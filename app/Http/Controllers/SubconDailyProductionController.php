<?php

namespace App\Http\Controllers;

use App\Models\SubconOrder;
use App\Models\Vendor;
use App\Models\Vsm\SubconDailyProductionEntry;
use App\Services\SubconDailyProductionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Factory-floor daily production tracking, independent of the SubconOrder
 * approval workflow. One page, one <form>, one Save button — but each style
 * gets its own full table (own header row, own Size/Target/Cutting/Sewing/
 * Finishing-group columns), stacked one after another, rather than one
 * table shared across every style. That's because Cutting has a third
 * dimension (which ratio/marker produced the output) and different styles
 * genuinely use different ratios — a single shared <colgroup> can't give
 * two rows a different number of columns, so "this style's own ratio
 * columns" requires this style to have its own table. Load to Sewing and
 * Load to Finishing are no longer entered directly (computed instead — see
 * SubconDailyProductionService::forOrder()), and Finishing & Packing is now
 * its own 3-step group (Finishing/QC/Packing) the same shape as Cutting's
 * ratio group, minus the "+"/Total (see finishingStages() below for why).
 *
 * Vendor actions (index/storeAll/activate/close/reopen) are gated by
 * 'role:subcon_vendor' on /subcon/vendor; admin actions (adminIndex) are
 * read-only and gated by 'role:admin,subcon_admin' on /subcon/admin — both
 * enforced entirely by the route-group middleware (see routes/web.php),
 * this controller carries no auth check of its own. There's no per-style
 * page — each style's "View Work Order" link goes to the actual
 * SubconOrder page instead (see style-progress-rows.blade.php).
 */
class SubconDailyProductionController extends Controller
{
    // --- Vendor -------------------------------------------------------

    public function index(Request $request, SubconDailyProductionService $service)
    {
        $vendorId = Auth::user()->vendor_id;
        $date = $this->resolveDate($request);

        $orders = SubconOrder::forVendor($vendorId)
            ->where('status', '!=', 'cancelled')
            ->orderBy('created_at', 'desc')
            ->get();

        $statuses = $service->statusesFor($orders);

        $board = $orders->filter(fn ($o) => $this->isVisibleOnBoard(
            $statuses[$o->id]['status'] ?? 'not_started', $statuses[$o->id]['closed_at'] ?? null, $date
        ))
            ->sortBy(fn ($o) => ($statuses[$o->id]['status'] ?? '') === 'closed' ? 1 : 0)
            ->values();
        $addable = $orders->filter(fn ($o) => ($statuses[$o->id]['status'] ?? 'not_started') === 'not_started')->values();

        $breakdowns = $board->mapWithKeys(fn ($o) => [$o->id => $service->cuttingRatioBreakdown($o)]);
        $collapsedIds = $this->collapsedOrderIds($request);
        $dayNotes = $service->dayNotesFor($board, $date, $vendorId);

        $groups = $board->map(function ($order) use ($service, $date, $statuses, $breakdowns, $request, $collapsedIds, $dayNotes) {
            $ratioColumns = $this->resolveRatioColumnsFor($request, $order->id, $breakdowns[$order->id]);

            return $this->buildStyleData(
                $order, $service, $date, $statuses[$order->id]['status'] ?? 'not_started',
                $breakdowns[$order->id], $ratioColumns, in_array($order->id, $collapsedIds, true),
                $dayNotes['styles'][$order->id] ?? null
            );
        })->all();

        return view('subcon.vendor.daily-production.index', [
            'groups' => $groups,
            'addable' => $addable,
            'date' => $date,
            'flatStages' => $this->flatStages($service),
            'finishingStages' => $this->finishingStages($service),
            'dayNote' => $dayNotes['general'] ?? null,
        ]);
    }

    /**
     * Which styles' Cutting ratio columns are currently collapsed to just
     * a Total (?collapsed[]=order_id, toggled by dpToggleCuttingRatios() in
     * the view). A plain reload with the ratio columns simply not rendered
     * — not a client-side show/hide — because a table under
     * table-layout:fixed has to have its <colgroup>/<col> widths match
     * what's actually on the page from the first paint; trying to resize
     * columns after the fact via JS (three different ways were tried) kept
     * breaking the layout in some new way each time.
     */
    private function collapsedOrderIds(Request $request): array
    {
        return array_map('strval', (array) $request->query('collapsed', []));
    }

