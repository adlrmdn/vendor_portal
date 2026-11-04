<?php

namespace App\Services;

use App\Models\SubconFabricReconciliation;
use App\Models\SubconOrder;

/**
 * Persists the per-fabric consumption the subcon admin enters at the cutting
 * gate and derives the figures the QMS console later reads. Matches the agreed
 * spreadsheet schema (B=fabric_sent, C=consumption_plan, E=total qty cut,
 * G=short_roll, H=sisa_kain, I=kepala_kain, J=retur_kain, K=shortage):
 *   cutt_plan          = ROUNDDOWN((B − (J + K)) / C, 0)  ← ONLY retur_kain + shortage are subtracted
 *   actual_consumption = (B − (J + K)) / E                ← ONLY retur_kain + shortage are subtracted
 *   overconsumption    = (actual_consumption − C) / C  (ratio; 0.0271 = 2.71%)
 *   deduction (IDR)    = max(0, actual_consumption − 1.03 × C) × E × fabric_price
 *                        charged only when overconsumption > 3.00% (the 3% tolerance
 *                        is subtracted; under-consuming is never charged)
 *
 * fabric_sent (B) is pinned to the confirmed D365 Goods Receive quantity for
 * any VSM-linked fabric — never a typed figure for those; only a manually
 * added fabric (no PO to receive against) keeps a submitted value.
 *
 * fabric_price is prefilled from the VSM fabric PO but editable here.
 *
 * Upserts by (order_id, label). The waste columns (short_roll/sisa_kain/
 * kepala_kain/retur_kain) are vendor-entered on the cutting report, but the
 * approver can override them here — when a waste value is submitted it wins,
 * otherwise the already-stored reconciliation value is preserved. shortage
 * (Selisih/Shortage) is approver-only — there is no vendor-side input for it —
 * but follows the exact same override-or-keep-stored rule.
 *
 * Transaction-agnostic on purpose: callers wrap this together with the stage
 * transition so "save consumption" and "approve" commit as one atomic action.
 */
class SubconConsumptionService
{
    public function __construct(private SubconProductionService $production) {}

    /** Overconsumption tolerance: no deduction is charged up to +3%. */
    private const TOLERANCE = 0.03;

    /**
     * @param  array<int, array<string, mixed>>  $fabrics  rows of ['label'=>, 'fabric_sent'=>?, 'consumption_plan'=>?, 'fabric_price'=>?]
     */
    public function persist(SubconOrder $order, array $fabrics): void
    {
        $totalCut = $this->production->totalCutForOrder($order);

        // Fabric Sent is pinned to the confirmed D365 Goods Receive quantity for
        // any fabric linked from VSM (i.e. with a real PO to receive against) —
        // no longer a typed figure, so a mistyped number can't skew the deduction
        // math. Only a manually-added fabric (no VSM/PO link — the "Add fabric"
        // row) has no Goods Receive to pull, so it keeps whatever is submitted.
        $goodsReceiveByLabel = [];
        try {
            $vsmLines = $this->production->fabricLinesForPo($order->order_number);
            $goodsReceiveByLabel = $this->production->resolveGoodsReceipts($vsmLines);
        } catch (\Throwable $e) {
            report($e);
        }

        foreach ($fabrics as $f) {
            $label = trim((string) ($f['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $existing = SubconFabricReconciliation::where('order_id', $order->id)
                ->where('label', $label)
                ->first();

            // Waste columns: the approver may override the vendor-entered values on
            // the approval form. A submitted value wins; otherwise keep what's stored.
            $waste = [];
            foreach (['short_roll', 'sisa_kain', 'kepala_kain', 'retur_kain', 'shortage'] as $col) {
                $submitted = $f[$col] ?? null;
                $waste[$col] = ($submitted === null || $submitted === '')
                    ? ($existing ? (float) $existing->{$col} : 0.0)
                    : round((float) $submitted, 2);
            }

            // Waste subtracted from fabric sent: retur_kain + shortage. The other
            // three columns (short_roll/sisa_kain/kepala_kain) are still captured
            // and stored for reference, but no longer reduce actual consumption.
            $wasteTotal = $waste['retur_kain'] + $waste['shortage'];

            $goodsReceive = $goodsReceiveByLabel[$label]['qty'] ?? null;
            $fabricSent = $goodsReceive !== null
                ? round((float) $goodsReceive, 2)
                : (($f['fabric_sent'] ?? null) === null || $f['fabric_sent'] === ''
                    ? null : round((float) $f['fabric_sent'], 2));
            $consumptionPlan = ($f['consumption_plan'] ?? null) === null || $f['consumption_plan'] === ''
                ? null : round((float) $f['consumption_plan'], 4);
            $fabricPrice = ($f['fabric_price'] ?? null) === null || $f['fabric_price'] === ''
                ? null : round((float) $f['fabric_price'], 2);
            $cuttPlan = ($fabricSent !== null && $consumptionPlan !== null && $consumptionPlan > 0)
                ? (int) floor(($fabricSent - $wasteTotal) / $consumptionPlan) : null;
            $actualConsumption = ($fabricSent !== null && $totalCut > 0)
                ? round(($fabricSent - $wasteTotal) / $totalCut, 4) : null;
            $overconsumption = ($actualConsumption !== null && $consumptionPlan !== null && $consumptionPlan > 0)
                ? round(($actualConsumption - $consumptionPlan) / $consumptionPlan, 4) : null;

            // Deduction (IDR): charge the overuse beyond the 3% tolerance only.
            // excess/piece = actual − (plan × 1.03); ≤ 0 means within tolerance → no charge.
            $deduction = null;
            if ($actualConsumption !== null && $consumptionPlan !== null && $fabricPrice !== null && $totalCut > 0) {
                $excessPerPiece = $actualConsumption - ($consumptionPlan * (1 + self::TOLERANCE));
                $deduction = $excessPerPiece > 0
                    ? round($excessPerPiece * $totalCut * $fabricPrice, 2)
                    : 0.0;
            }

            SubconFabricReconciliation::updateOrCreate(
                ['order_id' => $order->id, 'label' => $label],
                [
                    'short_roll' => $waste['short_roll'],
                    'sisa_kain' => $waste['sisa_kain'],
                    'kepala_kain' => $waste['kepala_kain'],
                    'retur_kain' => $waste['retur_kain'],
                    'shortage' => $waste['shortage'],
                    'fabric_sent' => $fabricSent,
                    'consumption_plan' => $consumptionPlan,
                    'cutt_plan' => $cuttPlan,
                    'actual_consumption' => $actualConsumption,
                    'overconsumption' => $overconsumption,
                    'fabric_price' => $fabricPrice,
                    'deduction' => $deduction,
                ]
            );
        }
    }
}
