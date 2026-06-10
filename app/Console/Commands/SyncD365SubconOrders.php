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

        $this->info('Fetching PO headers...');
        $headers = $this->fetchRecords('PurchaseOrderHeadersV2', $filters);
        $this->info('Fetched '.count($headers).' header records.');

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

        // 4. Transform + persist.
        DB::beginTransaction();
        try {
            $createdOrders = 0;
            $createdItems = 0;
            $retitled = 0;

            foreach ($poMap as $poNumber => $vendorId) {
                $h = $poHeaders[$poNumber];

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
                    ]
                );
                if ($order->wasRecentlyCreated) {
                    $createdOrders++;
                } elseif ($style && $order->title !== $style) {
                    // Existing order whose style has since resolved in VSM — retitle it.
                    $order->update(['title' => $style]);
                    $retitled++;
                }

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
            }

            DB::commit();
            $this->info("Synced subcon orders. New orders: $createdOrders, new items: $createdItems, retitled: $retitled.");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Error during insertion: '.$e->getMessage());

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