    /**
     * Whether a style belongs on the board for the given viewed date. Open
     * styles always show. A closed style shows through the day it was
     * closed (so closing one is still visible right after, as
     * confirmation) but disappears from every later day's view — keeps
     * the board from accumulating every closed style forever, per the
     * user's request. A missing closed_at (shouldn't happen, but data can
     * be odd) fails open — still shown — rather than silently vanishing.
     */
    private function isVisibleOnBoard(string $status, ?string $closedAt, string $viewDate): bool
    {
        if ($status === 'open') {
            return true;
        }
        if ($status !== 'closed') {
            return false;
        }

        return $closedAt === null || $viewDate <= $closedAt;
    }

    private function resolveDate(Request $request): string
    {
        $date = $request->query('date', now()->toDateString());
        try {
            $parsed = \Illuminate\Support\Carbon::parse($date)->toDateString();
        } catch (\Throwable $e) {
            return now()->toDateString();
        }

        return $parsed > now()->toDateString() ? now()->toDateString() : $parsed;
    }

    /**
     * The one stage that's still a single, plain column — Sewing. Cutting
     * has its own ratio group (resolveRatioColumnsFor()), Finishing &
     * Packing has its own 3-step group (finishingStages()), and Load to
     * Sewing/Load to Finishing are computed rather than entered (see
     * SubconDailyProductionService::forOrder()) so they're not rendered at
     * all.
     */
    private function flatStages(SubconDailyProductionService $service): array
    {
        return [SubconDailyProductionEntry::STAGE_SEWING => $service->stages()[SubconDailyProductionEntry::STAGE_SEWING]];
    }

    /**
     * Finishing & Packing's own group of 3 real, fixed steps — Finishing,
     * QC, Packing, in physical order. Unlike Cutting's ratio group these
     * are a fixed set (no "+" to add more) and deliberately have no
     * combined Total column: a ratio group's columns are additive (each
     * ratio/marker is a distinct batch of pieces cut in parallel, so
     * summing them is a real total), but these 3 are sequential steps the
     * *same* pieces move through one after another — summing "5 finished +
     * 5 QC'd + 5 packed" would triple-count the same 5 pieces, not report
     * 15 real ones.
     */
    private function finishingStages(SubconDailyProductionService $service): array
    {
        $stages = $service->stages();

        return [
            SubconDailyProductionEntry::STAGE_FINISHING => $stages[SubconDailyProductionEntry::STAGE_FINISHING],
            SubconDailyProductionEntry::STAGE_QC => $stages[SubconDailyProductionEntry::STAGE_QC],
            SubconDailyProductionEntry::STAGE_PACKING => $stages[SubconDailyProductionEntry::STAGE_PACKING],
        ];
    }

