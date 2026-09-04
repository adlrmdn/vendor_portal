<?php

namespace App\Http\Controllers;

use App\Exports\FabricDeliveryToleranceExport;
use App\Models\FinanceRpaCheck;
use App\Services\FabricDeliveryToleranceService;
use App\Services\RpaQueueReadService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class FinanceAdminController extends Controller
{
    private const PER_PAGE = 25;

    public function __construct(
        private RpaQueueReadService $rpaQueueReader,
        private FabricDeliveryToleranceService $fabricDeliveryTolerance,
    ) {
        $this->middleware('auth');

        $this->middleware(function ($request, $next) {
            if (! in_array(Auth::user()->role, ['admin', 'finance_admin'])) {
                abort(403, 'Unauthorized access.');
            }

            return $next($request);
        });
    }

    public function dashboard()
    {
        return view('finance.admin.dashboard', [
            'invoiceStats' => $this->statusCounts('invoice'),
            'debitNoteStats' => $this->statusCounts('deduction'),
        ]);
    }

    /**
     * Subcon CMT POs that D365 already shows as Received (i.e. the vendor's
     * service is fully delivered — see CLAUDE.md: the PO line only ever
     * carries a generic "Item Jasa CMT" service item) but that have never had
     * an invoice RPA job queued for them at all. Not a status filter on an
     * existing rpa_queues row — a gap report: these POs aren't in that table
     * in any status, which is exactly what makes them easy to miss otherwise.
     *
     * "Already invoiced" is read once as a flat set of every PO ever seen in
     * an `invoice` row's payload (any status — pending/failed/completed all
     * count as "already there"), matched by substring rather than equality
     * since a payload's PO field has historically carried more than one PO
     * comma-joined (see the #240 anomaly — a canceled+reissued PO pair from
     * forge's string_agg) — a straight `IN` list would miss those.
     */
    public function pendingPayment(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $rows = $this->pendingPaymentRows($search);

        $page = max(1, (int) $request->query('page', 1));
        $paginated = new LengthAwarePaginator(
            $rows->slice(($page - 1) * self::PER_PAGE, self::PER_PAGE)->values(),
            $rows->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('finance.admin.pending-payment', ['rows' => $paginated, 'filters' => ['q' => $search]]);
    }

    /** Same rows as pendingPayment(), as a PDF — the whole (filtered) list, no pagination. */
    public function pendingPaymentExport(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $rows = $this->pendingPaymentRows($search);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('finance.pdf.pending-payment', [
            'rows' => $rows,
            'search' => $search,
            'generatedAt' => now('Asia/Jakarta')->format('d M Y H:i').' WIB',
        ])->setPaper('a4', 'landscape');

        return $pdf->download('pending-payment-'.now('Asia/Jakarta')->format('Ymd-Hi').'.pdf');
    }

    /**
     * @return \Illuminate\Support\Collection<int,object{po:string,vendor_name:string,item_batch:?string,plm_id:?string,qty:?float,amount:?float,received_at:?string}>
     */
    private function pendingPaymentRows(string $search): \Illuminate\Support\Collection
    {
        $poLines = DB::connection('vsm')->table('po_lines')
            ->where('LineDescription', 'Item Jasa CMT')
            ->where('PurchaseOrderLineStatus', 'Received')
            ->orderByDesc('ModifiedDateTime1')
            ->get(['PurchaseOrderNumber', 'PLMId', 'ItemBatchNumber', 'OrderedPurchaseQuantity', 'LineAmount', 'ModifiedDateTime1']);

        $poNumbers = $poLines->pluck('PurchaseOrderNumber')->unique()->values()->all();
        $vendorsByPo = empty($poNumbers) ? collect() : DB::connection('vsm')->table('po_headers')
            ->whereIn('PurchaseOrderNumber', $poNumbers)
            ->pluck('PurchaseOrderName', 'PurchaseOrderNumber');

        $invoicedPayloads = DB::connection('rpa')->table('rpa_queues')
            ->where('rpa_type', 'invoice')
            ->pluck('payload');
        $invoicedText = $invoicedPayloads->map(function ($payload) {
            $decoded = json_decode($payload ?? '', true) ?: [];

            return (string) ($decoded['PO'] ?? '');
        })->implode(' | ');

        $rows = $poLines->reject(fn ($line) => str_contains($invoicedText, (string) $line->PurchaseOrderNumber))
            ->map(fn ($line) => (object) [
                'po' => $line->PurchaseOrderNumber,
                'vendor_name' => $vendorsByPo[$line->PurchaseOrderNumber] ?? '-',
                'item_batch' => $line->ItemBatchNumber,
                'plm_id' => $line->PLMId,
                'qty' => $line->OrderedPurchaseQuantity,
                'amount' => $line->LineAmount,
                'received_at' => $line->ModifiedDateTime1,
            ])
            ->values();

        if ($search !== '') {
            $needle = strtolower($search);
            $rows = $rows->filter(fn ($row) => str_contains(strtolower($row->po), $needle)
                || str_contains(strtolower($row->vendor_name), $needle))->values();
        }

        return $rows;
    }

    /**
     * Fabric PO lines that shipped outside their original delivery tolerance
     * (see FabricDeliveryToleranceService). Filtered/paginated in memory —
     * same shape as pendingPaymentRows(), and cheap for the same reason: this
     * is a small exceptions list, not the full PO history.
     */
    public function fabricDelivery(Request $request)
    {
        $filters = $this->fabricDeliveryFilters($request);
        $rows = $this->fabricDeliveryRows($filters);

        $page = max(1, (int) $request->query('page', 1));
        $paginated = new LengthAwarePaginator(
            $rows->slice(($page - 1) * self::PER_PAGE, self::PER_PAGE)->values(),
            $rows->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('finance.admin.fabric-delivery', ['rows' => $paginated, 'filters' => $filters]);
    }

    /** Same (filtered) rows as fabricDelivery(), as an .xlsx — no pagination. */
    public function fabricDeliveryExport(Request $request)
    {
        $rows = $this->fabricDeliveryRows($this->fabricDeliveryFilters($request));

        $name = 'fabric-delivery-'.now('Asia/Jakarta')->format('Ymd-Hi').'.xlsx';

        return Excel::download(new FabricDeliveryToleranceExport($rows), $name);
    }

    private function fabricDeliveryFilters(Request $request): array
    {
        return [
            'q' => trim((string) $request->query('q', '')),
            'type' => $request->query('type', ''),
        ];
    }

    private function fabricDeliveryRows(array $filters): \Illuminate\Support\Collection
    {
        $rows = $this->fabricDeliveryTolerance->outsideOriginalToleranceRows();

        if ($filters['q'] !== '') {
            $needle = strtolower($filters['q']);
            $rows = $rows->filter(fn ($row) => str_contains(strtolower((string) $row->po_number), $needle)
                || str_contains(strtolower((string) $row->vendor_name), $needle)
                || str_contains(strtolower((string) $row->style), $needle))->values();
        }

        if (in_array($filters['type'], ['UNDER', 'OVER'], true)) {
            $rows = $rows->where('type', $filters['type'])->values();
        }

        return $rows;
    }

    public function invoices(Request $request)
    {
        return view('finance.admin.invoices', array_merge(
            $this->rpaRows('invoice', $request),
            ['rpaType' => 'invoice']
        ));
    }

    public function debitNotes(Request $request)
    {
        return view('finance.admin.debit-notes', array_merge(
            $this->rpaRows('deduction', $request),
            ['rpaType' => 'deduction']
        ));
    }

    /**
     * Finance's own review checkbox on a completed invoice/deduction: writes
     * straight to rpa_queues.checked (the shared column other consumers of
     * this table already read/write), scoped to completed rows only — there's
     * nothing to review before the RPA job has actually finished. Only the
     * boolean flips on the shared row; updated_at is left untouched so it
     * doesn't fall back into "today" for an older completed job. The local
     * finance_rpa_checks record is purely a "who/when" audit decoration on
     * top of that shared flag, not the source of truth for the checkbox
     * itself.
     */
    public function toggleCheck(Request $request, int $id)
    {
        $row = DB::connection('rpa')->table('rpa_queues')->where('id', $id)->first(['id', 'status', 'checked', 'rpa_type', 'entity_id']);

        if (! $row) {
            abort(404);
        }

        $wasChecked = (bool) $row->checked;
        $forcedComplete = false;

        if ($row->status === 'completed') {
            $newValue = ! $row->checked;

            DB::connection('rpa')->table('rpa_queues')->where('id', $id)->update(['checked' => $newValue]);
        } else {
            // Finance can force a still-pending/failed job straight to
            // "checked" — meaning they've verified it's actually done another
            // way (e.g. handled manually in D365) and want it off the pending
            // list — but that also has to force rpa_queues.status to
            // 'completed', or it'd keep showing as waiting/processing/failed
            // everywhere else that reads this shared table. One-way and
            // requires explicit confirmation from the client (the `force`
            // flag) since it's bypassing the actual RPA automation.
            if (! $request->boolean('force')) {
                return response()->json(['message' => 'Confirmation required to check a non-completed job.'], 422);
            }

            $newValue = true;
            $forcedComplete = true;

            DB::connection('rpa')->table('rpa_queues')->where('id', $id)->update([
                'checked' => true,
                'status' => 'completed',
                'updated_at' => now(),
            ]);
        }

        if ($newValue && ! $wasChecked && $row->rpa_type === 'invoice') {
            $this->retryWaitingDeduction($row->entity_id);
        }

        if ($newValue) {
            FinanceRpaCheck::updateOrCreate(
                ['rpa_queue_id' => $id],
                [
                    'rpa_type' => (string) $request->input('rpa_type'),
                    'checked_by' => Auth::id(),
                    'checked_at' => now(),
                ]
            );
        } else {
            FinanceRpaCheck::where('rpa_queue_id', $id)->delete();
        }

        return response()->json([
            'checked' => $newValue,
            'forced_complete' => $forcedComplete,
        ]);
    }

    /**
     * Checking off an invoice signals its sibling debit note (same
     * entity_id/packaging project) is ready to be chased again rather than
     * sitting idle: a deduction row still stuck in 'waiting' is bumped back
     * to 'pending' so the external RPA bot retries it on its next pass,
     * instead of Finance having to fall back to `rpa:reconcile-po --fix`.
     * Deliberately scoped to 'waiting' only — 'failed'/'processing' rows are
     * left alone, since those need their own investigation, not a blind
     * retry.
     */
    private function retryWaitingDeduction(?string $entityId): void
    {
        if ($entityId === null || $entityId === '') {
            return;
        }

        DB::connection('rpa')->table('rpa_queues')
            ->where('entity_id', $entityId)
            ->where('rpa_type', 'deduction')
            ->where('status', 'waiting')
            ->update(['status' => 'pending', 'updated_at' => now()]);
    }

    /**
     * Bulk version of retryWaitingDeduction() for the "Retry all waiting"
     * button on the Debit Notes tab — bumps every deduction row still stuck
     * in 'waiting' back to 'pending' so the RPA bot retries the lot on its
     * next pass, rather than Finance reaching for `rpa:reconcile-po --fix`.
     */
    public function retryAllWaitingDebitNotes()
    {
        $count = DB::connection('rpa')->table('rpa_queues')
            ->where('rpa_type', 'deduction')
            ->where('status', 'waiting')
            ->update(['status' => 'pending', 'updated_at' => now()]);

        return response()->json(['retried' => $count]);
    }

    /**
     * Streams the signed PDF report attached to an invoice/deduction job
     * (payload.signed_doc, a data: URI) inline, if one was attached. The doc
     * is usually offloaded to S3 (RpaQueueService::offloadSignedDocToS3) —
     * payload.signed_doc_s3_key is resolved in that case.
     */
    public function report(string $rpaType, int $id)
    {
        $row = DB::connection('rpa')->table('rpa_queues')
            ->where('id', $id)->where('rpa_type', $rpaType)
            ->first(['id', 'payload']);

        if (! $row) {
            abort(404);
        }

        $payload = json_decode($row->payload ?? '', true) ?: [];
        $signedDoc = $this->rpaQueueReader->resolveSignedDoc($payload);

        if ($signedDoc === '' || ! str_contains($signedDoc, 'base64,')) {
            abort(404, 'No report attached to this job.');
        }

        $pdf = base64_decode(substr($signedDoc, strpos($signedDoc, 'base64,') + 7));

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$rpaType.'-'.$id.'.pdf"',
        ]);
    }

    /**
     * Count per status for a given rpa_type. Not date-scoped — see
     * RpaQueueReadService for why.
     */
    private function statusCounts(string $rpaType): array
    {
        $counts = DB::connection('rpa')->table('rpa_queues')
            ->where('rpa_type', $rpaType)
            ->whereIn('status', RpaQueueReadService::STATUSES)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $stats = [
            'waiting' => (int) ($counts['waiting'] ?? 0),
            'processing' => (int) ($counts['processing'] ?? 0),
            'completed' => (int) ($counts['completed'] ?? 0),
            'failed' => (int) ($counts['failed'] ?? 0),
        ];
        $stats['total'] = array_sum($stats);

        return $stats;
    }

    /**
     * rpa_queues rows for a given type, decorated with portal/report/checked
     * info, then filtered by status/portal/checked/search and paged.
     * Filtering happens in memory (after decoding each row's JSON payload)
     * rather than pushed into the query, since portal and checked-by-name both
     * depend on data outside rpa_queues itself (Vendor lookup, local
     * finance_rpa_checks) — not date-scoped, so this stays as small as the
     * unchecked/recent backlog actually is (see RpaQueueReadService).
     *
     * @return array{rows: LengthAwarePaginator, filters: array<string,string>}
     */
    private function rpaRows(string $rpaType, Request $request): array
    {
        $filters = [
            'status' => $request->query('status', ''),
            'portal' => $request->query('portal', ''),
            'checked' => $request->query('checked', ''),
            'q' => trim((string) $request->query('q', '')),
        ];

        $rows = $this->rpaQueueReader->rows($rpaType);

        if ($filters['status'] !== '' && in_array($filters['status'], RpaQueueReadService::STATUSES, true)) {
            $rows = $rows->where('status', $filters['status']);
        }
        if ($filters['portal'] !== '') {
            $rows = $rows->where('portal', $filters['portal']);
        }
        if ($filters['checked'] !== '') {
            $rows = $rows->where('checked', $filters['checked'] === '1');
        }
        if ($filters['q'] !== '') {
            $needle = strtolower($filters['q']);
            $rows = $rows->filter(fn ($row) => str_contains(strtolower($row->po), $needle)
                || str_contains(strtolower($row->vendor_name), $needle));
        }

        // Actionable rows (completed, not yet checked) float to the top,
        // then failed rows — these need attention too — then waiting (still
        // needs monitoring) and processing, then already-checked completed
        // rows last, since those are fully resolved and reviewed. Previously
        // waiting/processing sorted after checked-completed rows, burying an
        // aging waiting backlog under already-done work indefinitely.
        // updated_at desc is kept as the tiebreaker within each group since
        // sortBy() is stable.
        $rows = $rows->sortBy(function ($row) {
            if ($row->status === 'completed') {
                return $row->checked ? 4 : 0;
            }

            return match ($row->status) {
                'failed' => 1,
                'waiting' => 2,
                default => 3, // processing
            };
        })->values();

        $page = max(1, (int) $request->query('page', 1));

        $paginated = new LengthAwarePaginator(
            $rows->slice(($page - 1) * self::PER_PAGE, self::PER_PAGE)->values(),
            $rows->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return ['rows' => $paginated, 'filters' => $filters];
    }

    /**
     * Count of completed-but-not-yet-checked rows for a type — drives the
     * sidebar tab badge. Not date-scoped (see RpaQueueReadService). Deliberately
     * a plain DB count (no payload decode or portal resolution needed) so it's
     * cheap enough to run on every page load from the sidebar partial.
     */
    public static function uncheckedCount(string $rpaType): int
    {
        return DB::connection('rpa')->table('rpa_queues')
            ->where('rpa_type', $rpaType)
            ->where('status', 'completed')
            ->where('checked', false)
            ->count();
    }
}
