<?php

namespace App\Services;

use App\Models\SubconAddProcessReport;
use App\Models\SubconJobLog;
use App\Models\SubconOfficialReport;
use App\Models\SubconOrder;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the Official Report (Berita Acara) — the per-Add-Process-
 * operation MD Production -> Director sign-off. See
 * docs/superpowers/specs/2026-09-22-subcon-official-report-design.md for the
 * full design; this class implements it.
 */
class SubconOfficialReportService
{
    public function __construct(
        private readonly SubconProductionService $production,
        private readonly D365JobTransactionService $d365,
    ) {}

    /**
     * Order/Cut/Good/Reject qty for one Add Process operation on a
     * production group, summed across every line VSM's own production
     * detail has for the group. Deliberately duplicates the same math
     * `resources/views/subcon/admin/add-process/index.blade.php` already
     * computes inline for its Yield/TOTAL row, rather than refactoring that
     * already-working view to call a shared method — this keeps the
     * Official Report's snapshot logic independent of a view template, and
     * matches this codebase's existing convention of tolerating this kind
     * of small duplication over coupling unrelated things (see
     * AdminController/VendorController::saveItemRolls in CLAUDE.md).
     *
     * @return array{order_qty: int, cut_qty: int, good_qty: int, reject_qty: int}
     */
    public function computeYield(string $productionGroup, string $operation): array
    {
        $groups = $this->production->forProductionGroup($productionGroup);
        $lines = collect($groups)->flatMap(fn ($g) => collect($g['lines'] ?? []))->values();
        $cutQuantities = $this->production->cutQuantitiesForGroup($productionGroup);

        $reports = SubconAddProcessReport::where('production_group', $productionGroup)
            ->where('operation', $operation)
            ->get()
            ->keyBy('prod_id');

        return [
            'order_qty' => (int) $lines->sum('Qty'),
            'cut_qty' => (int) $lines->sum(fn ($l) => $cutQuantities[$l->Size]['good'] ?? 0),
            'good_qty' => (int) $lines->sum(fn ($l) => $reports->get($l->ProdId)->qty ?? 0),
            'reject_qty' => (int) $lines->sum(fn ($l) => $reports->get($l->ProdId)->reject_qty ?? 0),
        ];
    }

    /**
     * Per-operation port of QcReportPdfService::calculateDeductions()'s
     * formula shape (spec §8) — floor(cutQty*0.01) tolerance,
     * salesPrice*0.70 penalty price, charged only on the qty exceeding that
     * tolerance, plus a manual additional_deduction folded into the total.
     *
     * @return array{allowed_limit: int, exceeding_reject_qty: int, penalty_price: float, reject_penalty: float, total_deduction: float}
     */
    public function computeDeduction(int $cutQty, int $rejectQty, float $salesPrice, float $additionalDeduction = 0.0): array
    {
        $allowedLimit = (int) floor($cutQty * 0.01);
        $exceedingReject = max(0, $rejectQty - $allowedLimit);
        $penaltyPrice = $salesPrice * 0.70;
        $rejectPenalty = $exceedingReject * $penaltyPrice;

        return [
            'allowed_limit' => $allowedLimit,
            'exceeding_reject_qty' => $exceedingReject,
            'penalty_price' => $penaltyPrice,
            'reject_penalty' => $rejectPenalty,
            'total_deduction' => $rejectPenalty + $additionalDeduction,
        ];
    }

    /**
     * §5 of the spec: both checks must pass before a send/resend is allowed.
     * Re-run on every send/resend POST, not just hidden client-side.
     *
     * @return array{ok: bool, reason: ?string, cmt_order: ?SubconOrder, matched_po: ?array}
     */
    public function gate(string $productionGroup, string $operation): array
    {
        $cmtOrder = SubconOrder::where('production_group', $productionGroup)->first();

        if ($cmtOrder) {
            if (in_array($cmtOrder->workflow_stage, [
                SubconOrder::STAGE_CUTTING,
                SubconOrder::STAGE_CUTTING_REVIEW,
            ], true)) {
                return ['ok' => false, 'reason' => 'Cutting has not been approved yet for this work order.', 'cmt_order' => $cmtOrder, 'matched_po' => null];
            }
        } else {
            $cutQuantities = $this->production->cutQuantitiesForGroup($productionGroup);
            $hasCutQty = collect($cutQuantities)->contains(fn ($c) => ($c['good'] ?? 0) > 0);
            if (! $hasCutQty) {
                return ['ok' => false, 'reason' => 'The D365 cut qty job sync is still blank for this in-house production group.', 'cmt_order' => null, 'matched_po' => null];
            }
        }

        $pos = $this->production->addProcessPurchaseOrdersForGroup($productionGroup);
        $matchedPo = $this->production->matchAddProcessPoForOperation($pos, $operation);

        if (! $matchedPo) {
            return ['ok' => false, 'reason' => "{$operation} has no confirmed purchase order yet.", 'cmt_order' => $cmtOrder, 'matched_po' => null];
        }

        if (strcasecmp($matchedPo['status'] ?? '', 'Invoiced') === 0) {
            return ['ok' => false, 'reason' => "{$operation}'s purchase order ({$matchedPo['po_number']}) is already invoiced — reporting is closed.", 'cmt_order' => $cmtOrder, 'matched_po' => $matchedPo];
        }

        return ['ok' => true, 'reason' => null, 'cmt_order' => $cmtOrder, 'matched_po' => $matchedPo];
    }

