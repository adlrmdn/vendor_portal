<?php

namespace App\Services;

use App\Models\SubconAddProcessReport;

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
}
