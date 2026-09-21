<?php

namespace App\Console\Commands;

use App\Models\PurchaseOrder;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ArchiveInvoicedOrders extends Command
{
    protected $signature = 'd365:archive-invoiced-orders
                            {--months=8 : Only consider local POs whose order_date is older than this many months}
                            {--company=mpg : D365 legal entity (dataAreaId) to check status against}
                            {--dry-run : Report what would be archived, write nothing}';

    protected $description = 'One-time cleanup: archive (hide, not delete) fabric POs that are both older than N months and already Invoiced in D365, decluttering the default Admin/Vendor PO lists';

    public function handle()
    {
        $months = (int) $this->option('months');
        $company = (string) $this->option('company');
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = Carbon::now()->subMonths($months)->toDateString();

        $candidates = PurchaseOrder::whereNull('archived_at')
            ->whereHas('vendor', fn ($q) => $q->where('type', 'fabric'))
            ->where('order_date', '<', $cutoff)
            ->pluck('id', 'po_number');

        $this->info("Found {$candidates->count()} local fabric PO(s) older than {$months} months, not yet archived.");

        if ($candidates->isEmpty()) {
            return Command::SUCCESS;
        }

        $token = $this->getD365Token();
        if (! $token) {
            $this->error('Failed to authenticate with D365.');

            return Command::FAILURE;
        }

        $statuses = [];
        foreach ($candidates->keys()->chunk(20) as $chunk) {
            $parts = array_map(fn ($po) => "PurchaseOrderNumber eq '$po'", $chunk->all());
            $filters = ['('.implode(' or ', $parts).')'];
            if ($company) {
                $filters[] = "dataAreaId eq '{$company}'";
            }
            foreach ($this->fetchRecords('PurchaseOrderHeadersV2', $filters, ['PurchaseOrderNumber', 'PurchaseOrderStatus']) as $r) {
                $statuses[$r['PurchaseOrderNumber']] = $r['PurchaseOrderStatus'] ?? null;
            }
        }

        $toArchive = [];
        foreach ($candidates as $poNumber => $id) {
            if (($statuses[$poNumber] ?? null) === 'Invoiced') {
                $toArchive[] = $id;
            }
        }

        if ($dryRun) {
            $this->warn('Dry run: would archive '.count($toArchive).' of '.$candidates->count().' checked PO(s). Nothing written.');

            return Command::SUCCESS;
        }

        if (! empty($toArchive)) {
            PurchaseOrder::whereIn('id', $toArchive)->update(['archived_at' => now()]);
        }

        $this->info('Archived '.count($toArchive).' of '.$candidates->count().' checked PO(s).');

        return Command::SUCCESS;
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
            Cache::put($cacheKey, $data['access_token'], ($data['expires_in'] ?? 3600) - 300);

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
