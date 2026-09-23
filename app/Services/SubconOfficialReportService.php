<?php

namespace App\Services;

use App\Models\SubconAddProcessReport;
use App\Models\SubconOfficialReport;
use App\Models\SubconOrder;

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
            $groups = $this->production->forProductionGroup($productionGroup);
            $plmId = collect($groups)->pluck('plm_id')->filter()->first();
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
            ]
        );
    }
}