    /**
     * First send (no row exists yet) or resend (existing pending_ho row,
     * typically after a Director decline — revision_count is NOT
     * incremented here, only on decline; see applyDirectorDecline()).
     * Creates/updates the row directly in pending_ho and emails MD
     * Production. Throws \RuntimeException (message = gate()'s reason) when
     * blocked — callers turn that into a flash error.
     */
    public function send(string $productionGroup, string $operation, float $additionalDeduction, ?string $remarks, ?float $salesPriceOverride = null): SubconOfficialReport
    {
        $gate = $this->gate($productionGroup, $operation);
        if (! $gate['ok']) {
            throw new \RuntimeException($gate['reason']);
        }

        $matchedPo = $gate['matched_po'];
        $yield = $this->computeYield($productionGroup, $operation);

        $salesPrice = $salesPriceOverride;
        $salesPriceSource = $salesPrice !== null ? 'manual' : null;
        if ($salesPrice === null) {
            // forProductionGroup() always hardcodes plm_id to '' (it has no
            // real PLM lookup of its own) — the real PLM link, when one
            // exists, lives in plm_activity, same source
            // addProcessPurchaseOrdersForGroup() already reads elsewhere in
            // SubconProductionService. Best-effort: a vsm outage or a
            // standalone/in-house group with no PLM link both fall through
            // to the existing "admin fills sales_price manually" path.
            $plmId = null;
            try {
                $plmId = DB::connection('vsm')->table('plm_activity')
                    ->where('ProductionGroup', $productionGroup)
                    ->value('PLMId');
            } catch (\Throwable $e) {
                report($e);
            }
            $salesPrice = $plmId ? $this->d365->fetchSalesPrice((string) $plmId) : null;
            $salesPriceSource = $salesPrice !== null ? 'd365' : null;
        }

        $deduction = $this->computeDeduction($yield['cut_qty'], $yield['reject_qty'], (float) ($salesPrice ?? 0), $additionalDeduction);

        return SubconOfficialReport::updateOrCreate(
            ['production_group' => $productionGroup, 'operation' => $operation],
            [
                'status' => SubconOfficialReport::STATUS_PENDING_HO,
                'matched_po_number' => $matchedPo['po_number'] ?? null,
                'matched_po_vendor_code' => $matchedPo['vendor_code'] ?? null,
                'matched_po_vendor_name' => $matchedPo['vendor_name'] ?? null,
                'order_qty' => $yield['order_qty'],
                'cut_qty' => $yield['cut_qty'],
                'good_qty' => $yield['good_qty'],
                'reject_qty' => $yield['reject_qty'],
                'sales_price' => $salesPrice,
                'sales_price_source' => $salesPriceSource,
                'allowed_limit' => $deduction['allowed_limit'],
                'exceeding_reject_qty' => $deduction['exceeding_reject_qty'],
                'penalty_price' => $deduction['penalty_price'],
                'reject_penalty' => $deduction['reject_penalty'],
                'additional_deduction' => $additionalDeduction,
                'remarks' => $remarks,
                'director_decline_reason' => null,
            ]
        );
    }

