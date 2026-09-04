<?php

namespace App\Services;

use App\Models\PoItem;
use App\Models\Setting;
use App\Models\ToleranceAmendmentRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Fabric PO lines that fall outside their ORIGINAL delivery tolerance — the
 * under/over-delivery % in effect before any tolerance amendment was
 * approved (falls back to the item's own stored value, then the system
 * default, when it was never amended).
 *
 * A PO line split into a partial-shipment chain (PoItem::splitToPartialShipment
 * suffixes the batch -P2/-P2-P2/...) is collapsed back into ONE logical line
 * before comparing delivered vs ordered — matching PoItem's own "logical
 * unit" grouping (po_id + item_number, see PoItem::getGlobalOrderedQuantity).
 * Otherwise a finished shipment fragment gets judged against the whole
 * group's target and reads as under-delivered even though it shipped exactly
 * what it was supposed to. A group still mid-shipment (a sibling still
 * pending/processing) is excluded entirely — it isn't finished yet.
 */
class FabricDeliveryToleranceService
{
    /**
     * @return Collection<int, object{
     *     po_number: ?string, vendor_name: ?string, style: string, item_number: ?string, unit: ?string,
     *     shipments: int, ordered: float, delivered: float,
     *     orig_underdelivery_pct: float, orig_overdelivery_pct: float,
     *     min_allowed: float, max_allowed: float, pct_vs_ordered: ?float,
     *     was_split_shipment: bool, tolerance_was_amended: bool, type: string,
     * }>
     */
    public function outsideOriginalToleranceRows(): Collection
    {
        $defaultUnder = (float) Setting::getValue('default_underdelivery', 3.00);
        $defaultOver = (float) Setting::getValue('default_overdelivery', 3.00);

        // Earliest 'tolerance' amendment per po_item_id -> its old_underdelivery/
        // old_overdelivery is the tolerance in effect BEFORE any amendment ever
        // touched that specific row.
        $firstAmendments = ToleranceAmendmentRequest::where('type', 'tolerance')
            ->orderBy('created_at')
            ->get()
            ->groupBy('po_item_id')
            ->map(fn ($g) => $g->first());

        $items = PoItem::with(['purchaseOrder.vendor'])
            ->whereHas('purchaseOrder.vendor', fn ($q) => $q->where('type', 'fabric'))
            ->get();

        $rows = collect();

        foreach ($items->groupBy(fn ($i) => $i->po_id.'|'.$i->item_number) as $group) {
            if ($group->contains(fn ($i) => $i->status !== 'completed')) {
                continue;
            }

            $first = $group->sortBy('created_at')->first();
            $firstAmend = $firstAmendments->get($first->id);

            if ($firstAmend) {
                $origUnder = (float) $firstAmend->old_underdelivery;
                $origOver = (float) $firstAmend->old_overdelivery;
            } else {
                $origUnder = (float) $first->underdelivery;
                $origOver = (float) $first->overdelivery;
            }

            $effUnder = $origUnder > 0 ? $origUnder : $defaultUnder;
            $effOver = $origOver > 0 ? $origOver : $defaultOver;

            $target = (float) $group->sum('quantity');
            $delivered = (float) DB::table('rolls')
                ->whereIn('item_id', $group->pluck('id'))
                ->sum(DB::raw(PoItem::ROLL_QUANTITY_SQL));

            $min = max(0, $target * (1 - $effUnder / 100));
            $max = max(0, $target * (1 + $effOver / 100));

            if ($delivered >= $min && $delivered <= $max) {
                continue;
            }

            $wasAmended = $group->contains(fn ($i) => $firstAmendments->has($i->id));

            $rows->push((object) [
                'po_number' => $first->purchaseOrder->po_number ?? null,
                'vendor_name' => $first->purchaseOrder->vendor->name ?? null,
                'style' => preg_replace('/(-P\d+)+$/', '', $first->batch),
                'item_number' => $first->item_number,
                'unit' => $first->unit,
                'shipments' => $group->count(),
                'ordered' => $target,
                'delivered' => $delivered,
                'orig_underdelivery_pct' => $effUnder,
                'orig_overdelivery_pct' => $effOver,
                'min_allowed' => round($min, 2),
                'max_allowed' => round($max, 2),
                'pct_vs_ordered' => $target > 0 ? round((($delivered - $target) / $target) * 100, 2) : null,
                'was_split_shipment' => $group->count() > 1,
                'tolerance_was_amended' => $wasAmended,
                'type' => $delivered < $min ? 'UNDER' : 'OVER',
            ]);
        }

        return $rows->sortByDesc(fn ($r) => abs($r->pct_vs_ordered ?? 0))->values();
    }
}
