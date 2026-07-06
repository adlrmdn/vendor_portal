<?php

namespace App\Console\Commands;

use App\Exports\FabricVendorCredentialsExport;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;

class SyncD365FabricVendors extends Command
{
    protected $signature = 'd365:sync-fabric-vendors
                            {--company=mpg : D365 legal entity (dataAreaId). Vendor codes are company-scoped, so this must be pinned — cross-company enrichment resolves the wrong legal vendor for a code.}
                            {--months=12 : Only consider POs created within the last N months}
                            {--password=password : Default login password for the generated vendor accounts}
                            {--min-vendors=5 : Safety floor. Abort WITHOUT touching the DB if fewer fabric vendors resolve (guards against an accidental flush from a bad/empty fetch)}
                            {--force : Skip the interactive "this will delete & recreate" confirmation}
                            {--dry-run : Fetch + classify only; print the resolved list and write nothing}';

    protected $description = 'Rebuild the fabric vendor list from D365 PO headers (pools Fab-Local/Fab-Import/Fab-Repro), last N months, and export login credentials to Excel';

    /** Fabric purchasing pools — the sole classifier (vendor master "type" may be stale). */
    private const FABRIC_POOLS = ['Fab-Local', 'Fab-Import', 'Fab-Repro'];

    /** Pool id -> short label stored on Vendor.group. */
    private const POOL_LABELS = [
        'Fab-Local' => 'Local',
        'Fab-Import' => 'Import',
        'Fab-Repro' => 'Repro',
    ];

