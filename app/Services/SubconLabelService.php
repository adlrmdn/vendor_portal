<?php

namespace App\Services;

use App\Models\SubconCuttingReport;
use App\Models\SubconOrder;

/**
 * Assembles the DomPDF view data for a subcon work order's packaging labels.
 *
 * Actual quantities and the box/blister page count come from the D365 packing
 * instruction (via D365JobTransactionService::fetchPackingInstructionGroups) —
 * no longer from the estimated DTLines. This service layers on the presentation
 * concerns that need VSM / local data: style name, per-size gramasi weights,
 * item/variant resolution, and physical store addresses. Shared verbatim by the
 * subcon vendor and admin controllers so the two can never drift.
 */
class SubconLabelService
{
    public function __construct(
        private SubconProductionService $production,
        private D365JobTransactionService $d365,
    ) {}

    /**
     * Build the label view data for an order, or an ['error' => string] the
     * caller can redirect back with (missing distribution / no PI generated).
     *
     * $scope selects which stores to print:
     *   - 'store'     → every store EXCEPT WH Replenish / WH Online
     *   - 'warehouse' → only WH Replenish / WH Online (the sack stores)
     *   - 'all'       → everything (default)
     *
     * @return array{order:SubconOrder, styleName:string, grouped:array, printDate:string, scope:string}|array{error:string}
     */
    public function buildViewData(SubconOrder $order, string $scope = 'all'): array
    {
        if (empty($order->distribution_id)) {
            return ['error' => 'This order does not have a Distribution ID associated.'];
        }

        $groups = $this->d365->fetchPackingInstructionGroups($order);
        if (empty($groups)) {
            return ['error' => 'No packing instruction found for Distribution ID: '.$order->distribution_id.'. Labels may not be generated yet.'];
        }

        // Split by the two central distribution warehouses (WH Replenish / WH Online).
        if ($scope === 'warehouse' || $scope === 'store') {
            $whStores = $this->d365->warehouseStoreIds();
            $groups = array_values(array_filter(
                $groups,
                fn ($g) => in_array($g['store_id'], $whStores, true) === ($scope === 'warehouse')
            ));
            if (empty($groups)) {
                return ['error' => $scope === 'warehouse'
                    ? 'No WH Replenish / WH Online labels for this work order — its packing instruction has no rows for those warehouses.'
                    : 'No store labels for this work order — its packing instruction only has WH Replenish / WH Online rows.'];
            }
        }

        // Guard: a group with no PackingCode would render a blank/unscannable
        // barcode. That means the packing instruction was never fully generated
        // (the DTT bot timed out / didn't finish), so block the print entirely
        // rather than emit useless labels — the labels must be (re)generated first.
        $missing = array_values(array_filter($groups, fn ($g) => trim((string) $g['packing_code']) === ''));
        if (! empty($missing)) {
            return ['error' => 'Cannot print: '.count($missing).' of '.count($groups).' labels have no packing code yet, so their barcodes would be blank. The packing instruction has not been fully generated — please (re)generate labels before printing.'];
        }

        // Style / article name (from VSM production detail).
        $productionGroups = $this->production->forPo($order->order_number);
        $summary = $this->production->summarize($productionGroups);
        $styleName = ! empty($productionGroups)
            ? ($productionGroups[0]['article_name'] ?? $summary['style'] ?? $order->title)
            : ($summary['style'] ?? $order->title);

        // Warehouses to resolve physical addresses (keyed by StoreID).
        $storeIds = array_values(array_unique(array_filter(array_map(fn ($g) => $g['store_id'] ?: null, $groups))));
        $warehouses = ! empty($storeIds) ? $this->d365->fetchWarehouses($storeIds) : [];

        // Size → item/variant fallback from VSM, used when a PI detail has no VariantID.
        $variantBySize = [];
        foreach ($productionGroups as $pg) {
            foreach (($pg['lines'] ?? []) as $pl) {
                $sz = strtolower($pl->Size ?? '');
                if ($sz !== '' && ! empty($pl->ItemId)) {
                    $variantBySize[$sz] = $pl->ItemId;
                }
            }
        }

        // Local per-size gramasi (grams/piece) — only a fallback when the PI line
        // carries no actual TOC_GramasiReal.
        $gramasiMap = [];
        foreach (SubconCuttingReport::where('order_id', $order->id)->get() as $report) {
            if ($report->gramasi !== null && $report->gramasi > 0) {
                $gramasiMap[strtolower($report->size)] = (float) $report->gramasi;
            }
        }
        // "Follow the previous number" when a size has no gramasi of its own.
        $lastGramasi = ! empty($gramasiMap) ? reset($gramasiMap) : 0.0;

        foreach ($groups as &$group) {
            $items = [];
            $totalWeight = 0.0;
            foreach ($group['items'] as $d) {
                $sizeLower = strtolower($d['size']);
                if (isset($gramasiMap[$sizeLower])) {
                    $lastGramasi = $gramasiMap[$sizeLower];
                }
                // Prefer the PI's actual real weight (kg total for the line); fall
                // back to local gramasi (grams/piece) × qty when D365 has none.
                $weight = $d['gramasi_real'] > 0
                    ? $d['gramasi_real']
                    : $d['qty'] * ($lastGramasi / 1000);
                $totalWeight += $weight;

                $items[] = [
                    'item_id' => $d['variant_id'] ?: ($variantBySize[$sizeLower] ?? ($d['item_code'] ?: '—')),
                    'size' => $d['size'],
                    'qty' => $d['qty'],
                    'weight' => $weight,
                ];
            }
            $group['items'] = $items;
            $group['total_weight'] = $totalWeight;

            $warehouse = $warehouses[$group['store_id']] ?? null;
            // D365 stamps the ORIGIN warehouse name onto EVERY PI row's StoreName
            // (so all rows read e.g. "WH REPLENISH PEMALANG"). Use the real
            // destination name from the Warehouses master; keep the PI value only
            // if the master has no record for this StoreID.
            if (! empty($warehouse['WarehouseName'])) {
                $group['store_name'] = $warehouse['WarehouseName'];
            }
            $group['store_province'] = ! empty($warehouse['TOC_DistribWh']) ? $warehouse['TOC_DistribWh'] : '';
            $group['address'] = $this->getStoreAddress(
                $group['store_id'],
                $group['store_name'],
                $group['store_province'],
                $warehouse
            );
        }
        unset($group);

        // Predictable ordering by store name.
        usort($groups, fn ($a, $b) => strcmp($a['store_name'], $b['store_name']));

        $printDate = $order->order_date ? $order->order_date->format('n/j/Y') : date('n/j/Y');

        return [
            'order' => $order,
            'styleName' => $styleName,
            'grouped' => $groups,
            'printDate' => $printDate,
            'scope' => $scope,
        ];
    }

