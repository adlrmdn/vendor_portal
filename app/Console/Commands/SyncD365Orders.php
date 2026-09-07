<?php

namespace App\Console\Commands;

use App\Models\PoItem;
use App\Models\PurchaseOrder;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SyncD365Orders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'd365:sync-orders
                            {--since= : Backfill: only pull POs created on/after this date (Y-m-d). Overrides the default 90-day window.}
                            {--months= : Backfill: pull POs created within the last N months. Overrides the default 90-day window.}
                            {--vendor=* : Limit to specific vendor code(s). Defaults to a 12-month window instead of 90 days, since a newly-added vendor may already have older confirmed POs.}
                            {--company=mpg : D365 legal entity (dataAreaId) to restrict to. REQUIRED to avoid cross-company vendor-code collisions (e.g. V0246 = KNK in mpg but PUMA CAT in mpr) — see d365:sync-subcon-orders.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch POs from D365 and sync to the local database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting D365 ETL pipeline...');

        $token = $this->getD365Token();
        if (! $token) {
            $this->error('Failed to authenticate with D365.');

            return Command::FAILURE;
        }

        // 1. Fetch Headers. Default window is the last 90 days (incremental hourly
        // cadence); --since / --months widen it for a one-off historical backfill.
        // Widened from 30 days so a PO that sits pending/processing for a while
        // still falls inside the routine fetch window and picks up a D365-side
        // vendor reassignment (see the existing-PO update loop below) without
        // needing a manual backfill every time.
        $vendorCodes = $this->option('vendor');

        if ($since = $this->option('since')) {
            $cutoffDate = Carbon::parse($since);
        } elseif ($months = $this->option('months')) {
            $cutoffDate = Carbon::now()->subMonths((int) $months);
        } elseif (! empty($vendorCodes)) {
            $cutoffDate = Carbon::now()->subMonths(12);
        } else {
            $cutoffDate = Carbon::now()->subDays(90);
        }
        $cutoff = $cutoffDate->format('Y-m-d\TH:i:s\Z');
        $this->info('Sync window: POs created on/after '.$cutoffDate->toDateString().'.');
        $filters = [
            "(PurchPoolId eq 'Fab-Local' or PurchPoolId eq 'Fab-Import' or PurchPoolId eq 'Fab-Repro')",
            "DocumentApprovalStatus eq Microsoft.Dynamics.DataEntities.VersioningDocumentState'Confirmed'",
            "CreatedDateTime1 ge $cutoff",
        ];

        if (! empty($vendorCodes)) {
            $filters[] = '('.implode(' or ', array_map(
                fn ($code) => "OrderVendorAccountNumber eq '$code'",
                $vendorCodes
            )).')';
            $this->info('Restricting to vendor(s): '.implode(', ', $vendorCodes));
        }

        // Vendor account codes are company-scoped in D365 — the same code maps to
        // different legal vendors across companies (e.g. V0246 = KNK in mpg but
        // PUMA CAT in mpr). Restrict to one legal entity, same as the subcon sync.
        $company = $this->option('company');
        if ($company) {
            $filters[] = "dataAreaId eq '$company'";
        }

        $this->info('Fetching PO Headers...');
        $headers = $this->fetchRecords('PurchaseOrderHeadersV2', $filters);
        $this->info('Fetched '.count($headers).' header records.');

        if (empty($headers)) {
            $this->info('No new orders to sync.');

            return Command::SUCCESS;
        }

        // 2. DB Validations (Vendors & Existing POs)
        $vendors = Vendor::pluck('id', 'vendor_code')->toArray();
        $existingPos = PurchaseOrder::pluck('id', 'po_number')->toArray();

        $validToInsert = [];
        $poMap = [];
        $vendorAccountByPo = [];

        foreach ($headers as $h) {
            $poNumber = $h['PurchaseOrderNumber'];
            $vendorAccount = $h['OrderVendorAccountNumber'];

            if (! isset($vendors[$vendorAccount])) {
                continue; // Skip if vendor not in DB
            }

            $vendorAccountByPo[$poNumber] = $vendorAccount;

            if (isset($existingPos[$poNumber])) {
                $poMap[$poNumber] = $existingPos[$poNumber];
            } else {
                $uuid = (string) Str::uuid();
                $poMap[$poNumber] = $uuid;
                $h['local_uuid'] = $uuid;
                $h['local_vendor_id'] = $vendors[$vendorAccount];
                $validToInsert[] = $h;
            }
        }

        $this->info('Found '.count($poMap).' valid POs (New: '.count($validToInsert).').');
        if (empty($poMap)) {
            return Command::SUCCESS;
        }

        // 3. PLM Lookup
        $this->info('Fetching PLM Mapping...');
        $plmFilters = ["ModifiedDateTimeHeader ge $cutoff"];
        if ($company) {
            $plmFilters[] = "dataAreaId eq '$company'";
        }
        $plmRecords = $this->fetchRecords('TOC_PurchRequisitions', $plmFilters, ['PurchReqId', 'TOC_PLM_ID']);

        $plmMap = [];
        foreach ($plmRecords as $r) {
            if (isset($r['PurchReqId']) && isset($r['TOC_PLM_ID'])) {
                $plmMap[$r['PurchReqId']] = $r['TOC_PLM_ID'];
            }
        }
        $this->info('Built PLM Map with '.count($plmMap).' entries.');

        // 4. Fetch Lines in Batches
        $poNumbers = array_keys($poMap);
        $chunks = array_chunk($poNumbers, 20);

        $allLines = [];
        foreach ($chunks as $chunk) {
            $this->info('Fetching batch for '.count($chunk).' POs...');
            $parts = array_map(function ($po) {
                return "PurchaseOrderNumber eq '$po'";
            }, $chunk);
            $lineFilters = ['('.implode(' or ', $parts).')'];
            if ($company) {
                $lineFilters[] = "dataAreaId eq '$company'";
            }
            $filter = implode(' and ', $lineFilters);

            $batchLines = $this->fetchRecords('PurchaseOrderLinesV2', [$filter]);
            $allLines = array_merge($allLines, $batchLines);
        }
        $this->info('Fetched '.count($allLines).' lines.');

        // 5. Data transformation and insertion
        DB::beginTransaction();
        try {
            // Calc totals
            $poTotals = [];
            foreach ($allLines as $l) {
                $amt = $l['LineAmount'] ?? 0.0;
                $poTotals[$l['PurchaseOrderNumber']] = ($poTotals[$l['PurchaseOrderNumber']] ?? 0.0) + $amt;
            }

            // Insert POs
            foreach ($validToInsert as $h) {
                $poNumber = $h['PurchaseOrderNumber'];
                $total = $poTotals[$poNumber] ?? 0.0;

                $orderDate = Carbon::parse($h['CreatedDateTime1'])->format('Y-m-d');
                $deliveryDate = isset($h['RequestedDeliveryDate']) ? Carbon::parse($h['RequestedDeliveryDate'])->format('Y-m-d') : null;

                $po = new PurchaseOrder;
                $po->id = $h['local_uuid'];
                $po->po_number = $poNumber;
                $po->vendor_id = $h['local_vendor_id'];
                $po->status = 'pending';
                $po->total_amount = $total;
                $po->currency = $h['CurrencyCode'];
                $po->order_date = $orderDate;
                $po->delivery_date = $deliveryDate;
                $po->notes = $h['ReasonComment'] ?? null;
                $po->reference = $h['VendorOrderReference'] ?? null; // PC (Preliminary Contract)
                $po->save();
            }
            $this->info('Inserted '.count($validToInsert).' NEW POs.');

            // PC (Preliminary Contract) reference per PO, keyed by PO number.
            $refMap = [];
            foreach ($headers as $h) {
                $refMap[$h['PurchaseOrderNumber']] = $h['VendorOrderReference'] ?? null;
            }

            // Update totals + PC reference for existing POs (lines/ref may have changed).
            $existingVendorIds = PurchaseOrder::whereIn('po_number', array_keys($existingPos))
                ->pluck('vendor_id', 'po_number')
                ->toArray();
            $reassigned = 0;
            foreach ($existingPos as $poNumber => $poId) {
                $updates = [];
                if (isset($poTotals[$poNumber])) {
                    $updates['total_amount'] = $poTotals[$poNumber];
                }
                if (array_key_exists($poNumber, $refMap)) {
                    $updates['reference'] = $refMap[$poNumber];
                }

                // D365 can reassign a PO to a different vendor account after it
                // was first synced (e.g. a data-entry correction). Since
                // vendor_id gates portal visibility, a stale value here silently
                // hides the PO from its real vendor while leaving it visible to
                // whoever it was originally (wrongly) attached to.
                if (isset($vendorAccountByPo[$poNumber])) {
                    $newVendorId = $vendors[$vendorAccountByPo[$poNumber]];
                    $currentVendorId = $existingVendorIds[$poNumber] ?? null;
                    if ($currentVendorId !== $newVendorId) {
                        Log::warning('Fabric PO vendor reassigned by D365 sync', [
                            'po_number' => $poNumber,
                            'from_vendor_id' => $currentVendorId,
                            'to_vendor_id' => $newVendorId,
                        ]);
                        $updates['vendor_id'] = $newVendorId;
                        $reassigned++;
                    }
                }

                if (! empty($updates)) {
                    PurchaseOrder::where('id', $poId)->update($updates);
                }
            }
            if ($reassigned > 0) {
                $this->warn("Reassigned {$reassigned} existing PO(s) to a different vendor (D365 vendor account changed).");
            }

            // Insert Items
            foreach ($allLines as $l) {
                $poNumber = $l['PurchaseOrderNumber'];
                if (isset($poMap[$poNumber])) {
                    $poId = $poMap[$poNumber];
                    $reqId = $l['PurchaseRequisitionId'] ?? '';
                    $plmId = $plmMap[$reqId] ?? $reqId;

                    // A PO can repeat the same (ItemNumber, batch) across distinct
                    // D365 lines (e.g. a quantity split), so LineNumber — D365's
                    // actual per-line identity — must be part of the match key too,
                    // or firstOrCreate collapses them into one row and drops the rest.
                    PoItem::firstOrCreate([
                        'po_id' => $poId,
                        'item_number' => $l['ItemNumber'],
                        'batch' => $l['ItemBatchNumber'] ?? null,
                        'line_number' => $l['LineNumber'] ?? null,
                    ], [
                        'id' => (string) Str::uuid(),
                        'description' => $l['LineDescription'],
                        'plm_number' => $plmId,
                        'quantity' => $l['OrderedPurchaseQuantity'] ?? 0.0,
                        'underdelivery' => $l['AllowedUnderdeliveryPercentage'] ?? 0.0,
                        'overdelivery' => $l['AllowedOverdeliveryPercentage'] ?? 0.0,
                        'unit' => $l['PurchaseUnitSymbol'],
                        'unit_price' => $l['PurchasePrice'] ?? 0.0,
                        'total_price' => $l['LineAmount'] ?? 0.0,
                        'status' => 'pending',
                    ]);
                }
            }
            $this->info('Inserted valid items (skipping duplicates).');

            DB::commit();
            $this->info('D365 ETL pipeline finished successfully.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Error during database insertion: '.$e->getMessage());

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
            $token = $data['access_token'];
            $expiresIn = $data['expires_in'] - 300; // Buffer of 5 minutes

            Cache::put($cacheKey, $token, $expiresIn);

            return $token;
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
        $queryString = http_build_query($queryParams);
        $nextLink = $url.'?'.$queryString;

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
