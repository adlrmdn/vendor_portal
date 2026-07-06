<?php

namespace App\Console\Commands;

use App\Models\SubconOrder;
use App\Models\SubconOrderItem;
use App\Models\Vendor;
use App\Services\SubconProductionService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SyncD365SubconOrders extends Command
{
    /**
     * Subcon counterpart of d365:sync-orders.
     *
     * Pulls REAL purchase orders from D365 for vendors flagged as `type = subcon`
     * in the local DB and materialises them as subcon work orders
     * (subcon_orders + subcon_order_items). The PO number is used verbatim as the
     * work order number, exactly like the fabric pipeline.
     */
    protected $signature = 'd365:sync-subcon-orders
                            {--days=60 : Only sync POs created within the last N days}
                            {--vendor=* : Limit to specific vendor code(s); defaults to all subcon vendors}
                            {--company=mpg : D365 legal entity (dataAreaId) to restrict to. REQUIRED to avoid cross-company vendor-code collisions (e.g. V0246 = KNK in mpg but PUMA CAT in mpr)}
                            {--dry-run : Fetch and report only; write nothing}';

    protected $description = 'Fetch subcon vendors\' POs from D365 and sync them into subcon work orders';

    public function handle(SubconProductionService $production)
    {
        $this->info('Starting D365 subcon ETL pipeline...');

        // Resolve which vendors to sync (subcon only).
        $vendorQuery = Vendor::where('type', 'subcon');
        if ($only = $this->option('vendor')) {
            $vendorQuery->whereIn('vendor_code', $only);
        }
        $vendors = $vendorQuery->pluck('id', 'vendor_code')->toArray();

        if (empty($vendors)) {
            $this->error('No subcon vendors found to sync.');

            return Command::FAILURE;
        }
        $this->info('Target subcon vendors: '.implode(', ', array_keys($vendors)));

        $token = $this->getD365Token();
        if (! $token) {
            $this->error('Failed to authenticate with D365.');

            return Command::FAILURE;
        }

        // 1. Fetch headers for these vendor accounts, confirmed, within window.
        $cutoff = Carbon::now()->subDays((int) $this->option('days'))->format('Y-m-d\TH:i:s\Z');
        $vendorClause = '('.implode(' or ', array_map(
            fn ($code) => "OrderVendorAccountNumber eq '$code'",
            array_keys($vendors)
        )).')';
        $filters = [
            $vendorClause,
            "DocumentApprovalStatus eq Microsoft.Dynamics.DataEntities.VersioningDocumentState'Confirmed'",
            "CreatedDateTime1 ge $cutoff",
        ];

        // Vendor account codes are company-scoped in D365 — the same code maps to
        // different legal vendors across companies. Restrict to one legal entity.
        if ($company = $this->option('company')) {
            $filters[] = "dataAreaId eq '$company'";
        }

        $localActivePos = SubconOrder::whereNotIn('status', ['completed', 'cancelled'])->pluck('order_number')->toArray();

        $this->info('Fetching PO headers...');
        $headers = $this->fetchRecords('PurchaseOrderHeadersV2', $filters);
        $this->info('Fetched '.count($headers).' header records.');

        $fetchedPoNumbers = [];
        foreach ($headers as $h) {
            $fetchedPoNumbers[] = $h['PurchaseOrderNumber'];
        }

        $missingActivePos = array_diff($localActivePos, $fetchedPoNumbers);
        if (! empty($missingActivePos)) {
            $this->info('Fetching headers for '.count($missingActivePos).' local active POs outside cutoff...');
            foreach (array_chunk($missingActivePos, 20) as $chunk) {
                $parts = array_map(fn ($po) => "PurchaseOrderNumber eq '$po'", $chunk);
                $missingFilters = ['('.implode(' or ', $parts).')'];
                if (! empty($company)) {
                    $missingFilters[] = "dataAreaId eq '$company'";
                }
                $missingHeaders = $this->fetchRecords('PurchaseOrderHeadersV2', $missingFilters);
                $headers = array_merge($headers, $missingHeaders);
            }
        }

        if (empty($headers)) {
            $this->info('No subcon orders to sync.');

            return Command::SUCCESS;
        }

        // 2. Map PO -> local vendor id (skip any vendor we do not track).
        $poMap = [];      // po_number => vendor_id
        $poHeaders = [];  // po_number => header
        foreach ($headers as $h) {
            $code = $h['OrderVendorAccountNumber'] ?? null;
            if (! $code || ! isset($vendors[$code])) {
                continue;
            }
            $poNumber = $h['PurchaseOrderNumber'];
            $poMap[$poNumber] = $vendors[$code];
            $poHeaders[$poNumber] = $h;
        }
        $this->info('Matched '.count($poMap).' POs to subcon vendors.');
        if (empty($poMap)) {
            return Command::SUCCESS;
        }

        // 3. Fetch lines in batches of 20 POs.
        $allLines = [];
        foreach (array_chunk(array_keys($poMap), 20) as $chunk) {
            $parts = array_map(fn ($po) => "PurchaseOrderNumber eq '$po'", $chunk);
            $lineFilters = ['('.implode(' or ', $parts).')'];
            if (! empty($company)) {
                $lineFilters[] = "dataAreaId eq '$company'";
            }
            $batch = $this->fetchRecords('PurchaseOrderLinesV2', $lineFilters);
            $allLines = array_merge($allLines, $batch);
        }
        $this->info('Fetched '.count($allLines).' line records.');

        // Group lines per PO.
        $linesByPo = [];
        foreach ($allLines as $l) {
            $linesByPo[$l['PurchaseOrderNumber']][] = $l;
        }

        if ($this->option('dry-run')) {
            foreach ($poMap as $poNumber => $vendorId) {
                $this->line(sprintf(
                    '  %s  (%d items)  created %s',
                    $poNumber,
                    count($linesByPo[$poNumber] ?? []),
                    $poHeaders[$poNumber]['CreatedDateTime1'] ?? '?'
                ));
            }
            $this->warn('Dry run: nothing written.');

            return Command::SUCCESS;
        }

        // Resolve garment style names from VSM so the order title is the style
        // (e.g. "Minimal Ane Shirt Blue") rather than the generic PO number.
        // POs not yet present in the VSM snapshot fall back to the old title.
        $styles = $production->stylesForPos(array_keys($poMap));

        // Resolve Distribution IDs from VSM mapping & D365 TOC_DT entity.
        $this->info('Resolving PLM activities from VSM...');
        $poNumbers = array_keys($poMap);
        $poPlmMap = [];
        $uniquePlmIds = [];
        try {
            $poLines = DB::connection('vsm')->table('po_lines')
                ->whereIn('PurchaseOrderNumber', $poNumbers)
                ->whereNotNull('PLMId')
                ->where('PLMId', '!=', '')
                ->get(['PurchaseOrderNumber', 'PLMId']);
            foreach ($poLines as $l) {
                $poPlmMap[$l->PurchaseOrderNumber][] = $l->PLMId;
                $uniquePlmIds[] = $l->PLMId;
            }
            $uniquePlmIds = array_unique($uniquePlmIds);
        } catch (\Throwable $e) {
            $this->warn('Failed to fetch PO lines from VSM: '.$e->getMessage());
        }

        $plmSoMap = [];
        if (! empty($uniquePlmIds)) {
            $this->info('Resolving Sales Orders from VSM plm_trans...');
            try {
                $trans = DB::connection('vsm')->table('plm_trans')
                    ->whereIn('PLMId', $uniquePlmIds)
                    ->where('ActivityName', 'SO Intercompany')
                    ->whereNotNull('ActivityNo')
                    ->where('ActivityNo', '!=', '')
                    ->get(['PLMId', 'ActivityNo']);
                foreach ($trans as $t) {
                    $plmSoMap[$t->PLMId] = $t->ActivityNo;
                }
            } catch (\Throwable $e) {
                $this->warn('Failed to fetch SO mapping from VSM: '.$e->getMessage());
            }
        }

        $soDstMap = [];
        $salesOrders = array_unique(array_values($plmSoMap));
        if (! empty($salesOrders)) {
            $this->info('Fetching Distribution IDs from D365 TOC_DT...');
            foreach (array_chunk($salesOrders, 20) as $chunk) {
                $filterParts = array_map(fn ($so) => "SOID eq '$so'", $chunk);
                $filter = '('.implode(' or ', $filterParts).')';
                try {
                    $tocRecords = $this->fetchRecords('TOC_DT', [$filter], ['SOID', 'DistributionID']);
                    foreach ($tocRecords as $rec) {
                        if (! empty($rec['SOID']) && ! empty($rec['DistributionID'])) {
                            $soDstMap[$rec['SOID']] = $rec['DistributionID'];
                        }
                    }
                } catch (\Throwable $e) {
                    $this->warn('Failed to fetch Distribution IDs from D365: '.$e->getMessage());
                }
            }
        }

        $poDstMap = [];
        foreach ($poMap as $poNumber => $vendorId) {
            $plmIds = $poPlmMap[$poNumber] ?? [];
            $dstIds = [];
            foreach ($plmIds as $plmId) {
                if ($so = $plmSoMap[$plmId] ?? null) {
                    if ($dst = $soDstMap[$so] ?? null) {
                        $dstIds[] = $dst;
                    }
                }
            }
            if (! empty($dstIds)) {
                $poDstMap[$poNumber] = implode(' / ', array_unique($dstIds));
            }
        }        // 4. Transform + persist.
        DB::beginTransaction();
        try {
            $createdOrders = 0;
            $createdItems = 0;
            $retitled = 0;
            $deletedOrders = 0;

            foreach ($poMap as $poNumber => $vendorId) {
                $h = $poHeaders[$poNumber];

                // --- 1. Check if Finished ---
                $isFinished = false;

                // A. Check D365 PurchaseOrderStatus
                $d365Status = $h['PurchaseOrderStatus'] ?? null;
                if ($d365Status && $d365Status !== 'Backorder') {
                    $isFinished = true;
                }

                // B. Check production order status (from VSM production group lines)
                $groups = $production->forPo($poNumber);
                if (! $isFinished && ! empty($groups)) {
                    $allVsmLines = [];
                    foreach ($groups as $g) {
                        if (! empty($g['lines'])) {
                            foreach ($g['lines'] as $line) {
                                $allVsmLines[] = $line;
                            }
                        }
                    }
                    if (! empty($allVsmLines)) {
                        // Only a fully *Completed* group counts as finished here.
                        // 'ReportedFinished' means sewing reported done, but the
                        // subcon paperwork (cutting/gramasi/labels) may still be
                        // pending — removing the order then would yank it mid-flow.
                        $finishedLines = array_filter($allVsmLines, function ($line) {
                            return in_array($line->ProdStatus, ['Completed']);
                        });
                        if (count($finishedLines) === count($allVsmLines)) {
                            $isFinished = true;
                        }
                    }
                }

                if ($isFinished) {
                    $existsLocal = SubconOrder::where('order_number', $poNumber)->exists();
                    if ($existsLocal) {
                        SubconOrder::where('order_number', $poNumber)->delete();
                        $deletedOrders++;
                        $this->info("PO {$poNumber} is finished. Deleted/removed from local portal.");
                    } else {
                        $this->info("PO {$poNumber} is finished on D365. Skipping creation.");
                    }

                    continue; // Skip creating or syncing this order further
                }

                // --- 2. Create/Update SubconOrder ---
                $orderDate = isset($h['CreatedDateTime1'])
                    ? Carbon::parse($h['CreatedDateTime1'])->format('Y-m-d')
                    : now()->format('Y-m-d');
                $dueDate = isset($h['RequestedDeliveryDate'])
                    ? Carbon::parse($h['RequestedDeliveryDate'])->format('Y-m-d')
                    : null;
                $style = $styles[$poNumber] ?? null;
                $title = $style
                    ?: (! empty($h['VendorOrderReference'])
                        ? $h['VendorOrderReference']
                        : ('Work Order '.$poNumber));
                $distributionId = $poDstMap[$poNumber] ?? null;

                // Resolve Production Group fallback
                $tocPrg = $h['TOC_ProductionGroup'] ?? null;
                if (! $tocPrg && ! empty($groups)) {
                    foreach ($groups as $g) {
                        if (! empty($g['production_group'])) {
                            $tocPrg = $g['production_group'];
                            break;
                        }
                    }
                }

                $summary = $production->summarize($groups);
                $sizesCount = $summary['size_count'] ?? 0;

                $order = SubconOrder::firstOrCreate(
                    ['order_number' => $poNumber],
                    [
                        'id' => (string) Str::uuid(),
                        'vendor_id' => $vendorId,
                        'status' => 'pending',
                        'title' => $title,
                        'description' => null,
                        'order_date' => $orderDate,
                        'due_date' => $dueDate,
                        'notes' => $h['ReasonComment'] ?? null,
                        'distribution_id' => $distributionId,
                        'workflow_stage' => SubconOrder::STAGE_CUTTING,
                        'production_group' => $tocPrg,
                        'sizes_count' => $sizesCount,
                    ]
                );

                if ($order->wasRecentlyCreated) {
                    $createdOrders++;
                } else {
                    $updates = [];
                    if ($style && $order->title !== $style) {
                        $updates['title'] = $style;
                        $retitled++;
                    }
                    if ($distributionId && $order->distribution_id !== $distributionId) {
                        $updates['distribution_id'] = $distributionId;
                    }
                    if ($tocPrg && $order->production_group !== $tocPrg) {
                        $updates['production_group'] = $tocPrg;
                    }
                    if ($sizesCount !== $order->sizes_count) {
                        $updates['sizes_count'] = $sizesCount;
                    }
                    if (! empty($updates)) {
                        $order->update($updates);
                    }
                }

                // --- 3. Create Order Items ---
                foreach ($linesByPo[$poNumber] ?? [] as $l) {
                    $item = SubconOrderItem::firstOrCreate(
                        ['order_id' => $order->id, 'item_number' => $l['ItemNumber']],
                        [
                            'id' => (string) Str::uuid(),
                            'description' => $l['LineDescription'] ?? $l['ItemNumber'],
                            'quantity' => $l['OrderedPurchaseQuantity'] ?? 0.0,
                            'unit' => $l['PurchaseUnitSymbol'] ?? 'PCS',
                            'status' => 'pending',
                            'notes' => null,
                        ]
                    );
                    if ($item->wasRecentlyCreated) {
                        $createdItems++;
                    }
                }

                // --- 4. Sync Qty Cutting and Gramasi from D365 and Escalate Workflow ---
                $cuttingFilled = false;
                $gramasiFilled = false;

                $jobTransService = app(\App\Services\D365JobTransactionService::class);
                foreach ($groups as $g) {
                    if (empty($g['production_group'])) {
                        continue;
                    }

                    $erpDetails = $jobTransService->fetchJobTransactionDetails($g['production_group']);

                    // Process cutting quantities
                    if (! empty($erpDetails['cutting'])) {
                        $cuttingFilled = true;
                        foreach ($g['lines'] as $line) {
                            $size = $line->Size;
                            if (isset($erpDetails['cutting'][$size])) {
                                $qty = $erpDetails['cutting'][$size];

                                \App\Models\SubconCuttingReport::updateOrCreate(
                                    ['order_id' => $order->id, 'prod_id' => $line->ProdId],
                                    [
                                        'size' => $size,
                                        'cutting_qty' => $qty,
                                    ]
                                );
                            }
                        }
                    }

                    // Process gramasi
                    if (! empty($erpDetails['gramasi'])) {
                        $gramasiFilled = true;
                        foreach ($g['lines'] as $line) {
                            $size = $line->Size;
                            if (isset($erpDetails['gramasi'][$size])) {
                                // Convert kg from D365 to grams locally (multiply by 1000)
                                $grams = (float) $erpDetails['gramasi'][$size] * 1000;

                                \App\Models\SubconCuttingReport::updateOrCreate(
                                    ['order_id' => $order->id, 'prod_id' => $line->ProdId],
                                    [
                                        'size' => $size,
                                        'gramasi' => $grams,
                                    ]
                                );
                            }
                        }
                    }
                }

                // Escalate workflow if anything filled
                if ($gramasiFilled) {
                    $sendEmail = false;
                    if (! in_array($order->workflow_stage, [SubconOrder::STAGE_WAITING_DISTRIBUTION, SubconOrder::STAGE_LABELS, SubconOrder::STAGE_COMPLETED], true)) {
                        $order->workflow_stage = SubconOrder::STAGE_WAITING_DISTRIBUTION;
                        $sendEmail = true;
                    }
                    if (empty($order->cutting_approved_at)) {
                        $order->cutting_approved_at = now();
                        $order->cutting_approved_by = 'ERP Sync';
                    }
                    if (empty($order->gramasi_approved_at)) {
                        $order->gramasi_approved_at = now();
                        $order->gramasi_approved_by = 'ERP Sync';
                    }
                    $order->save();
                    if ($sendEmail) {
                        try {
                            $recipients = \App\Http\Controllers\SubconApprovalController::labelGeneratorRecipient();
                            if (! empty($recipients)) {
                                Mail::to($recipients)->send(new \App\Mail\SubconStageStatusMailable($order, 'gramasi', 'approved'));
                            }
                        } catch (\Throwable $e) {
                            Log::error('Sync Subcon email failed: '.$e->getMessage());
                        }
                    }
                } elseif ($cuttingFilled) {
                    if (in_array($order->workflow_stage, [SubconOrder::STAGE_CUTTING, SubconOrder::STAGE_CUTTING_REVIEW])) {
                        $order->workflow_stage = SubconOrder::STAGE_GRAMASI;
                    }
                    if (empty($order->cutting_approved_at)) {
                        $order->cutting_approved_at = now();
                        $order->cutting_approved_by = 'ERP Sync';
                    }
                    $order->save();
                }
            }

            DB::commit();
            $this->info("Synced subcon orders. New orders: $createdOrders, new items: $createdItems, retitled: $retitled, deleted/removed: $deletedOrders.");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Error during insertion: '.$e->getMessage());
            if (app()->runningUnitTests()) {
                throw $e;
            }

            return Command::FAILURE;
        }
    }

    private function getD365Token()
    {
        $tenantId = config('services.d365.tenant_id');
        $clientId = config('services.d365.client_id');
        $clientSecret = config('services.d365.client_secret');
        $resource = config('services.d365.resource');

        $cacheKey = 'd365_access_token';
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $url = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";
        $response = Http::asForm()->post($url, [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'scope' => "{$resource}/.default",
        ]);

        if ($response->successful()) {
            $data = $response->json();
            Cache::put($cacheKey, $data['access_token'], $data['expires_in'] - 300);

            return $data['access_token'];
        }

        $this->error('D365 Auth Error: '.$response->body());

        return null;
    }

    private function fetchRecords($table, $filters = [], $select = [])
    {
        $resource = config('services.d365.resource');
        $url = "{$resource}/data/{$table}";

        $queryParams = ['cross-company' => 'true'];
        if (! empty($filters)) {
            $queryParams['$filter'] = implode(' and ', $filters);
        }
        if (! empty($select)) {
            $queryParams['$select'] = implode(',', $select);
        }

        $allRecords = [];
        $nextLink = $url.'?'.http_build_query($queryParams);

        while ($nextLink) {
            $token = $this->getD365Token();
            $response = Http::withToken($token)->acceptJson()->get($nextLink);

            if (! $response->successful()) {
                $this->error('D365 Fetch Error: '.$response->status().' '.$response->body());
                break;
            }

            $body = $response->json();
            if (isset($body['value'])) {
                $allRecords = array_merge($allRecords, $body['value']);
            }
            $nextLink = $body['@odata.nextLink'] ?? null;
        }

        return $allRecords;
    }
}
