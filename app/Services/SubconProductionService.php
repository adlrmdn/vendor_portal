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

            $hasGroup = false;
            if (!empty($plmIds)) {
                // 2. PLM activity rows (article + production group + status).
                $activities = DB::connection('vsm')->table('plm_activity')
                    ->whereIn('PLMId', $plmIds)
                    ->get([
                        'PLMId', 'ProductionGroup', 'ArticleCode', 'ArticleName',
                        'Brand', 'Colour', 'GroupName', 'PLMActivityStatus',
                        'Season', 'World', 'Department', 'Category', 'SubCategory',
                    ]);

                if ($activities->isNotEmpty()) {
                    $hasGroup = true;
                }
            }

            if ($hasGroup) {
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
            }

            // Fallback: look up the production_group from subcon_orders locally
            // (only resolves for a PO already synced — see forProductionGroup()
            // for the PLM-less path that also works on first creation).
            $localOrder = DB::table('subcon_orders')->where('order_number', $poNumber)->first();
            $fallbackGroup = $localOrder ? $localOrder->production_group : null;
            if ($fallbackGroup) {
                $group = $this->forProductionGroup($fallbackGroup);
                if (! empty($group)) {
                    return $group;
                }
            }

            return [];
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * Same shape as one forPo() entry, but resolved directly from a known
     * production group ID instead of via the PO's PLM link. Standalone CMT
     * production groups (no PLM) have no other path to their size lines —
     * forPo() only reaches this via a local subcon_orders lookup, which is
     * empty for a PO not yet synced. The sync command calls this directly
     * with D365's TOC_ProductionGroup header field so title/sizes_count are
     * correct on first creation, not just on the next sync pass.
     *
     * @return array<int, array{
     *   plm_id: string, production_group: ?string, article_code: ?string,
     *   article_name: ?string, brand: ?string, colour: ?string,
     *   group_name: ?string, plm_status: ?string, total_qty: float,
     *   lines: array<int, object>
     * }>
     */
    public function forProductionGroup(string $productionGroup): array
    {
        try {
            $lines = DB::connection('vsm')->table('production_group_lines')
                ->where('ProductionGroup', $productionGroup)
                ->orderBy('LineNum')
                ->get([
                    'ProductionGroup', 'ProdId', 'ItemId', 'Size', 'Qty',
                    'ProdStatus', 'InventSiteId', 'InventLocationId', 'SearchName',
                ]);

            if ($lines->isEmpty()) {
                return [];
            }

            $firstLine = $lines->first();
            $sortedLines = $lines->sortBy(function ($line) {
                return $this->sizeToRank($line->Size ?? '');
            })->values();

            return [
                [
                    'plm_id' => '',
                    'production_group' => $productionGroup,
                    'article_code' => $firstLine->ItemId,
                    'article_name' => $firstLine->SearchName,
                    'brand' => null,
                    'colour' => null,
                    'group_name' => null,
                    'plm_status' => null,
                    'season' => null,
                    'world' => null,
                    'department' => null,
                    'category' => null,
                    'subcategory' => null,
                    'total_qty' => (float) $sortedLines->sum('Qty'),
                    'lines' => $sortedLines->all(),
                ],
            ];
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
     * Trim/accessory keywords seen on Fab-* pool lines that are NOT fabric
     * (e.g. a hangtag bought in KG instead of PCS) — the PCS-unit skip below
     * doesn't catch these since they're ordered by weight/length, not piece
     * count. Matched case-insensitively against LineDescription.
     */
    private const NON_FABRIC_KEYWORDS = ['HANGTAG', 'LABEL', 'STICKER', 'POLYBAG'];

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
                // PCS lines on fabric POs are trims/accessories, not fabric —
                // they have no consumption to reconcile.
                if (strcasecmp($unit, 'PCS') === 0) {
                    continue;
                }
                // Some trims (e.g. hangtags) are bought by weight/length instead
                // of PCS and would otherwise slip through the check above.
                foreach (self::NON_FABRIC_KEYWORDS as $keyword) {
                    if (stripos($desc, $keyword) !== false) {
                        continue 2;
                    }
                }
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

            // Item-master enrichment (Inventory Group) — a single batched, best-effort
            // D365 call for every item number across all grouped fabrics. Wrapped in
            // its own try/catch so a D365 hiccup never drops the VSM-derived fabric
            // lines themselves (unlike fabric_sent/price, this has no local fallback).
            $allItemNumbers = [];
            foreach ($grouped as $g) {
                foreach ($g['item_numbers'] as $it) {
                    $allItemNumbers[$it] = true;
                }
            }
            $itemMaster = [];
            if (! empty($allItemNumbers)) {
                try {
                    $itemMaster = app(\App\Services\D365JobTransactionService::class)->fetchItemMaster(array_keys($allItemNumbers));
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            return array_values(array_map(function ($g) use ($itemMaster) {
                // Weighted unit price = Σ LineAmount / Σ ordered qty — a VSM-only
                // fallback (no currency); the resolver prefers po_items / D365.
                $g['fabric_price'] = $g['fabric_sent'] > 0 ? round($g['total_amount'] / $g['fabric_sent'], 2) : null;
                unset($g['total_amount']);
                $g['fabric_sent'] = round($g['fabric_sent'], 2);
                $g['po_numbers'] = array_keys($g['po_numbers']);
                $g['item_numbers'] = array_keys($g['item_numbers']);
                $g['label'] = $g['description'].($g['unit'] ? ' ('.$g['unit'].')' : '');
                // Display unit: length fabric (M/YD) is always entered/converted
                // to YARDS regardless of the PO's raw unit — see resolveGoodsReceipts().
                $g['display_unit'] = self::displayUnit($g['label']);
                $g['item_number'] = implode(', ', $g['item_numbers']);
                $g['inventory_group'] = null;
                foreach ($g['item_numbers'] as $it) {
                    if (! empty($itemMaster[$it]['inventory_group'])) {
                        $g['inventory_group'] = $itemMaster[$it]['inventory_group'];
                        break;
                    }
                }

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

        // Policy markup: every resolved fabric price (local po_items, direct D365,
        // or the VSM fallback alike) is marked up 30% before it reaches the
        // consumption/deduction calc. Applied here — the single funnel all three
        // sources pass through — so it can't be missed on any one source.
        foreach ($result as $label => $r) {
            if ($r['price'] !== null) {
                $result[$label]['price'] = round($r['price'] * self::FABRIC_PRICE_MARKUP, 2);
            }
        }

        return $result;
    }

    /** Policy markup applied to every resolved fabric price (see resolveFabricPricing()). */
    private const FABRIC_PRICE_MARKUP = 1.30;

    /** 1 meter = this many yards — the only length conversion Goods Receive needs. */
    private const METER_TO_YARD = 1.0936133;

    /**
     * The unit a fabric line's figures are actually recorded in — for display
     * only. D365 quotes length fabric in M or YD depending on the PO, but Fabric
     * Sent / the waste columns / Goods Receive are always entered/converted to
     * YARDS regardless (see resolveGoodsReceipts() above); only KG (weight)
     * fabric keeps its own unit. The label's trailing "(UNIT)" — built from the
     * raw D365 PurchaseUnitSymbol — is the only place that unit survives once a
     * fabric line reaches display code, so it's parsed back out here.
     */
    public static function displayUnit(?string $label): ?string
    {
        if (! $label || ! preg_match('/\(([A-Za-z0-9]+)\)\s*$/', $label, $m)) {
            return null;
        }

        return strcasecmp($m[1], 'KG') === 0 ? 'KG' : 'YD';
    }

    /**
     * Actual goods-received quantity per fabric line, direct from D365 (the real
     * receipt documents — see D365JobTransactionService::fetchGoodsReceipts()).
     * There's no local mirror for this (unlike fabric pricing, which po_items
     * carries), so it's always a direct, cached, best-effort D365 call keyed by
     * the fabric's linked PO numbers.
     *
     * D365/VSM record quantity in whatever unit the fabric was procured in
     * (PurchaseUnitSymbol — M or YD for length fabrics, KG for weight), but the
     * cutting floor always works — and the vendor always enters Fabric Sent and
     * every waste column — in YARDS regardless of the PO's unit. Left unconverted,
     * a fabric bought in meters shows a Goods Receive that looks ~9% short of
     * Fabric Sent even when everything was actually received. So a length fabric
     * quoted in meters is converted to yards here; KG (weight) and already-YD
     * fabrics pass through unchanged.
     *
     * @param  array<int, array<string, mixed>>  $vsmLines  from fabricLinesForPo()
     * @return array<string, array{qty: ?float, date: ?string}> keyed by label
     */
    public function resolveGoodsReceipts(array $vsmLines): array
    {
        $allPos = [];
        foreach ($vsmLines as $l) {
            foreach (($l['po_numbers'] ?? []) as $p) {
                $allPos[$p] = true;
            }
        }

        if (empty($allPos)) {
            return [];
        }

        $receipts = [];
        try {
            $receipts = app(\App\Services\D365JobTransactionService::class)->fetchGoodsReceipts(array_keys($allPos));
        } catch (\Throwable $e) {
            report($e);
        }

        $result = [];
        foreach ($vsmLines as $l) {
            $qty = null;
            $date = null;
            foreach (($l['po_numbers'] ?? []) as $p) {
                if (! isset($receipts[$p])) {
                    continue;
                }
                foreach (($l['item_numbers'] ?? []) as $it) {
                    if (isset($receipts[$p]['items'][$it])) {
                        $qty = ($qty ?? 0.0) + $receipts[$p]['items'][$it];
                    }
                }
                if (! empty($receipts[$p]['last_delivery_date']) && (! $date || $receipts[$p]['last_delivery_date'] > $date)) {
                    $date = $receipts[$p]['last_delivery_date'];
                }
            }
            // Meters → yards: the only unit the fabric was actually procured in
            // that doesn't already match the vendor's yard-based entry.
            if ($qty !== null && strcasecmp((string) ($l['unit'] ?? ''), 'M') === 0) {
                $qty *= self::METER_TO_YARD;
            }
            $result[$l['label']] = ['qty' => $qty !== null ? round($qty, 2) : null, 'date' => $date];
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

        // Actual goods-received quantity per fabric (direct D365, always — no
        // local mirror exists for this).
        $receipts = $this->resolveGoodsReceipts(array_values($vsm));

        $labels = array_keys($vsm + $recon->all());

        $lines = [];
        foreach ($labels as $label) {
            // PCS = trims, not fabric. Also drops reconciliation rows snapshotted
            // before the exclusion existed (kept in the DB, just not surfaced).
            if (preg_match('/\(PCS\)\s*$/i', $label)) {
                continue;
            }
            $isNonFabric = false;
            foreach (self::NON_FABRIC_KEYWORDS as $keyword) {
                if (stripos($label, $keyword) !== false) {
                    $isNonFabric = true;
                    break;
                }
            }
            if ($isNonFabric) {
                continue;
            }
            $r = $recon->get($label);
            $priced = $pricing[$label] ?? ['price' => ($vsm[$label]['fabric_price'] ?? null), 'currency' => null];

            // Convert a non-IDR source price to IDR via the configured FX rate so
            // the deduction is always computed in IDR. The converted value is the
            // prefill; a saved admin value still wins. Source kept as a short hint
            // so the 30% policy markup isn't silently invisible on screen.
            $srcPrice = $priced['price'] ?? null; // already includes FABRIC_PRICE_MARKUP, per resolveFabricPricing()
            $srcCurrency = $priced['currency'] ?? null;
            $prefillPrice = $srcPrice;
            $priceSource = null;
            if ($srcPrice !== null) {
                $rawPrice = round($srcPrice / self::FABRIC_PRICE_MARKUP, 2);
                $markupPct = round((self::FABRIC_PRICE_MARKUP - 1) * 100);
                if ($srcCurrency && strtoupper((string) $srcCurrency) !== 'IDR') {
                    $rate = $this->fxRateToIdr($srcCurrency);
                    if ($rate > 0) {
                        $prefillPrice = round($srcPrice * $rate, 2);
                        $cur = strtoupper((string) $srcCurrency);
                        $priceSource = $cur.' '.number_format($rawPrice, 2).' (+'.$markupPct.'%) @ '.number_format($rate).' = Rp '.number_format($prefillPrice, 2);
                    }
                } else {
                    $priceSource = 'Rp '.number_format($rawPrice, 2).' (+'.$markupPct.'%)';
                }
            }

            // Price: admin-saved value wins; otherwise the IDR-converted prefill.
            $fabricPrice = $r && $r->fabric_price !== null
                ? (float) $r->fabric_price
                : $prefillPrice;

            $lines[] = [
                'label' => $label,
                'unit' => self::displayUnit($label),
                'item_number' => $vsm[$label]['item_number'] ?? null,
                'inventory_group' => $vsm[$label]['inventory_group'] ?? null,
                'goods_receive' => $receipts[$label]['qty'] ?? null,
                'goods_receive_date' => $receipts[$label]['date'] ?? null,
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
                'fabric_currency' => 'IDR', // always IDR: native or FX-converted above
                'fabric_price_source' => $priceSource, // e.g. "USD 0.79 @ 16,000 = Rp 12,640.00" or null
                'deduction' => $r && $r->deduction !== null ? (float) $r->deduction : null,
            ];
        }

        return $lines;
    }

    /**
     * FX rate to convert a source currency into IDR for the fabric-price
     * deduction. Fetched LIVE from a public rates API (open.er-api.com) for the
     * current Jakarta day and cached until end-of-day, so the deduction uses
     * "that day's" rate without hitting the API on every approval render.
     * Returns 1.0 for IDR/blank and 0.0 for an unresolvable currency (→ no
     * conversion). USD keeps a 16,000 offline fallback so the deduction still
     * computes if the API is unreachable.
     */
    private function fxRateToIdr(string $currency): float
    {
        $code = strtoupper(trim($currency));
        if ($code === '' || $code === 'IDR') {
            return 1.0;
        }

        $today = now('Asia/Jakarta');
        $cacheKey = 'fx_'.strtolower($code).'_idr_'.$today->toDateString();

        $rate = \Illuminate\Support\Facades\Cache::get($cacheKey);
        if ($rate === null) {
            $rate = $this->fetchLiveFxRateToIdr($code); // 0.0 on failure — not cached, so a later render retries.
            if ($rate > 0) {
                \Illuminate\Support\Facades\Cache::put($cacheKey, $rate, $today->copy()->endOfDay());
            }
        }

        if ($rate > 0) {
            return (float) $rate;
        }

        // Best-effort offline fallback so a USD-priced deduction still computes.
        return $code === 'USD' ? 16000.0 : 0.0;
    }

    /**
     * Fetch today's live 1-unit-of-$code → IDR rate from open.er-api.com (free,
     * no API key). Returns 0.0 on any failure; the caller supplies a fallback.
     */
    private function fetchLiveFxRateToIdr(string $code): float
    {
        try {
            $resp = \Illuminate\Support\Facades\Http::timeout(8)
                ->get('https://open.er-api.com/v6/latest/'.$code);

            if ($resp->ok() && ($resp->json('result') === 'success')) {
                return (float) $resp->json('rates.IDR', 0);
            }
        } catch (\Throwable $e) {
            // Network/API failure → 0.0, caller falls back.
        }

        return 0.0;
    }

    /**
     * Total cutting quantity for an order (basis for actual_consumption).
     * This is the ACTUAL quantity CUT — the sum of the vendor's per-line cutting
     * reports (`SubconCuttingReport.cutting_qty`) — NOT the ordered/planned
     * production-group quantity from VSM (`production_group_lines.Qty`), which is
     * the order qty and over-states the basis (consumption must divide by what was
     * really cut). Consumption is only entered at the cutting gate, after the
     * vendor submits these reports, so the local sum is always available by then.
     */
    public function totalCutForOrder(\App\Models\SubconOrder $order): int
    {
        // D365 can reissue a size's ProdId mid-order, which leaves the old
        // row in place alongside the new one (rows are keyed by prod_id, not
        // size). Dedupe to one row per size — the most recently updated —
        // instead of summing every historical prod_id, or the qty doubles.
        return (int) \App\Models\SubconCuttingReport::where('order_id', $order->id)
            ->orderByDesc('updated_at')
            ->get(['size', 'cutting_qty'])
            ->unique('size')
            ->sum('cutting_qty');
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

            $namesByPo = [];
            if (!empty($plmIds)) {
                $nameByPlm = DB::connection('vsm')->table('plm_activity')
                    ->whereIn('PLMId', $plmIds)
                    ->whereNotNull('ArticleName')
                    ->where('ArticleName', '!=', '')
                    ->pluck('ArticleName', 'PLMId');

                foreach ($lines as $l) {
                    if ($name = $nameByPlm->get($l->PLMId)) {
                        $namesByPo[$l->PurchaseOrderNumber][] = $name;
                    }
                }
            }

            // Fallback: resolve SearchName from local production_group when PLM link is missing
            $foundPos = array_keys($namesByPo);
            $missingPos = array_diff($poNumbers, $foundPos);
            if (!empty($missingPos)) {
                $localOrders = DB::table('subcon_orders')
                    ->whereIn('order_number', $missingPos)
                    ->whereNotNull('production_group')
                    ->where('production_group', '!=', '')
                    ->get(['order_number', 'production_group']);

                foreach ($localOrders as $lo) {
                    $searchName = DB::connection('vsm')->table('production_group_lines')
                        ->where('ProductionGroup', $lo->production_group)
                        ->value('SearchName');
                    if ($searchName) {
                        $namesByPo[$lo->order_number][] = $searchName;
                    }
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