    /**
     * Renders the Official Report PDF as a `data:application/pdf;base64,...`
     * URI (spec §9) — the shape RpaQueueService's offloadSignedDocToS3()
     * expects (matches QcReportPdfService::renderDataUri()'s contract).
     * Overview fields (article name / season / PLM id) come from
     * forProductionGroup()'s own group metadata, best-effort — a
     * standalone/PLM-less group simply renders '—' for those, same as the
     * rest of this feature treats a missing PLM link. forProductionGroup()
     * has no delivery-plan data source at all, so that field is always null
     * here (renders as '—') — a known, accepted limitation, not a bug.
     */
    public function renderPdfDataUri(SubconOfficialReport $report): ?string
    {
        try {
            $groups = $this->production->forProductionGroup($report->production_group);
            $first = $groups[0] ?? [];

            $bytes = \Barryvdh\DomPDF\Facade\Pdf::loadView('subcon.pdf.official-report', [
                'report' => $report,
                'articleName' => $first['article_name'] ?? null,
                'deliveryPlan' => null,
                'season' => $first['season'] ?? null,
                'plmId' => $first['plm_id'] ?? null,
            ])->setPaper('a4', 'portrait')->output();

            return 'data:application/pdf;base64,'.base64_encode($bytes);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Official Report PDF render failed', ['report' => $report->id, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * MD Production approves — first send or a post-decline resend (same
     * method either way, spec §6's "no HO-decline route" note). Deduction/
     * remarks are only overwritten when explicitly passed (the revision
     * case); this method never touches reject_penalty — it's unaffected by
     * additional_deduction. totalDeduction() is computed from the model's
     * current state, so it naturally reflects any new additional_deduction
     * once saved, without any recompute step here.
     */
    public function applyHoApproval(SubconOfficialReport $report, string $approver, ?float $additionalDeduction = null, ?string $remarks = null): void
    {
        if (! $report->isPendingHo()) {
            throw new \RuntimeException('This report is no longer awaiting MD Production approval.');
        }

        DB::transaction(function () use ($report, $approver, $additionalDeduction, $remarks) {
            if ($additionalDeduction !== null) {
                $report->additional_deduction = $additionalDeduction;
            }
            if ($remarks !== null) {
                $report->remarks = $remarks;
            }

            $report->ho_approval_signature = "Digitally Signed: {$approver}";
            $report->ho_approved_at = now();
            $report->status = SubconOfficialReport::STATUS_PENDING_DIRECTOR;
            $report->save();
        });

        $this->logResult($report, 'ho_approved', "HO approved by {$approver}.");
    }

    /**
     * Director approves — terminal. Renders the PDF once (signature is now
     * complete on the HO side, still pending on the Director side... but the
     * approval write happens first so the rendered PDF reflects BOTH
     * signatures), then queues Invoice + Deduction RPA (spec §10). RPA
     * queueing is best-effort and never blocks the approval itself — a
     * queueing failure is logged, not thrown.
     */
    public function applyDirectorApproval(SubconOfficialReport $report, string $approver): void
    {
        if (! $report->isPendingDirector()) {
            throw new \RuntimeException('This report is no longer awaiting Director authorization.');
        }

        DB::transaction(function () use ($report, $approver) {
            $report->director_approval_signature = "Digitally Signed: {$approver}";
            $report->director_approved_at = now();
            $report->status = SubconOfficialReport::STATUS_COMPLETED;
            $report->save();
        });

        $signedDoc = $this->renderPdfDataUri($report);
        if ($signedDoc !== null) {
            $rpaQueue = app(RpaQueueService::class);
            $rpaQueue->queueOfficialReportInvoice($report, $signedDoc);
            $rpaQueue->queueOfficialReportDeduction($report, $signedDoc);
        } else {
            \Illuminate\Support\Facades\Log::warning("Official Report {$report->id}: director approved but PDF render failed — RPA not queued.");
        }

        $this->logResult($report, 'director_approved', "Director approved by {$approver}.");
    }

    /**
     * Director declines — back to pending_ho for MD Production to revise,
     * never back to a QC stage (there isn't one) and never a bare resend
     * (spec §4). revision_count increments so the UI can tell a first send
     * from a resend (SubconOfficialReport::needsRevision()).
     */
    public function applyDirectorDecline(SubconOfficialReport $report, string $reason): void
    {
        if (! $report->isPendingDirector()) {
            throw new \RuntimeException('This report is no longer awaiting Director authorization.');
        }

        DB::transaction(function () use ($report, $reason) {
            $report->status = SubconOfficialReport::STATUS_PENDING_HO;
            $report->revision_count = $report->revision_count + 1;
            $report->director_decline_reason = $reason;
            $report->save();
        });

        $this->logResult($report, 'director_declined', "Director declined: {$reason}");
    }

    /** Audit trail via SubconJobLog, spec §3 — job_type "official_report:{operation}". */
    private function logResult(SubconOfficialReport $report, string $status, string $message): void
    {
        $order = SubconOrder::where('production_group', $report->production_group)->first();

        SubconJobLog::create([
            'order_id' => $order?->id,
            'order_number' => $order?->order_number ?? $report->production_group,
            'job_type' => "official_report:{$report->operation}",
            'status' => $status,
            'message' => $message,
        ]);
    }
}
