<?php

namespace App\Console\Commands;

use App\Models\PaymentTerm;
use App\Models\Vendor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Enriches EXISTING fabric vendor rows with the extra fields the PC module
 * needs (currency, D365 payment code -> payment_term_id, country, contact
 * person, supplier group, favourite flag) from D365 VendorsV2, mpg-scoped.
 *
 * Deliberately UPDATE-only: it never creates a vendor row. The fabric
 * vendor roster already exists and is already synced/auto-registered by
 * SyncD365Orders::autoRegisterVendors() — duplicating that here (or a
 * delete-and-rebuild pattern like d365:sync-fabric-vendors) would fight
 * the existing, additive registration flow rather than complement it.
 *
 * OPEN ITEM (flagged in the Phase 1 plan, not resolved here): only
 * VendorAccountNumber, VendorOrganizationName, VendorSearchName,
 * PrimaryEmailAddress, PrimaryPhoneNumber and AddressStreet are confirmed
 * VendorsV2 fields anywhere in this codebase (autoRegisterVendors()).
 * TermsOfPaymentCode, AddressCountryRegionId, PrimaryContactPersonName,
 * CurrencyCode and Blocked below are best-guess/typical-D365-naming
 * placeholders, NOT verified against a real response — confirm each
 * against the D365 $metadata endpoint before relying on this command.
 */
class PcEnrichVendors extends Command
{
    protected $signature = 'pc:enrich-vendors
                            {--company=mpg : D365 legal entity (dataAreaId) to restrict to.}';

    protected $description = 'Enrich existing fabric vendors with PC-specific fields from D365 VendorsV2 (update-only, never creates a vendor)';

    public function handle()
    {
        $this->info('Starting PC vendor enrichment...');

        $vendorCodes = Vendor::where('type', 'fabric')->pluck('vendor_code')->all();
        if (empty($vendorCodes)) {
            $this->info('No fabric vendors exist locally to enrich.');

            return Command::SUCCESS;
        }

        $token = $this->getD365Token();
        if (! $token) {
            $this->error('Failed to authenticate with D365.');

            return Command::FAILURE;
        }

        $company = $this->option('company');
        $byCode = [];
        foreach (array_chunk($vendorCodes, 20) as $chunk) {
            $parts = array_map(fn ($c) => "VendorAccountNumber eq '$c'", $chunk);
            $filters = ['('.implode(' or ', $parts).')'];
            if ($company) {
                $filters[] = "dataAreaId eq '{$company}'";
            }
            foreach ($this->fetchRecords('VendorsV2', $filters) as $row) {
                $code = $row['VendorAccountNumber'] ?? null;
                if ($code) {
                    $byCode[$code] = $row;
                }
            }
        }

        $this->info('Fetched D365 data for '.count($byCode).' of '.count($vendorCodes).' local fabric vendor(s).');

        $now = now();
        $updated = 0;
        foreach ($byCode as $code => $row) {
            $d365PaymentCode = $row['TermsOfPaymentCode'] ?? null;
            $paymentTermId = $d365PaymentCode
                ? PaymentTerm::where('d365_code', $d365PaymentCode)->value('id')
                : null;

            Vendor::where('vendor_code', $code)->where('type', 'fabric')->update([
                'country' => $row['AddressCountryRegionId'] ?? null,
                'contact_person' => $row['PrimaryContactPersonName'] ?? null,
                'currency_code' => $row['CurrencyCode'] ?? null,
                'd365_payment_code' => $d365PaymentCode,
                'payment_term_id' => $paymentTermId,
                'synced_at' => $now,
            ]);
            $updated++;
        }

        $this->info("Enriched {$updated} fabric vendor(s).");

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
