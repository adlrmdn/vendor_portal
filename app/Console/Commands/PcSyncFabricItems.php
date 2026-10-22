<?php

namespace App\Console\Commands;

use App\Models\PcFabricItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * PC fabric item master feed — D365 ReleasedProductsV2 + ProductTranslations,
 * the same proven two-call combo already used by
 * D365JobTransactionService::fetchItemMaster() (item_name must come from
 * ProductTranslations.ProductName filtered LanguageId=en-US, never
 * ReleasedProductsV2.SearchName/ProductSearchName — that field is documented
 * there as unreliable). Additive upsert only, never deletes — unlike
 * d365:sync-fabric-vendors' destructive rebuild pattern.
 *
 * OPEN ITEMS (flagged in the Phase 1 plan, not resolved here — this command
 * is structurally complete but needs confirming against the real D365
 * metadata endpoint or the data team before its schedule entry goes live):
 *
 *   1. The custom TOC_-prefixed field names below for composition,
 *      construction, weave, cut_width, full_width, weight_gsm, process and
 *      finish are PLACEHOLDERS — fetchItemMaster() only ever confirmed
 *      TOC_InventoryGroup exists; none of these eight have been verified
 *      against a real ReleasedProductsV2 response.
 *   2. There is no confirmed "this is a fabric item" filter at the
 *      ReleasedProductsV2 level anywhere in this codebase (fetchItemMaster()
 *      is always called with an already-known item-number list, never asked
 *      to enumerate the catalog). Without --item= this command pulls the
 *      WHOLE released-product catalog across every company it can see,
 *      filtered only by dataAreaId — a correct but expensive stopgap until a
 *      real fabric-pool/category filter is confirmed.
 */
class PcSyncFabricItems extends Command
{
    protected $signature = 'pc:sync-fabric-items
                            {--company=mpg : D365 legal entity (dataAreaId) to restrict to.}
                            {--item=* : Limit to specific item number(s) instead of the whole catalog.}';

    protected $description = 'Sync the PC fabric item master from D365 ReleasedProductsV2 + ProductTranslations (additive upsert only)';

    // TODO(open item 1 above): confirm these field names before relying on them.
    private const PLACEHOLDER_FIELD_MAP = [
        'composition' => 'TOC_Composition',
        'construction' => 'TOC_Construction',
        'weave' => 'TOC_Weave',
        'cut_width' => 'TOC_CuttWidth',
        'full_width' => 'TOC_FullWidth',
        'weight_gsm' => 'TOC_Weight',
        'process' => 'TOC_Process',
        'finish' => 'TOC_Finish',
    ];

    public function handle()
    {
        $this->info('Starting PC fabric item sync...');

        $token = $this->getD365Token();
        if (! $token) {
            $this->error('Failed to authenticate with D365.');

            return Command::FAILURE;
        }

        $company = $this->option('company');
        $itemNumbers = $this->option('item');

        $filters = [];
        if (! empty($itemNumbers)) {
            $filters[] = '('.implode(' or ', array_map(
                fn ($it) => 'ItemNumber eq '.$this->odataLit($it),
                $itemNumbers
            )).')';
        }
        if ($company) {
            $filters[] = "dataAreaId eq '{$company}'";
        }

        $this->info('Fetching ReleasedProductsV2...');
        $products = $this->fetchRecords('ReleasedProductsV2', $filters);
        $this->info('Fetched '.count($products).' product record(s).');

        if (empty($products)) {
            $this->info('No fabric items to sync.');

            return Command::SUCCESS;
        }

        // cross-company=true returns one row per legal entity for the same
        // item number — prefer the mpg row, matching fetchItemMaster()'s
        // documented tie-break (fabric is mpg-scoped per CLAUDE.md).
        $byItem = [];
        foreach ($products as $row) {
            $item = $row['ItemNumber'] ?? null;
            if ($item === null) {
                continue;
            }
            $isMpg = strcasecmp((string) ($row['dataAreaId'] ?? ''), 'mpg') === 0;
            if (! isset($byItem[$item]) || $isMpg) {
                $byItem[$item] = $row;
            }
        }

        $names = $this->fetchProductNames(array_keys($byItem));

        $now = now();
        $synced = 0;
        foreach ($byItem as $itemNumber => $row) {
            $attributes = [
                'item_name' => $names[$itemNumber] ?? null,
                'uom_code' => $row['UnitId'] ?? null,
                'is_active' => true,
                'synced_at' => $now,
            ];

            foreach (self::PLACEHOLDER_FIELD_MAP as $column => $d365Field) {
                $attributes[$column] = $row[$d365Field] ?? null;
            }

            PcFabricItem::updateOrCreate(['item_number' => $itemNumber], $attributes);
            $synced++;
        }

        $this->info("Synced {$synced} fabric item(s).");

        return Command::SUCCESS;
    }

    /**
     * @param  array<int, string>  $itemNumbers
     * @return array<string, string>
     */
    private function fetchProductNames(array $itemNumbers): array
    {
        $names = [];
        foreach (array_chunk($itemNumbers, 20) as $chunk) {
            $filters = array_map(fn ($it) => 'ProductNumber eq '.$this->odataLit($it), $chunk);
            $filterStr = '('.implode(' or ', $filters).")  and LanguageId eq 'en-US'";

            foreach ($this->fetchRecords('ProductTranslations', [$filterStr]) as $row) {
                $item = $row['ProductNumber'] ?? null;
                $name = $row['ProductName'] ?? null;
                if ($item !== null && ! empty($name)) {
                    $names[$item] = $name;
                }
            }
        }

        return $names;
    }

    private function odataLit(string $value): string
    {
        return "'".str_replace("'", "''", $value)."'";
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
            $expiresIn = $data['expires_in'] - 300;

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