    private function getStoreAddress(string $storeId, string $storeName, ?string $province, ?array $warehouse): array
    {
        if (! empty($warehouse)) {
            $street = $warehouse['PrimaryAddressStreet'] ?? '';
            $city = $warehouse['PrimaryAddressCity'] ?? '';
            $state = $warehouse['PrimaryAddressStateId'] ?? $warehouse['TOC_DistribWh'] ?? '';
            $zip = $warehouse['PrimaryAddressZipCode'] ?? '';
            $country = $warehouse['PrimaryAddressCountryRegionId'] ?? 'IDN';

            if (! empty($street)) {
                $line2 = trim(($city ? $city.', ' : '').($state ? $state.' ' : '').$zip);

                return [
                    'line1' => $street,
                    'line2' => $line2 ?: ($province ? strtoupper($province).' '.$zip : $zip),
                    'country' => $country,
                ];
            }
        }

        $addresses = [
            '11242' => [
                'line1' => 'Jl. Boulevard Diponegoro, Kelapa Dua',
                'line2' => 'TANGERANG, BANTEN 15810',
                'country' => 'IDN',
            ],
            '20223' => [
                'line1' => 'Jl. Boulevard Barat Raya, Kelapa Gading',
                'line2' => 'JAKARTA UTARA, DKI JAKARTA 14240',
                'country' => 'IDN',
            ],
            '20256' => [
                'line1' => 'Jl. Jend Sudirman, Gelora, Tanah Abang',
                'line2' => 'JAKARTA PUSAT, DKI JAKARTA 10270',
                'country' => 'IDN',
            ],
            '20267' => [
                'line1' => 'Jl. Asia Afrika No.8, Gelora, Tanah Abang',
                'line2' => 'JAKARTA PUSAT, DKI JAKARTA 10270',
                'country' => 'IDN',
            ],
            '20269' => [
                'line1' => 'Jl. Alam Sutera Boulevard No.21, Pakulonan',
                'line2' => 'TANGERANG SELATAN, BANTEN 15325',
                'country' => 'IDN',
            ],
            '20294' => [
                'line1' => 'Jl. Grand Galaxy Boulevard, Jakasetia',
                'line2' => 'BEKASI, JAWA BARAT 17147',
                'country' => 'IDN',
            ],
            '20207' => [
                'line1' => 'Jl. Pemuda No.150, Sekayu, Semarang Tengah',
                'line2' => 'SEMARANG, JAWA TENGAH 50132',
                'country' => 'IDN',
            ],
        ];

        if (isset($addresses[$storeId])) {
            return $addresses[$storeId];
        }

        $prov = $province ?: 'DKI JAKARTA';

        return [
            'line1' => $storeName,
            'line2' => 'KOTA/KABUPATEN, '.strtoupper($prov),
            'country' => 'IDN',
        ];
    }
}
