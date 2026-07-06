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
                    'Season', 'World', 'Department', 'Category', 'SubCategory',
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

                $sortedLines = $lines->sortBy(function ($line) {
                    return $this->sizeToRank($line->Size ?? '');
                })->values();

                return [
                    'plm_id' => $a->PLMId,
                    'production_group' => $a->ProductionGroup,
                    'article_code' => $a->ArticleCode,
                    'article_name' => $a->ArticleName,
                    'brand' => $a->Brand,
                    'colour' => $a->Colour,
                    'group_name' => $a->GroupName,
                    'plm_status' => $a->PLMActivityStatus,
                    'season' => $a->Season,
                    'world' => $a->World,
                    'department' => $a->Department,
                    'category' => $a->Category,
                    'subcategory' => $a->SubCategory,
                    'total_qty' => (float) $sortedLines->sum('Qty'),
                    'lines' => $sortedLines->all(),
                ];
            })->all();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * VSM purchase-pool prefix that identifies fabric (garment material) POs.
     * Matches ALL fabric pools — Fab-Local / Fab-Import / Fab-Repro and any
     * future Fab-* pool — while excluding Acc-*, CMT, AddPro, etc.
     */
    private const FABRIC_POOL_PREFIX = 'Fab-';

    /**
     * Linked fabric PO lines for a CMT PO, combined per fabric (description +
     * unit). Reached via the shared PLMId: the CMT PO's lines carry the PLM, and
     * the fabric POs bought for that style share it (all Fab-* pools). Quantities for the same
     * fabric (identical description + unit) are summed; the original metric is
     * kept and folded into the label.
     *
     * Best-effort — returns [] if VSM is unreachable or there is no PLM link.
     *
     * @return array<int, array{label:string, description:string, unit:?string, fabric_sent:float, fabric_price:?float}>
     */
    public function fabricLinesForPo(string $poNumber): array
    {
        try {
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

            $lines = DB::connection('vsm')->table('po_lines as l')
                ->join('po_headers as h', 'h.PurchaseOrderNumber', '=', 'l.PurchaseOrderNumber')
                ->whereIn('l.PLMId', $plmIds)
                ->where('h.PurchPoolId', 'like', self::FABRIC_POOL_PREFIX.'%')
                ->get(['l.LineDescription', 'l.PurchaseUnitSymbol', 'l.OrderedPurchaseQuantity', 'l.LineAmount', 'l.PurchaseOrderNumber', 'l.ItemNumber']);

            // Combine by (description + unit): same fabric in the same metric.
            // total_amount is accumulated so a weighted unit price can be derived;
            // the source PO + item numbers are kept so a real price + currency can
            // be resolved (local po_items first, then direct D365).
            $grouped = [];
            foreach ($lines as $ln) {
                $desc = trim((string) ($ln->LineDescription ?? ''));
                $unit = trim((string) ($ln->PurchaseUnitSymbol ?? ''));
                $key = $desc.'|'.$unit;
                if (! isset($grouped[$key])) {
                    $grouped[$key] = ['description' => $desc, 'unit' => $unit ?: null, 'fabric_sent' => 0.0, 'total_amount' => 0.0, 'po_numbers' => [], 'item_numbers' => []];
                }
                $grouped[$key]['fabric_sent'] += (float) ($ln->OrderedPurchaseQuantity ?? 0);
                $grouped[$key]['total_amount'] += (float) ($ln->LineAmount ?? 0);
                if (! empty($ln->PurchaseOrderNumber)) {
                    $grouped[$key]['po_numbers'][$ln->PurchaseOrderNumber] = true;
                }
                if (! empty($ln->ItemNumber)) {
                    $grouped[$key]['item_numbers'][$ln->ItemNumber] = true;
                }
            }

            return array_values(array_map(function ($g) {
                // Weighted unit price = Σ LineAmount / Σ ordered qty — a VSM-only
                // fallback (no currency); the resolver prefers po_items / D365.
                $g['fabric_price'] = $g['fabric_sent'] > 0 ? round($g['total_amount'] / $g['fabric_sent'], 2) : null;
                unset($g['total_amount']);
                $g['fabric_sent'] = round($g['fabric_sent'], 2);
                $g['po_numbers'] = array_keys($g['po_numbers']);
                $g['item_numbers'] = array_keys($g['item_numbers']);
                $g['label'] = $g['description'].($g['unit'] ? ' ('.$g['unit'].')' : '');

                return $g;
            }, $grouped));
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * Resolve a real unit price + currency per fabric line. Local `po_items`
     * (already synced by the fabric portal, carries unit_price + PO currency) is
     * checked first — fast, no external call — and only the fabrics with no local
     * match trigger a direct, best-effort D365 fetch. Falls back to the VSM-derived
     * price (no currency) when neither has it.
     *
     * @param  array<int, array<string, mixed>>  $vsmLines  from fabricLinesForPo()
     * @return array<string, array{price: ?float, currency: ?string}> keyed by label
     */
    public function resolveFabricPricing(array $vsmLines): array
    {
        $allItems = [];
        $allPos = [];
        foreach ($vsmLines as $l) {
            foreach (($l['item_numbers'] ?? []) as $it) {
                $allItems[$it] = true;
            }
            foreach (($l['po_numbers'] ?? []) as $p) {
                $allPos[$p] = true;
            }
        }

        // 1. Local po_items (default connection) — item_number → price + currency.
        $local = [];
        if (! empty($allItems)) {
            try {
                $rows = DB::table('po_items as pi')
                    ->join('purchase_orders as po', 'po.id', '=', 'pi.po_id')
                    ->whereIn('pi.item_number', array_keys($allItems))
                    ->when(! empty($allPos), fn ($q) => $q->whereIn('po.po_number', array_keys($allPos)))
                    ->get(['pi.item_number', 'pi.total_price', 'pi.quantity', 'po.currency']);
                foreach ($rows as $r) {
                    // Price PER UNIT metric = line total ÷ ordered qty. (unit_price is
                    // D365 PurchasePrice, quoted per a price-unit basis, so it over-states.)
                    $qty = (float) $r->quantity;
                    if (! isset($local[$r->item_number]) && $qty > 0 && $r->total_price !== null) {
                        $local[$r->item_number] = ['price' => round((float) $r->total_price / $qty, 2), 'currency' => $r->currency];
                    }
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $result = [];
        $pending = [];
        $pendingPos = [];
        foreach ($vsmLines as $l) {
            $hit = null;
            foreach (($l['item_numbers'] ?? []) as $it) {
                if (isset($local[$it])) {
                    $hit = $local[$it];
                    break;
                }
            }
            if ($hit !== null) {
                $result[$l['label']] = $hit;
            } else {
                $pending[$l['label']] = $l;
                foreach (($l['po_numbers'] ?? []) as $p) {
                    $pendingPos[$p] = true;
                }
            }
        }

        // 2. Direct D365 for whatever isn't local — best-effort, cached.
        $d365 = [];
        if (! empty($pending) && ! empty($pendingPos)) {
            try {
                $d365 = app(\App\Services\D365JobTransactionService::class)->fetchFabricPricing(array_keys($pendingPos));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        foreach ($pending as $label => $l) {
            $price = null;
            $currency = null;
            foreach (($l['po_numbers'] ?? []) as $p) {
                if (! isset($d365[$p])) {
                    continue;
                }
                foreach (($l['item_numbers'] ?? []) as $it) {
                    if (isset($d365[$p]['items'][$it])) {
                        $price = $d365[$p]['items'][$it];
                        $currency = $d365[$p]['currency'] ?? null;
                        break 2;
                    }
                }
            }
            // 3. Final fallback: VSM-derived price (no currency).
            if ($price === null) {
                $price = $l['fabric_price'] ?? null;
            }
            $result[$label] = ['price' => $price, 'currency' => $currency];
        }

        return $result;
    }

    /**
     * Per-fabric lines for an order, enriched with locally-stored reconciliation
     * AND consumption (fabric_sent / consumption_plan / cutt_plan /
     * actual_consumption / overconsumption). The fabric set is the union of
     * VSM-linked fabrics and any reconciliation rows already saved (so
     * vendor-added fabrics persist). Consumption fields are null until the admin
     * fills them at cutting approval. See SubconConsumptionService for the formulas.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fabricLinesWithData(\App\Models\SubconOrder $order): array
    {
        $recon = \App\Models\SubconFabricReconciliation::where('order_id', $order->id)->get()->keyBy('label');

        // VSM fabric lines, keyed by label — used to resolve a real price + currency.
        $vsm = [];
        foreach ($this->fabricLinesForPo($order->order_number) as $fl) {
            $vsm[$fl['label']] = $fl;
        }

        // Real unit price + currency (local po_items first, then direct D365).
        $pricing = $this->resolveFabricPricing(array_values($vsm));

        $labels = array_keys($vsm + $recon->all());

        $lines = [];
        foreach ($labels as $label) {
            $r = $recon->get($label);
            $priced = $pricing[$label] ?? ['price' => ($vsm[$label]['fabric_price'] ?? null), 'currency' => null];

            // Price: admin-saved value wins; otherwise prefill from the resolved source.
            $fabricPrice = $r && $r->fabric_price !== null
                ? (float) $r->fabric_price
                : ($priced['price'] ?? null);

            $lines[] = [
                'label' => $label,
                'short_roll' => $r ? (float) $r->short_roll : 0,
                'sisa_kain' => $r ? (float) $r->sisa_kain : 0,
                'kepala_kain' => $r ? (float) $r->kepala_kain : 0,
                'retur_kain' => $r ? (float) $r->retur_kain : 0,
                'fabric_sent' => $r && $r->fabric_sent !== null ? (float) $r->fabric_sent : null,
                'consumption_plan' => $r && $r->consumption_plan !== null ? (float) $r->consumption_plan : null,
                'cutt_plan' => $r && $r->cutt_plan !== null ? (int) $r->cutt_plan : null,
                'actual_consumption' => $r && $r->actual_consumption !== null ? (float) $r->actual_consumption : null,
                'overconsumption' => $r && $r->overconsumption !== null ? (float) $r->overconsumption : null,
                'fabric_price' => $fabricPrice,
                'fabric_currency' => $priced['currency'] ?? null,
                'deduction' => $r && $r->deduction !== null ? (float) $r->deduction : null,
            ];
        }

        return $lines;
    }

    /** Total cutting quantity entered for an order (basis for actual_consumption). */
    public function totalCutForOrder(\App\Models\SubconOrder $order): int
    {
        return (int) \App\Models\SubconCuttingReport::where('order_id', $order->id)->sum('cutting_qty');
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
        $sizes = [];
        $totalQty = 0.0;

        foreach ($groups as $g) {
            if (! empty($g['article_name'])) {
                $styles[] = $g['article_name'];
            }
            if (! empty($g['lines'])) {
                foreach ($g['lines'] as $line) {
                    if ($line->Size !== null && $line->Size !== '') {
                        $sizes[] = $line->Size;
                    }
                }
            }
            $totalQty += $g['total_qty'];
        }

        return [
            'style' => empty($styles) ? null : implode(' / ', array_unique($styles)),
            'size_count' => count(array_unique($sizes)),
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

    /**
     * Helper to map a size string to a numeric rank for sorting.
     */
    private function sizeToRank(string $size): float
    {
        $s = strtoupper(trim($size));
        if ($s === '') {
            return 999;
        }

        // 1. Check for standard sizes or explicit mappings
        $ranks = [
            'XS' => 10,
            'S' => 20,
            'M' => 30,
            'L' => 40,
            'XL' => 50,
            'XXL' => 60,
        ];

        if (isset($ranks[$s])) {
            return $ranks[$s];
        }

        // 2. Alphabetic patterns:
        // e.g. XXXS, XXS (smaller than XS)
        if (preg_match('/^(X+)S$/', $s, $matches)) {
            $xCount = strlen($matches[1]);

            return 11 - $xCount;
        }
        if (preg_match('/^(\d+)XS$/', $s, $matches)) {
            $xCount = (int) $matches[1];

            return 11 - $xCount;
        }

        // e.g. XXL/2XL, XXXL/3XL, 4XL, 5XL, 6XL, 3L
        if (preg_match('/^(X+)L$/', $s, $matches)) {
            $xCount = strlen($matches[1]);

            return 40 + ($xCount * 10);
        }
        if (preg_match('/^(\d+)XL$/', $s, $matches)) {
            $xCount = (int) $matches[1];

            return 40 + ($xCount * 10);
        }
        if (preg_match('/^(\d+)L$/', $s, $matches)) {
            $xCount = (int) $matches[1];

            return 40 + ($xCount * 10);
        }

        // Mixed sizes like S/M, L/XL
        if (str_contains($s, '/')) {
            $parts = explode('/', $s);

            return ($this->sizeToRank($parts[0]) + $this->sizeToRank($parts[1])) / 2;
        }

        // 3. Numeric collar/waist sizes or dress sizes:
        // E.g., "14.5", "8 F", "29"
        if (preg_match('/^\s*(\d+(?:\.\d+)?)/', $s, $matches)) {
            return (float) $matches[1];
        }

        return 1000 + ord($s[0]);
    }
}
