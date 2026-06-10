<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Resolves a subcon CMT purchase order to its garment production detail,
 * read from the (read-only) `vsm` database.
 *
 * The PO line itself only carries the generic "Item Jasa CMT" service item, so
 * the garment is reached through the PLM id on the line:
 *
 *   po_lines.PLMId  ->  plm_activity.ProductionGroup  ->  production_group_lines
 *
 * IMPORTANT: these tables do NOT share a consistent dataAreaId
 * (po/prg are stored under 'mpg' but plm_activity under 'mpr'/'mti'), so the
 * joins are on PLMId / ProductionGroup ONLY — never dataAreaId.
 */
class SubconProductionService
{
    /**
     * Production groups for a PO, each with article info and per-size lines.
     *
     * Returns [] when the PO has no resolvable PLM link or VSM is unreachable —
     * the caller treats production detail as a best-effort enrichment.
     *
     * @return array<int, array{
     *   plm_id: string, production_group: ?string, article_code: ?string,
     *   article_name: ?string, brand: ?string, colour: ?string,
     *   group_name: ?string, plm_status: ?string, total_qty: float,
     *   lines: array<int, object>
     * }>
     */
    public function forPo(string $poNumber): array
    {
        try {
            // 1. PLM ids on this PO's lines (skip the empty/blank service lines).
            $plmIds = DB::connection('vsm')->table('po_lines')
                ->where('PurchaseOrderNumber', $poNumber)
                ->whereNotNull('PLMId')
                ->where('PLMId', '!=', '')
                ->distinct()
                ->pluck('PLMId')
                ->all();

            if (empty($plmIds)) {
                return [];
            }

            // 2. PLM activity rows (article + production group + status).
            $activities = DB::connection('vsm')->table('plm_activity')
                ->whereIn('PLMId', $plmIds)
                ->get([
                    'PLMId', 'ProductionGroup', 'ArticleCode', 'ArticleName',
                    'Brand', 'Colour', 'GroupName', 'PLMActivityStatus',
                ]);

            if ($activities->isEmpty()) {
                return [];
            }

            // 3. PRG size lines for all referenced production groups, in one query.
            $groupIds = $activities->pluck('ProductionGroup')->filter()->unique()->all();
            $linesByGroup = empty($groupIds)
                ? collect()
                : DB::connection('vsm')->table('production_group_lines')
                    ->whereIn('ProductionGroup', $groupIds)
                    ->orderBy('ProductionGroup')
                    ->orderBy('LineNum')
                    ->get([
                        'ProductionGroup', 'ProdId', 'ItemId', 'Size', 'Qty',
                        'ProdStatus', 'InventSiteId', 'InventLocationId', 'SearchName',
                    ])
                    ->groupBy('ProductionGroup');

            return $activities->map(function ($a) use ($linesByGroup) {
                $lines = $linesByGroup->get($a->ProductionGroup, collect());

                return [
                    'plm_id' => $a->PLMId,
                    'production_group' => $a->ProductionGroup,
                    'article_code' => $a->ArticleCode,
                    'article_name' => $a->ArticleName,
                    'brand' => $a->Brand,
                    'colour' => $a->Colour,
                    'group_name' => $a->GroupName,
                    'plm_status' => $a->PLMActivityStatus,
                    'total_qty' => (float) $lines->sum('Qty'),
                    'lines' => $lines->all(),
                ];
            })->all();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * Condense the result of forPo() into a one-line summary for headers/lists.
     *
     * @param  array  $groups  output of forPo()
     * @return array{style: ?string, size_count: int, total_qty: float}
     */
    public function summarize(array $groups): array
    {
        $styles = [];
        $sizeCount = 0;
        $totalQty = 0.0;

        foreach ($groups as $g) {
            if (! empty($g['article_name'])) {
                $styles[] = $g['article_name'];
            }
            $sizeCount += count($g['lines']);
            $totalQty += $g['total_qty'];
        }

        return [
            'style' => empty($styles) ? null : implode(' / ', array_unique($styles)),
            'size_count' => $sizeCount,
            'total_qty' => $totalQty,
        ];
    }

    /**
     * Resolve garment style name(s) for many POs in one round-trip.
     *
     * Used by the sync command to persist a meaningful order title. Returns a
     * map of [po_number => "Style A / Style B"]; POs with no PLM link are absent.
     *
     * @param  array<int, string>  $poNumbers
     * @return array<string, string>
     */
    public function stylesForPos(array $poNumbers): array
    {
        if (empty($poNumbers)) {
            return [];
        }

        try {
            $lines = DB::connection('vsm')->table('po_lines')
                ->whereIn('PurchaseOrderNumber', $poNumbers)
                ->whereNotNull('PLMId')
                ->where('PLMId', '!=', '')
                ->get(['PurchaseOrderNumber', 'PLMId']);

            $plmIds = $lines->pluck('PLMId')->unique()->all();
            if (empty($plmIds)) {
                return [];
            }

            $nameByPlm = DB::connection('vsm')->table('plm_activity')
                ->whereIn('PLMId', $plmIds)
                ->whereNotNull('ArticleName')
                ->where('ArticleName', '!=', '')
                ->pluck('ArticleName', 'PLMId');

            $namesByPo = [];
            foreach ($lines as $l) {
                if ($name = $nameByPlm->get($l->PLMId)) {
                    $namesByPo[$l->PurchaseOrderNumber][] = $name;
                }
            }

            return array_map(
                fn ($names) => implode(' / ', array_unique($names)),
                $namesByPo
            );
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }
}