    public function handle()
    {
        $company = (string) $this->option('company');
        $months = (int) $this->option('months');
        $password = (string) $this->option('password');
        $minVendors = (int) $this->option('min-vendors');

        $this->info('Rebuilding fabric vendor list from D365 PO headers (pools '.implode('/', self::FABRIC_POOLS).", last {$months} months"
            .($company !== '' ? ", company={$company}" : ', cross-company').')...');

        $token = $this->getD365Token();
        if (! $token) {
            $this->error('Failed to authenticate with D365. Aborting — no DB changes made.');

            return Command::FAILURE;
        }

        // --- 1. Fetch fabric PO headers in window. Every distinct vendor on a
        // header in a fabric pool IS a fabric vendor (pool is the classifier). --
        $cutoff = Carbon::now()->subMonths($months)->format('Y-m-d\TH:i:s\Z');
        $poolFilter = '('.implode(' or ', array_map(fn ($p) => "PurchPoolId eq '$p'", self::FABRIC_POOLS)).')';
        $headerFilters = [$poolFilter, "CreatedDateTime1 ge {$cutoff}"];
        if ($company !== '') {
            $headerFilters[] = "dataAreaId eq '{$company}'";
        }

        $this->info('Fetching fabric PO headers...');
        $headers = $this->fetchRecords('PurchaseOrderHeadersV2', $headerFilters,
            ['PurchaseOrderNumber', 'OrderVendorAccountNumber', 'PurchPoolId', 'CreatedDateTime1']);
        $this->info('Fetched '.count($headers).' fabric PO headers.');

        if (empty($headers)) {
            $this->error('No fabric PO headers returned. Aborting — no DB changes made (accidental-flush guard).');

            return Command::FAILURE;
        }

        // vendor_code => ['pools' => set, 'po_count' => int]
        $vendorData = [];
        foreach ($headers as $h) {
            $code = $h['OrderVendorAccountNumber'] ?? null;
            $pool = $h['PurchPoolId'] ?? null;
            if (! $code) {
                continue;
            }
            if (! isset($vendorData[$code])) {
                $vendorData[$code] = ['pools' => [], 'po_count' => 0];
            }
            $vendorData[$code]['po_count']++;
            if ($pool && isset(self::POOL_LABELS[$pool])) {
                $vendorData[$code]['pools'][self::POOL_LABELS[$pool]] = true;
            }
        }

        $codes = array_keys($vendorData);
        sort($codes);
        $this->info('Resolved '.count($codes).' distinct fabric vendors from PO headers.');

        // --- 2. Circuit breaker: refuse to flush on a thin/empty result. ------
        if (count($codes) < $minVendors) {
            $this->error('Only '.count($codes)." fabric vendors resolved — below the safety floor of {$minVendors}.");
            $this->error('Aborting WITHOUT touching the DB. If this is genuinely correct, re-run with --min-vendors='.count($codes).'.');

            return Command::FAILURE;
        }

        // --- 3. Enrich with vendor master details (name/contact). -------------
        $this->info('Fetching vendor master details from VendorsV2...');
        $master = [];
        foreach (array_chunk($codes, 20) as $chunk) {
            $parts = array_map(fn ($c) => "VendorAccountNumber eq '$c'", $chunk);
            $vFilters = ['('.implode(' or ', $parts).')'];
            if ($company !== '') {
                $vFilters[] = "dataAreaId eq '{$company}'";
            }
            $recs = $this->fetchRecords('VendorsV2', $vFilters);
            foreach ($recs as $r) {
                $code = $r['VendorAccountNumber'] ?? null;
                // Cross-company can return the same code per legal entity; keep first.
                if ($code && ! isset($master[$code])) {
                    $master[$code] = $r;
                }
            }
        }

        // --- 4. Dry run: show the resolved list, write nothing. ---------------
        if ($this->option('dry-run')) {
            $rows = array_map(function ($code) use ($master, $vendorData) {
                $r = $master[$code] ?? [];

                return [
                    $code,
                    $r['VendorOrganizationName'] ?? $r['VendorSearchName'] ?? $code,
                    implode('/', array_keys($vendorData[$code]['pools'])) ?: '—',
                    $r['PrimaryEmailAddress'] ?? '—',
                    $vendorData[$code]['po_count'],
                ];
            }, $codes);
            $this->table(['Code', 'Name', 'Pool', 'Contact Email', 'Fabric POs (12mo)'], $rows);
            $this->warn('Dry run: '.count($codes).' vendors resolved. Nothing written.');

            return Command::SUCCESS;
        }

        // --- 5. Confirm the destructive rebuild. ------------------------------
        $currentCount = Vendor::where('type', 'fabric')->count();
        if (! $this->option('force')) {
            $this->warn("This will DELETE all {$currentCount} current fabric vendors (and cascade their purchase_orders + po_items + rolls) "
                .'and recreate '.count($codes).' vendors with login password "'.$password.'".');
            if (! $this->confirm('Proceed?', false)) {
                $this->info('Cancelled — no DB changes made.');

                return Command::SUCCESS;
            }
        }

        // --- 6. Flush + rebuild in a single transaction. ----------------------
        $credentials = [];
        DB::beginTransaction();
        try {
            User::where('role', 'fabric_vendor')->delete();
            Vendor::where('type', 'fabric')->delete();

            foreach ($codes as $code) {
                $r = $master[$code] ?? [];
                $vendorName = $r['VendorOrganizationName'] ?? $r['VendorSearchName'] ?? $code;
                $phone = $r['PrimaryPhoneNumber'] ?? null;
                $contactEmail = $r['PrimaryEmailAddress'] ?? null;
                $address = $r['AddressStreet'] ?? null;
                $poolLabel = implode('/', array_keys($vendorData[$code]['pools']));

                $vendorId = (string) Str::uuid();
                $vendor = new Vendor([
                    'name' => $vendorName,
                    'vendor_code' => $code,
                    'group' => $poolLabel ?: null,
                    'type' => 'fabric',
                    'contact_info' => array_filter([
                        'contact_person' => null,
                        'email' => $contactEmail,
                        'phone' => $phone,
                        'address' => $address,
                    ], fn ($v) => $v !== null),
                    'is_active' => true,
                ]);
                $vendor->id = $vendorId;
                $vendor->save();

                // Unique login email derived from the vendor name.
                $cleanName = $this->abbreviateVendorName($vendorName);
                $email = "vendor@{$cleanName}.com";
                $baseEmail = $email;
                $counter = 1;
                while (User::where('email', $email)->exists()) {
                    $email = str_replace('@', $counter.'@', $baseEmail);
                    $counter++;
                }

                $user = new User([
                    'name' => $vendorName,
                    'email' => $email,
                    'password' => bcrypt($password),
                    'role' => 'fabric_vendor',
                    'vendor_id' => $vendorId,
                ]);
                $user->id = (string) Str::uuid();
                $user->save();

                $credentials[] = [
                    'code' => $code,
                    'name' => $vendorName,
                    'group' => $poolLabel,
                    'contact_email' => $contactEmail,
                    'phone' => $phone,
                    'login_email' => $email,
                    'password' => $password,
                    'po_count' => $vendorData[$code]['po_count'],
                ];
            }

            DB::commit();
            $this->info('Rebuilt fabric vendors: deleted '.$currentCount.', created '.count($credentials).'.');
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Failed to rebuild fabric vendors: '.$e->getMessage().' (rolled back — no changes).');

            return Command::FAILURE;
        }

        // --- 7. Export the list + credentials to Excel on the project root. ---
        try {
            $path = base_path('fabric_vendors_credentials.xlsx');
            $binary = Excel::raw(new FabricVendorCredentialsExport($credentials), ExcelWriter::XLSX);
            file_put_contents($path, $binary);
            $this->info('Wrote vendor credentials to '.$path);
        } catch (\Throwable $e) {
            $this->warn('Vendors rebuilt, but writing the Excel file failed: '.$e->getMessage());
        }

        $this->newLine();
        $this->info('Generated Fabric Vendor Logins:');
        $this->table(
            ['Vendor Code', 'Vendor Name', 'Pool', 'Login Email', 'Login Password', 'Fabric POs'],
            array_map(fn ($c) => [$c['code'], $c['name'], $c['group'], $c['login_email'], $c['password'], $c['po_count']], $credentials)
        );

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

        try {
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
        } catch (\Throwable $e) {
            $this->error('D365 Auth Exception: '.$e->getMessage());
        }

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
            if (! $token) {
                break;
            }
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

    /** Abbreviate long vendor organization names for email slugs. */
    private function abbreviateVendorName(string $name): string
    {
        $words = array_filter(explode(' ', strtolower($name)));
        $prefixes = ['pt', 'cv', 'fa', 'ud', 'pd', 'koperasi'];

        if (count($words) > 1 && in_array($words[0], $prefixes, true)) {
            array_shift($words);
        }

        if (empty($words)) {
            return 'fabric';
        }

        $concat = implode('', $words);
        $cleanConcat = preg_replace('/[^a-z0-9]/', '', $concat);

        if (strlen($cleanConcat) <= 15) {
            return $cleanConcat;
        }

        $firstWord = preg_replace('/[^a-z0-9]/', '', $words[0]);
        $initials = '';
        for ($i = 1; $i < count($words); $i++) {
            $char = substr($words[$i], 0, 1);
            $cleanChar = preg_replace('/[^a-z0-9]/', '', $char);
            $initials .= $cleanChar;
        }

        $result = $firstWord.$initials;
        if (strlen($result) > 15) {
            $result = substr($result, 0, 15);
        }

        return $result;
    }
}