    /**
     * One style's own Cutting ratio columns: every ratio label this style's
     * breakdown already has data under, plus any the vendor just added via
     * that style's own "+ Add Ratio Column" control
     * (?ratios[{order_id}][]=..., scoped per order so adding a ratio to one
     * style never touches another's columns — see the view). A brand-new
     * ratio therefore appears as a blank column immediately, before
     * anything is saved under it.
     */
    private function resolveRatioColumnsFor(Request $request, string $orderId, array $breakdown): array
    {
        $used = collect($breakdown['ratios'] ?? [])->pluck('raw_label');
        $pendingByOrder = (array) $request->query('ratios', []);
        $pending = collect((array) ($pendingByOrder[$orderId] ?? []));

        return $used->merge($pending)
            ->map(fn ($r) => trim((string) $r))
            ->filter(fn ($r) => $r !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Everything one style's table needs: its size rows (per-stage
     * cumulative + the selected date's value for Sewing and the Finishing
     * group, and per-ratio-column cumulative + the selected date's value
     * for Cutting) plus this style's own ratio column list.
     * $cuttingCollapsed doesn't drop any data — the Cutting Total column is
     * still computed from every ratio's real figures either way — it only
     * tells the view to skip rendering the individual ratio columns.
     */
    private function buildStyleData(SubconOrder $order, SubconDailyProductionService $service, string $date, ?string $status, array $cuttingBreakdown, array $ratioColumns, bool $cuttingCollapsed = false, ?string $dayNote = null): array
    {
        $data = $service->forOrder($order);
        // Sewing (flat) + Finishing/QC/Packing (grouped) all get the same
        // simple per-size cumulative/day-value shape — only Cutting needs
        // the extra ratio dimension, handled separately below.
        $stageKeys = array_merge(array_keys($this->flatStages($service)), array_keys($this->finishingStages($service)));
        $ratioGroups = collect($cuttingBreakdown['ratios'] ?? [])->keyBy('raw_label');

        $sizes = array_map(function ($size) use ($data, $stageKeys, $date, $ratioColumns, $ratioGroups) {
            $stageData = [];
            foreach ($stageKeys as $key) {
                $dayRows = collect($data['history'][$key] ?? [])->firstWhere('entry_date', $date)['rows'] ?? [];
                $stageData[$key] = [
                    'cumulative' => $data['totals'][$key][$size['prod_id']] ?? 0,
                    'day_value' => $dayRows[$size['prod_id']] ?? null,
                ];
            }

            $cuttingData = [];
            foreach ($ratioColumns as $ratioLabel) {
                $ratioGroup = $ratioGroups->get($ratioLabel);
                $dayVal = null;
                if ($ratioGroup) {
                    $dayRow = collect($ratioGroup['dates'])->firstWhere('entry_date', $date);
                    $dayVal = $dayRow['values'][$size['prod_id']] ?? null;
                }
                $cuttingData[$ratioLabel] = [
                    'cumulative' => $ratioGroup['subtotal'][$size['prod_id']] ?? 0,
                    'day_value' => $dayVal,
                ];
            }

            return $size + ['stages' => $stageData, 'cutting' => $cuttingData];
        }, $data['sizes']);

        return [
            'order' => $order,
            'status' => $status ?? 'not_started',
            'sizes' => $sizes,
            'readyToClose' => $service->isReadyToClose($data),
            'ratioColumns' => $ratioColumns,
            'cuttingCollapsed' => $cuttingCollapsed,
            'dayNote' => $dayNote,
        ];
    }

    public function activate(Request $request, SubconDailyProductionService $service)
    {
        $data = $request->validate([
            'order_id' => ['required', 'string'],
            'date' => ['nullable', 'date'],
        ]);
        $order = SubconOrder::forVendor(Auth::user()->vendor_id)->findOrFail($data['order_id']);

        try {
            $service->activateStyle($order, Auth::user());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Could not add this style — please try again.');
        }

        return redirect()
            ->route('subcon.vendor.daily-production.index', ['date' => $data['date'] ?? now()->toDateString()])
            ->with('success', 'Style added — log its output below.');
    }

    /**
     * Bulk save: every size × column input on the page for one selected
     * date, in a single submit. Nested as entries[order_id][stage][prod_id]
     * for Sewing and the Finishing group (Finishing/QC/Packing), and
     * entries[order_id][cutting][ratio_label][prod_id] for Cutting's ratio
     * sub-columns; each (order, stage[, ratio]) group is delegated to
     * saveEntry(), so a closed style or a vsm failure surfaces per group
     * rather than silently dropping the rest. Redirects back to the board,
     * preserving any ?ratios=/?collapsed= state that was showing.
     */
    public function storeAll(Request $request, SubconDailyProductionService $service)
    {
        $data = $request->validate([
            'entry_date' => ['required', 'date', 'before_or_equal:today'],
            'entries' => ['nullable', 'array'],
            'notes' => ['nullable', 'array'],
            'notes.*' => ['nullable', 'string', 'max:2000'],
            'day_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $vendorId = Auth::user()->vendor_id;
        $user = Auth::user();
        $entryDate = $data['entry_date'];
        $acceptedStageKeys = array_merge(array_keys($this->flatStages($service)), array_keys($this->finishingStages($service)));
        $failed = [];
        $saved = false;

        foreach ((array) ($data['entries'] ?? []) as $orderId => $byStage) {
            $order = SubconOrder::forVendor($vendorId)->where('id', $orderId)->first();
            if (! $order) {
                continue;
            }

            foreach ((array) $byStage as $stage => $payload) {
                if ($stage === SubconDailyProductionEntry::STAGE_CUTTING) {
                    foreach ((array) $payload as $ratioLabel => $byProdId) {
                        $ratioLabel = trim((string) $ratioLabel);
                        $rows = $this->collectRows((array) $byProdId);
                        if ($ratioLabel === '' || empty($rows)) {
                            continue;
                        }

                        try {
                            $service->saveEntry($order, $stage, $entryDate, $rows, $user, $ratioLabel);
                            $saved = true;
                        } catch (\Throwable $e) {
                            report($e);
                            $failed[] = $order->order_number.' — Cutting ('.$ratioLabel.')';
                        }
                    }

                    continue;
                }

                if (! in_array($stage, $acceptedStageKeys, true)) {
                    continue;
                }

                $rows = $this->collectRows((array) $payload);
                if (empty($rows)) {
                    continue;
                }

                try {
                    $service->saveEntry($order, $stage, $entryDate, $rows, $user);
                    $saved = true;
                } catch (\Throwable $e) {
                    report($e);
                    $failed[] = $order->order_number.' — '.($service->stages()[$stage] ?? $stage);
                }
            }
        }

        if ($request->has('notes')) {
            foreach ((array) ($data['notes'] ?? []) as $orderId => $noteText) {
                $order = SubconOrder::forVendor($vendorId)->where('id', $orderId)->first();
                if ($order) {
                    try {
                        $service->saveStyleDayNote($order, $entryDate, $noteText, $user);
                        if (trim((string) $noteText) !== '') {
                            $saved = true;
                        }
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }
            }
        }

        if ($request->has('day_note')) {
            try {
                $service->saveVendorDayNote($vendorId, $entryDate, $data['day_note'] ?? null, $user);
                if (trim((string) ($data['day_note'] ?? '')) !== '') {
                    $saved = true;
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if (! empty($failed)) {
            return back()->with('error', 'Could not save: '.implode('; ', $failed).'.');
        }

        return back()->with('success', $saved ? "Daily production saved for {$entryDate}." : 'Nothing to save — no quantities or notes entered.');
    }

    /** @return array<int, array{prod_id: string, quantity: mixed}> */
    private function collectRows(array $byProdId): array
    {
        $rows = [];
        foreach ($byProdId as $prodId => $quantity) {
            if ($quantity === null || $quantity === '') {
                continue;
            }
            $rows[] = ['prod_id' => $prodId, 'quantity' => $quantity];
        }

        return $rows;
    }

    /**
     * Excel export of the whole board — cumulative totals to date (the
     * same "X of Y made" figures already on screen), not a day-by-day
     * ledger. One sheet, every style stacked vertically as its own
     * mini-table, same reasoning as the on-screen board: different styles
     * use different Cutting ratio/marker columns, so one shared table
     * can't fit them all.
     */
    public function export(Request $request, SubconDailyProductionService $service)
    {
        $vendorId = Auth::user()->vendor_id;
        $date = now()->toDateString(); // export is always "as of now" — see isVisibleOnBoard()

        $orders = SubconOrder::forVendor($vendorId)
            ->where('status', '!=', 'cancelled')
            ->orderBy('created_at', 'desc')
            ->get();

        $statuses = $service->statusesFor($orders);

        $board = $orders->filter(fn ($o) => $this->isVisibleOnBoard(
            $statuses[$o->id]['status'] ?? 'not_started', $statuses[$o->id]['closed_at'] ?? null, $date
        ))
            ->sortBy(fn ($o) => ($statuses[$o->id]['status'] ?? '') === 'closed' ? 1 : 0)
            ->values();

        $groups = $this->exportGroups($board, $service, $statuses);

        [$rows, $boldRows] = $this->buildExportRows($groups, $service);

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\SubconDailyProductionExport($rows, $boldRows),
            'daily-production_'.now()->format('Ymd-His').'.xlsx'
        );
    }

    /**
     * Same style-data shape buildStyleData() gives the board view, but
     * ratio columns come straight from real saved data (cuttingRatioBreakdown)
     * rather than resolveRatioColumnsFor()'s ?ratios= query-param merge —
     * an export shouldn't depend on transient "not yet saved" UI state from
     * whatever URL happened to trigger it. $date is arbitrary (today) since
     * only cumulative figures are used, never day_value.
     */
    private function exportGroups(\Illuminate\Support\Collection $board, SubconDailyProductionService $service, array $statuses): array
    {
        $date = now()->toDateString();
        $dayNotes = $service->dayNotesFor($board, $date);

        return $board->map(function ($order) use ($service, $date, $statuses, $dayNotes) {
            $breakdown = $service->cuttingRatioBreakdown($order);
            $ratioColumns = collect($breakdown['ratios'] ?? [])
                ->pluck('raw_label')
                ->map(fn ($r) => trim((string) $r))
                ->filter(fn ($r) => $r !== '')
                ->unique()
                ->sort()
                ->values()
                ->all();

            return $this->buildStyleData(
                $order, $service, $date, $statuses[$order->id]['status'] ?? 'not_started',
                $breakdown, $ratioColumns, false, $dayNotes['styles'][$order->id] ?? null
            );
        })->all();
    }

    /**
     * @return array{0: array<int, array<int, string|int|float>>, 1: array<int, int>} [$rows, $boldRows]
     */
    private function buildExportRows(array $groups, SubconDailyProductionService $service, bool $includeVendorColumn = false): array
    {
        $stageLabels = $this->flatStages($service) + $this->finishingStages($service);
        $stageKeys = array_keys($stageLabels);

        $rows = [];
        $boldRows = [];

        foreach ($groups as $group) {
            $order = $group['order'];
            $ratioColumns = $group['ratioColumns'];

            $titleRow = [$order->title ?: $order->order_number, $order->order_number, ucfirst($group['status'])];
            if ($includeVendorColumn) {
                $titleRow[] = $order->vendor->name ?? '';
            }
            if (! empty($group['dayNote'])) {
                $titleRow[] = 'Note: '.$group['dayNote'];
            }
            $rows[] = $titleRow;
            $boldRows[] = count($rows);

            if (empty($group['sizes'])) {
                $rows[] = ['No sized production lines resolved for this PO yet.'];
                $rows[] = [''];

                continue;
            }

            $rows[] = array_merge(
                ['Size', 'Order Qty'],
                $ratioColumns,
                ['Cutting Total'],
                array_values($stageLabels)
            );
            $boldRows[] = count($rows);

            $targetSum = 0;
            $ratioSums = array_fill_keys($ratioColumns, 0);
            $cuttingTotalSum = 0;
            $stageSums = array_fill_keys($stageKeys, 0);

            foreach ($group['sizes'] as $size) {
                $targetSum += (float) $size['target_qty'];
                $row = [$size['size'] ?: '—', $size['target_qty']];

                $rowCuttingTotal = 0;
                foreach ($ratioColumns as $r) {
                    $cum = (int) $size['cutting'][$r]['cumulative'];
                    $row[] = $cum;
                    $ratioSums[$r] += $cum;
                    $rowCuttingTotal += $cum;
                }
                $row[] = $rowCuttingTotal;
                $cuttingTotalSum += $rowCuttingTotal;

                foreach ($stageKeys as $key) {
                    $cum = (int) $size['stages'][$key]['cumulative'];
                    $row[] = $cum;
                    $stageSums[$key] += $cum;
                }

                $rows[] = $row;
            }

            $totalRow = ['Total', $targetSum];
            foreach ($ratioColumns as $r) {
                $totalRow[] = $ratioSums[$r];
            }
            $totalRow[] = $cuttingTotalSum;
            foreach ($stageKeys as $key) {
                $totalRow[] = $stageSums[$key];
            }
            $rows[] = $totalRow;
            $boldRows[] = count($rows);

            $rows[] = ['']; // spacer — an empty array is dropped by the writer, desyncing row indices
        }

        return [$rows, $boldRows];
    }

    public function close(string $id, SubconDailyProductionService $service)
    {
        $order = SubconOrder::forVendor(Auth::user()->vendor_id)->findOrFail($id);

        try {
            $service->closeStyle($order, Auth::user());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Could not close this style — please try again.');
        }

        return back()->with('success', 'Style closed — it stays on the board as a record, no further entries can be logged.');
    }

    public function reopen(string $id, SubconDailyProductionService $service)
    {
        $order = SubconOrder::forVendor(Auth::user()->vendor_id)->findOrFail($id);

        try {
            $service->reopenStyle($order);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Could not reopen this style — please try again.');
        }

        return back()->with('success', 'Style reopened.');
    }

    // --- Admin (read-only) ---------------------------------------------

    public function adminIndex(Request $request, SubconDailyProductionService $service)
    {
        $date = $this->resolveDate($request);

        $orders = SubconOrder::with('vendor')
            ->cmtOnly()
            ->where('status', '!=', 'cancelled')
            ->when($request->filled('vendor_id'), fn ($q) => $q->where('vendor_id', $request->query('vendor_id')))
            ->orderBy('created_at', 'desc')
            ->get();

        $statuses = $service->statusesFor($orders);

        $board = $orders->filter(fn ($o) => $this->isVisibleOnBoard(
            $statuses[$o->id]['status'] ?? 'not_started', $statuses[$o->id]['closed_at'] ?? null, $date
        ))
            ->sortBy(fn ($o) => ($statuses[$o->id]['status'] ?? '') === 'closed' ? 1 : 0)
            ->values();

        $breakdowns = $board->mapWithKeys(fn ($o) => [$o->id => $service->cuttingRatioBreakdown($o)]);
        $collapsedIds = $this->collapsedOrderIds($request);
        $vendorFilterId = $request->query('vendor_id');
        $dayNotes = $service->dayNotesFor($board, $date, $vendorFilterId ?: null);

        $groups = $board->map(function ($order) use ($service, $date, $statuses, $breakdowns, $request, $collapsedIds, $dayNotes) {
            $ratioColumns = $this->resolveRatioColumnsFor($request, $order->id, $breakdowns[$order->id]);

            return $this->buildStyleData(
                $order, $service, $date, $statuses[$order->id]['status'] ?? 'not_started',
                $breakdowns[$order->id], $ratioColumns, in_array($order->id, $collapsedIds, true),
                $dayNotes['styles'][$order->id] ?? null
            );
        })->all();

        $vendors = Vendor::where('type', 'subcon')->where('is_active', true)->orderBy('name')->get();

        return view('subcon.admin.daily-production.index', [
            'groups' => $groups,
            'date' => $date,
            'flatStages' => $this->flatStages($service),
            'finishingStages' => $this->finishingStages($service),
            'vendors' => $vendors,
            'dayNote' => $dayNotes['general'] ?? null,
        ]);
    }

    /** Same export as the vendor board, cross-vendor and respecting the same ?vendor_id= filter as adminIndex(), with a Vendor column. */
    public function adminExport(Request $request, SubconDailyProductionService $service)
    {
        $date = now()->toDateString(); // export is always "as of now" — see isVisibleOnBoard()

        $orders = SubconOrder::with('vendor')
            ->cmtOnly()
            ->where('status', '!=', 'cancelled')
            ->when($request->filled('vendor_id'), fn ($q) => $q->where('vendor_id', $request->query('vendor_id')))
            ->orderBy('created_at', 'desc')
            ->get();

        $statuses = $service->statusesFor($orders);

        $board = $orders->filter(fn ($o) => $this->isVisibleOnBoard(
            $statuses[$o->id]['status'] ?? 'not_started', $statuses[$o->id]['closed_at'] ?? null, $date
        ))
            ->sortBy(fn ($o) => ($statuses[$o->id]['status'] ?? '') === 'closed' ? 1 : 0)
            ->values();

        $groups = $this->exportGroups($board, $service, $statuses);

        [$rows, $boldRows] = $this->buildExportRows($groups, $service, includeVendorColumn: true);

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\SubconDailyProductionExport($rows, $boldRows),
            'daily-production_'.now()->format('Ymd-His').'.xlsx'
        );
    }
}
