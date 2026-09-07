<?php

namespace App\Services;

use App\Models\SubconOrder;
use Illuminate\Support\Collection;

/**
 * Marker/fabric-ratio cutting-plan calculations — the web version of the
 * team's Excel "CUTTING PLAN" worksheet: per fabric/marker type, a cascading
 * sequence of ratio groups (A, B, C, ...) whittles the order qty per size
 * down via successive marker layouts, and the fabric each layout consumes is
 * totalled and checked against what's actually available.
 *
 * Pure calculation — no persistence — so it's reusable wherever these
 * figures are needed later (e.g. feeding the work order's Cut Plan column).
 */
class SubconCuttingPlanService
{
    /** 1 inch of marker-end allowance, expressed in yards (matches the sheet). */
    private const ALLW_MARKER = 1 / 36;

    public function __construct(private SubconProductionService $production)
    {
    }

    /**
     * Order qty per size for a subcon order, keyed by size code, in the same
     * order production-detail.blade.php already trusts. One style per
     * cutting plan — takes the first production group (matches the Excel's
     * one-file-per-style assumption).
     *
     * @return array<string, int>
     */
    public function sizesForOrder(SubconOrder $order): array
    {
        $groups = $this->production->forPo($order->order_number);
        $lines = $groups[0]['lines'] ?? [];

        $sizes = [];
        foreach ($lines as $line) {
            $size = $line->Size ?: '—';
            $sizes[$size] = ($sizes[$size] ?? 0) + (int) $line->Qty;
        }

        return $sizes;
    }

    /**
     * Fabric lines (label => data, incl. goods_receive/fabric_sent) for the
     * order, for matching a block's marker_type against known fabric and
     * for the marker-type suggestion list in the view.
     */
    public function fabricLinesByLabel(SubconOrder $order): array
    {
        return collect($this->production->fabricLinesWithData($order))->keyBy('label')->all();
    }

    /**
     * Computes one block's full cascade + totals + est. cuttable qty.
     *
     * @param  array{marker_type:string, tolerance_pct:float, kg_per_pc:?float, fabric_available_override:?float, groups: array<int, array{jml_layer:?float, marker_length:?float, rasio: array<string,float>}>}  $block
     * @param  array<string,int>  $orderQtyBySize
     * @param  array<string,mixed>  $fabricLinesByLabel
     * @return array{groups: array, totals: array, estQtyCuttPlan: ?int, fabricAvailableYds: ?float, fabricAvailableSource: ?string}
     */
    public function computeBlock(array $block, array $orderQtyBySize, array $fabricLinesByLabel = []): array
    {
        $sizes = array_keys($orderQtyBySize);
        $remaining = $orderQtyBySize;

        $groups = [];
        $totalQty = 0;
        $totalFabricYds = 0.0;

        foreach (($block['groups'] ?? []) as $g) {
            $jmlLayer = (float) ($g['jml_layer'] ?? 0);
            $markerLength = (float) ($g['marker_length'] ?? 0);
            $rasio = $g['rasio'] ?? [];

            $qtyMarker = [];
            $sisaMarker = [];
            $qtyMarkerTotal = 0;

            foreach ($sizes as $size) {
                $qty = (float) ($rasio[$size] ?? 0) * $jmlLayer;
                $qtyMarker[$size] = $qty;
                $qtyMarkerTotal += $qty;
                $sisaMarker[$size] = ($remaining[$size] ?? 0) - $qty;
            }

            $kebFabric = $jmlLayer * ($markerLength + self::ALLW_MARKER);

            $groups[] = [
                'jml_layer' => $jmlLayer,
                'marker_length' => $markerLength,
                'rasio' => $rasio,
                'qty_marker' => $qtyMarker,
                'qty_marker_total' => $qtyMarkerTotal,
                'sisa_marker' => $sisaMarker,
                'keb_fabric' => $kebFabric,
            ];

            $totalQty += $qtyMarkerTotal;
            $totalFabricYds += $kebFabric;
            $remaining = $sisaMarker; // cascades into the next group
        }

        $kgPerPc = isset($block['kg_per_pc']) && $block['kg_per_pc'] !== null ? (float) $block['kg_per_pc'] : null;
        // tolerance_pct is stored/entered as a percentage number (5 = 5%), not a fraction.
        $tolerancePct = (float) ($block['tolerance_pct'] ?? 0);
        $avgConsYdsPerPc = $totalQty > 0 ? $totalFabricYds / $totalQty : null;

        $totals = [
            'total_qty' => $totalQty,
            'total_fabric_yds' => $totalFabricYds,
            'total_fabric_kg' => $kgPerPc !== null ? $totalQty * $kgPerPc : null,
            'avg_cons_yds_per_pc' => $avgConsYdsPerPc,
            'tol_adjusted_yds' => $totalFabricYds * (1 + $tolerancePct / 100),
        ];

        [$fabricAvailableYds, $fabricAvailableSource] = $this->resolveFabricAvailable($block, $fabricLinesByLabel);

        $estQtyCuttPlan = ($fabricAvailableYds !== null && $avgConsYdsPerPc !== null && $avgConsYdsPerPc > 0)
            ? (int) floor($fabricAvailableYds / $avgConsYdsPerPc)
            : null;

        return [
            'groups' => $groups,
            'totals' => $totals,
            'estQtyCuttPlan' => $estQtyCuttPlan,
            'fabricAvailableYds' => $fabricAvailableYds,
            'fabricAvailableSource' => $fabricAvailableSource,
        ];
    }

    /**
     * Fabric available, in yards, for the "est qty cutt plan" estimate.
     * Precedence: admin override > D365 goods-receive (already yards,
     * converted from metres by SubconProductionService) > vendor-entered
     * fabric_sent. Null (and a null source) if none of the three exist yet.
     *
     * @return array{0: ?float, 1: ?string}
     */
    private function resolveFabricAvailable(array $block, array $fabricLinesByLabel): array
    {
        if (isset($block['fabric_available_override']) && $block['fabric_available_override'] !== null && $block['fabric_available_override'] !== '') {
            return [(float) $block['fabric_available_override'], 'override'];
        }

        $fabricLine = $fabricLinesByLabel[trim((string) ($block['marker_type'] ?? ''))] ?? null;
        if (! $fabricLine) {
            return [null, null];
        }

        if (($fabricLine['goods_receive'] ?? null) !== null) {
            return [(float) $fabricLine['goods_receive'], 'goods_receive'];
        }

        if (($fabricLine['fabric_sent'] ?? null) !== null) {
            return [(float) $fabricLine['fabric_sent'], 'fabric_sent'];
        }

        return [null, null];
    }
}
