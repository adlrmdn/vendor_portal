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
            if (! empty($plmIds)) {
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

    // Accessories/trims (labels, zippers, thread, polybags, hangtags, tape,
    // interlining, ...) mostly live under a SEPARATE PO pool, not the
    // fabric-pool PO — confirmed live 2026-09-03 (MPG/PO/2606/00643: 15 real
    // Acc-Local lines, 0 PCS lines on the Fab-Import PO). Units vary — labels
    // are CM, thread/tape/lakban are YD, most others are PCS — never assume
    // PCS. Exception: knit collars ride on the fabric PO itself as PCS lines
    // and never get their own Acc-* PO — see accessoryLinesForPo()'s docblock.
    private const ACCESSORY_POOL_PREFIX = 'Acc-';

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
                // $g['item_numbers'] is still the assoc set built during grouping
                // (['itemNumber' => true, ...]) at this point — the values are all
                // `true`; the item numbers are the KEYS.
                foreach (array_keys($g['item_numbers']) as $it) {
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
                $itemMasterDescription = null;
                foreach ($g['item_numbers'] as $it) {
                    if ($itemMasterDescription === null && ! empty($itemMaster[$it]['description'])) {
                        $itemMasterDescription = $itemMaster[$it]['description'];
                    }
                    if (! empty($itemMaster[$it]['inventory_group'])) {
                        $g['inventory_group'] = $itemMaster[$it]['inventory_group'];
                        break;
                    }
                }
                // `label` above stays the PO-derived grouping/persistence key (never
                // change it — subcon_fabric_reconciliations upserts by it). What's
                // actually shown to the user prefers the item master's real
                // description, since LineDescription is free text that can be stale
                // or wrong (seen in production: a genuine fabric line carrying a
                // leftover "HANGTAG ..." description from a copy-paste).
                $g['display_description'] = $itemMasterDescription ?? $g['description'];
                $g['display_label'] = $g['display_description'].($g['unit'] ? ' ('.$g['unit'].')' : '');

                return $g;
            }, $grouped));
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * The accessory/trim counterpart to fabricLinesForPo() — same PLMId join,
     * mainly the dedicated Acc-* pool PO lines, PLUS the PCS-unit lines on the
     * Fab-* pool PO that fabricLinesForPo() deliberately skips ("not fabric").
     * That second part matters: accessory-like components — confirmed live to
     * be exclusively knit collars ("FLATKNIT COLLAR ...", zero noise across
     * every PCS line found on a Fab-* PO in `vsm`) — sometimes ride on the
     * fabric PO itself and never get their own Acc-* PO. Without also pulling
     * those, they vanish from BOTH fabricLinesForPo() (excluded as "not
     * fabric") and this method (not on an Acc-* PO) — a real "accessories not
     * appearing" gap confirmed live on PLM/25/10/00050 (MPG/PO/2510/00612: 3
     * COLLAR lines, no Acc-* PO for that PLM at all).
     * Used to seed real, D365-sourced accessory return-quantity rows (see
     * MaterialReturnService) instead of free-typing item names.
     *
     * @return array<int, array{label: string, display_label: string, unit: ?string, item_number: string, item_numbers: array<int,string>, po_numbers: array<int,string>, ordered_qty: float, unit_price: ?float}>
     */
    public function accessoryLinesForPo(string $poNumber): array
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
                ->where(function ($q) {
                    $q->where('h.PurchPoolId', 'like', self::ACCESSORY_POOL_PREFIX.'%')
                        ->orWhere(function ($q2) {
                            $q2->where('h.PurchPoolId', 'like', self::FABRIC_POOL_PREFIX.'%')
                                ->where('l.PurchaseUnitSymbol', 'PCS');
                        });
                })
                ->get(['l.LineDescription', 'l.PurchaseUnitSymbol', 'l.OrderedPurchaseQuantity', 'l.LineAmount', 'l.ItemNumber', 'l.PurchaseOrderNumber']);

            $grouped = [];
            foreach ($lines as $ln) {
                $desc = trim((string) ($ln->LineDescription ?? ''));
                $unit = trim((string) ($ln->PurchaseUnitSymbol ?? ''));
                if ($desc === '') {
                    continue;
                }
                $key = $desc.'|'.$unit;
                if (! isset($grouped[$key])) {
                    $grouped[$key] = ['description' => $desc, 'unit' => $unit, 'ordered_qty' => 0.0, 'total_amount' => 0.0, 'item_numbers' => [], 'po_numbers' => []];
                }
                $grouped[$key]['ordered_qty'] += (float) ($ln->OrderedPurchaseQuantity ?? 0);
                $grouped[$key]['total_amount'] += (float) ($ln->LineAmount ?? 0);
                if (! empty($ln->ItemNumber)) {
                    $grouped[$key]['item_numbers'][$ln->ItemNumber] = true;
                }
                if (! empty($ln->PurchaseOrderNumber)) {
                    $grouped[$key]['po_numbers'][$ln->PurchaseOrderNumber] = true;
                }
            }

            $allItemNumbers = [];
            foreach ($grouped as $g) {
                foreach (array_keys($g['item_numbers']) as $it) {
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
                // Weighted unit price = Σ LineAmount / Σ ordered qty — same
                // VSM-only reference as fabricLinesForPo()'s fabric_price (no
                // currency; see resolveFabricPricing()'s docblock for why this
                // layering does not extend to accessories).
                $g['unit_price'] = $g['ordered_qty'] > 0 ? round($g['total_amount'] / $g['ordered_qty'], 2) : null;
                unset($g['total_amount']);
                $g['ordered_qty'] = round($g['ordered_qty'], 2);
                $itemNumbers = array_keys($g['item_numbers']);
                $g['item_number'] = implode(', ', $itemNumbers);
                // item_numbers/po_numbers kept (not unset, unlike fabric's own
                // copy used to be) — resolveGoodsReceipts() needs both arrays to
                // scope the D365 lookup to the RIGHT PO (the accessory's own
                // supplier PO, never the CMT subcon PO — that only ever receives
                // the finished-garment service line).
                $g['item_numbers'] = $itemNumbers;
                $g['po_numbers'] = array_keys($g['po_numbers']);
                $g['label'] = $g['description'].' ('.$g['unit'].')';
                $itemMasterDescription = null;
                foreach ($itemNumbers as $it) {
                    if (! empty($itemMaster[$it]['description'])) {
                        $itemMasterDescription = $itemMaster[$it]['description'];
                        break;
                    }
                }
                $g['display_label'] = ($itemMasterDescription ?? $g['description']).' ('.$g['unit'].')';

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

        // Policy margin: every resolved fabric price (local po_items, direct D365,
        // or the VSM fallback alike) is divided by 0.7 (i.e. the raw cost is
        // treated as 70% of the priced value) before it reaches the
        // consumption/deduction calc. Applied here — the single funnel all three
        // sources pass through — so it can't be missed on any one source.
        foreach ($result as $label => $r) {
            if ($r['price'] !== null) {
                $result[$label]['price'] = round($r['price'] / self::FABRIC_PRICE_MARKUP_DIVISOR, 2);
            }
        }

        return $result;
    }

    /** Policy margin divisor applied to every resolved fabric price (see resolveFabricPricing()). */
    private const FABRIC_PRICE_MARKUP_DIVISOR = 0.7;

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
     * Real, already-posted material consumption for an order — read straight
     * from VSM `material_issue_lines`, synced from D365's production-journal
     * BOM entity (`ProdJournalBomCDREntities`: `BOMProposal`/`BOMConsump` map to
     * this table's `ProposalBOMQuantity`/`ConsumptionBOMQuantity`). Keyed by
     * `ProductionGroup` — this order's OWN production runs only (one row per
     * size's production order), unlike VSM `bom_lines` (BOM Final), which is a
     * style-wide design template shared across every PO that ever reused the
     * same PLM (confirmed: up to 18 POs on one sampled style) and so cannot be
     * safely divided into a per-order figure. This is the real posted number:
     * no division/estimation, no cross-PO contamination — verified against a
     * real order where the summed consumption for a fabric item matched its
     * admin-entered Fabric Sent to the decimal (420.00 = 420.00).
     *
     * `proposal` = D365's suggested/planned issue quantity (BOM standard × qty
     * produced) computed before consumption was posted — the closest available
     * proxy for "material sent for production" absent a dedicated dispatch
     * event in this app. `consumption` = what was actually posted as consumed.
     * `all_posted` is false if any contributing line is still a draft
     * (`IsPosted` = No) — those figures may still move.
     *
     * @return array<string, array{proposal: float, consumption: float, all_posted: bool}>
     */
    public function materialIssueForOrder(string $productionGroup): array
    {
        $productionGroup = trim($productionGroup);
        if ($productionGroup === '') {
            return [];
        }

        try {
            $rows = DB::connection('vsm')->table('material_issue_lines')
                ->where('ProductionGroup', $productionGroup)
                ->get(['ItemNumber', 'ProposalBOMQuantity', 'ConsumptionBOMQuantity', 'IsPosted']);

            $out = [];
            foreach ($rows as $r) {
                $item = (string) $r->ItemNumber;
                $out[$item] ??= ['proposal' => 0.0, 'consumption' => 0.0, 'all_posted' => true];
                $out[$item]['proposal'] += (float) $r->ProposalBOMQuantity;
                $out[$item]['consumption'] += (float) $r->ConsumptionBOMQuantity;
                if (strcasecmp((string) $r->IsPosted, 'Yes') !== 0) {
                    $out[$item]['all_posted'] = false;
                }
            }

            return $out;
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * Per-fabric lines for an order, enriched with locally-stored reconciliation
     * AND consumption (fabric_sent / consumption_plan / cutt_plan /
     * actual_consumption / overconsumption). The fabric set is the union of
     * VSM-linked fabrics and any reconciliation rows already saved (so
     * vendor-added fabrics persist). Consumption fields are null until the admin
     * fills them at cutting approval. See SubconConsumptionService for the formulas.
     * `fabric_sent_issue` is a reference/prefill only (D365's real Material
     * Issue posting for this order) — never persisted itself, purely a UI
     * default when `fabric_sent` hasn't been entered yet.
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

        // Real D365 Material Issue posting (production journal) — same source
        // materialIssueForOrder() already gives the accessory side's Mats Sent
        // prefill. Used below as a reference/prefill for Fabric Sent when no
        // admin value is saved yet: this order's OWN production runs only
        // (ProductionGroup-scoped), unlike bom_lines' style-wide BOM Final.
        $materialIssue = $this->materialIssueForOrder((string) $order->production_group);
        $issueSent = function (?string $itemNumberCsv) use ($materialIssue) {
            if (! $itemNumberCsv) {
                return null;
            }
            $sum = null;
            foreach (array_filter(array_map('trim', explode(',', $itemNumberCsv))) as $it) {
                if (isset($materialIssue[$it])) {
                    $sum = ($sum ?? 0) + $materialIssue[$it]['proposal'];
                }
            }

            return $sum;
        };

        $labels = array_keys($vsm + $recon->all());

        $lines = [];
        foreach ($labels as $label) {
            // PCS = trims, not fabric. Also drops reconciliation rows snapshotted
            // before the exclusion existed (kept in the DB, just not surfaced).
            if (preg_match('/\(PCS\)\s*$/i', $label)) {
                continue;
            }
            $r = $recon->get($label);
            $priced = $pricing[$label] ?? ['price' => ($vsm[$label]['fabric_price'] ?? null), 'currency' => null];

            // Convert a non-IDR source price to IDR via the configured FX rate so
            // the deduction is always computed in IDR. The converted value is the
            // prefill; a saved admin value still wins. Source kept as a short hint
            // so the policy margin divisor isn't silently invisible on screen.
            $srcPrice = $priced['price'] ?? null; // already divided by FABRIC_PRICE_MARKUP_DIVISOR, per resolveFabricPricing()
            $srcCurrency = $priced['currency'] ?? null;
            $prefillPrice = $srcPrice;
            $priceSource = null;
            if ($srcPrice !== null) {
                $rawPrice = round($srcPrice * self::FABRIC_PRICE_MARKUP_DIVISOR, 2);
                $divisorLabel = rtrim(rtrim(number_format(self::FABRIC_PRICE_MARKUP_DIVISOR, 2), '0'), '.');
                if ($srcCurrency && strtoupper((string) $srcCurrency) !== 'IDR') {
                    $rate = $this->fxRateToIdr($srcCurrency);
                    if ($rate > 0) {
                        $prefillPrice = round($srcPrice * $rate, 2);
                        $cur = strtoupper((string) $srcCurrency);
                        $priceSource = $cur.' '.number_format($rawPrice, 2).' (÷'.$divisorLabel.') @ '.number_format($rate).' = Rp '.number_format($prefillPrice, 2);
                    }
                } else {
                    $priceSource = 'Rp '.number_format($rawPrice, 2).' (÷'.$divisorLabel.')';
                }
            }

            // Price: admin-saved value wins; otherwise the IDR-converted prefill.
            $fabricPrice = $r && $r->fabric_price !== null
                ? (float) $r->fabric_price
                : $prefillPrice;

            $lines[] = [
                'label' => $label,
                // The real fabric name, preferred over the PO's own (possibly
                // stale/wrong) LineDescription-derived $label — see fabricLinesForPo().
                'display_label' => $vsm[$label]['display_label'] ?? $label,
                'unit' => self::displayUnit($label),
                'item_number' => $vsm[$label]['item_number'] ?? null,
                'inventory_group' => $vsm[$label]['inventory_group'] ?? null,
                'goods_receive' => $receipts[$label]['qty'] ?? null,
                'fabric_sent_issue' => $issueSent($vsm[$label]['item_number'] ?? null),
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
            if (! empty($plmIds)) {
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
            if (! empty($missingPos)) {
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
