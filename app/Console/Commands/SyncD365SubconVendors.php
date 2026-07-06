<?php

namespace App\Console\Commands;

use App\Exports\SubconVendorCredentialsExport;
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

class SyncD365SubconVendors extends Command
{
    protected $signature = 'd365:sync-subcon-vendors
                            {--company=mpg : D365 legal entity (dataAreaId). Vendor codes are company-scoped, so this must be pinned.}
                            {--months=12 : Only consider POs created within the last N months}
                            {--lenient : Widen classification to the whole Jasa Subkon family minus add-ons. Default is strict: ItemNumber=2312000100000 ("Item Jasa CMT") only}
                            {--password=password : Default login password for the generated vendor accounts}
                            {--min-vendors=15 : Safety floor. Abort WITHOUT touching the DB if fewer CMT vendors resolve (guards against an accidental flush from a bad/empty fetch)}
                            {--force : Skip the interactive "this will delete & recreate" confirmation}
                            {--dry-run : Fetch + classify only; print the resolved list and write nothing}';

    protected $description = 'Rebuild the subcon CMT vendor list from D365 PO line items (Item Jasa CMT), last N months, and export login credentials to Excel';

    /** The core CMT service item — the report\'s reliable "true CMT" classifier. */
    private const CMT_CORE_ITEM = '2312000100000';

    /** The "Jasa Subkon" service-item family (23120001000NN). */
    private const JASA_FAMILY_PREFIX = '23120001000';

    /** Add-process items in the family that are NOT core CMT (Sablon / Embro / Washing). */
    private const JASA_ADDON_ITEMS = ['2312000100001', '2312000100002', '2312000100004'];

    public function handle()
    {
        $company = $this->option('company');
        $months = (int) $this->option('months');
        $strict = ! $this->option('lenient');
        $password = (string) $this->option('password');
        $minVendors = (int) $this->option('min-vendors');

        if (empty($company)) {
            $this->error('--company is required (vendor codes are company-scoped in D365).');

            return Command::FAILURE;
        }

        $this->info("Rebuilding subcon CMT vendor list from D365 PO lines (company={$company}, last {$months} months, "
            .($strict ? 'strict' : 'lenient').' classification)...');

        $token = $this->getD365Token();
        if (! $token) {
            $this->error('Failed to authenticate with D365. Aborting — no DB changes made.');

            return Command::FAILURE;
        }

        // --- 1. Fetch CMT PO headers in window (pool=CMT bounds the fetch; line
        // items are the real classifier — see the EDA report). ----------------
        $cutoff = Carbon::now()->subMonths($months)->format('Y-m-d\TH:i:s\Z');
        $headerFilters = [
            "dataAreaId eq '{$company}'",
            "PurchPoolId eq 'CMT'",
            "CreatedDateTime1 ge {$cutoff}",
        ];

        $this->info('Fetching CMT PO headers...');
        $headers = $this->fetchRecords('PurchaseOrderHeadersV2', $headerFilters,
            ['PurchaseOrderNumber', 'OrderVendorAccountNumber', 'CreatedDateTime1']);
        $this->info('Fetched '.count($headers).' CMT PO headers.');

        if (empty($headers)) {
            $this->error('No CMT PO headers returned. Aborting — no DB changes made (accidental-flush guard).');

            return Command::FAILURE;
        }

        // PO -> vendor code (skip headers missing either).
        $poVendor = [];
        foreach ($headers as $h) {
            $po = $h['PurchaseOrderNumber'] ?? null;
            $code = $h['OrderVendorAccountNumber'] ?? null;
            if ($po && $code) {
                $poVendor[$po] = $code;
            }
        }

        // --- 2. Fetch lines for those POs and keep only POs carrying a real
        // CMT (Item Jasa) line — drops add-process-only & stray-stock POs. -----
        $this->info('Fetching PO lines to classify true CMT POs...');
        $cmtVendorCodes = [];       // vendor_code => true
        $poCountByVendor = [];      // vendor_code => # of CMT POs
        $poNumbers = array_keys($poVendor);

        foreach (array_chunk($poNumbers, 20) as $chunk) {
            $parts = array_map(fn ($po) => "PurchaseOrderNumber eq '$po'", $chunk);
            $lineFilters = ['('.implode(' or ', $parts).')', "dataAreaId eq '{$company}'"];
            $lines = $this->fetchRecords('PurchaseOrderLinesV2', $lineFilters,
                ['PurchaseOrderNumber', 'ItemNumber']);

            // Which POs in this chunk have at least one qualifying CMT line?
            $cmtPos = [];
            foreach ($lines as $l) {
                $item = (string) ($l['ItemNumber'] ?? '');
                if ($this->isCmtLine($item, $strict)) {
                    $cmtPos[$l['PurchaseOrderNumber']] = true;
                }
            }

            foreach (array_keys($cmtPos) as $po) {
                $code = $poVendor[$po] ?? null;
                if ($code) {
                    $cmtVendorCodes[$code] = true;
                    $poCountByVendor[$code] = ($poCountByVendor[$code] ?? 0) + 1;
                }
            }
        }

        $codes = array_keys($cmtVendorCodes);
        sort($codes);
        $this->info('Resolved '.count($codes).' distinct CMT vendors from PO line items.');

        // --- 3. Circuit breaker: refuse to flush on a thin/empty result. ------
        if (count($codes) < $minVendors) {
            $this->error('Only '.count($codes)." CMT vendors resolved — below the safety floor of {$minVendors}.");
            $this->error('Aborting WITHOUT touching the DB. If this is genuinely correct, re-run with --min-vendors='.count($codes).'.');

            return Command::FAILURE;
        }

        // --- 4. Enrich with vendor master details (name/contact). -------------
        $this->info('Fetching vendor master details from VendorsV2...');
        $master = [];
        foreach (array_chunk($codes, 20) as $chunk) {
            $parts = array_map(fn ($c) => "VendorAccountNumber eq '$c'", $chunk);
            $vFilters = ['('.implode(' or ', $parts).')', "dataAreaId eq '{$company}'"];
            $recs = $this->fetchRecords('VendorsV2', $vFilters);
            foreach ($recs as $r) {
                if (! empty($r['VendorAccountNumber'])) {
                    $master[$r['VendorAccountNumber']] = $r;
                }
            }
        }

        // --- 5. Dry run: show the resolved list, write nothing. ---------------
        if ($this->option('dry-run')) {
            $rows = array_map(function ($code) use ($master, $poCountByVendor) {
                $r = $master[$code] ?? [];

                return [
                    $code,
                    $r['VendorOrganizationName'] ?? $r['VendorSearchName'] ?? $code,
                    $r['PrimaryEmailAddress'] ?? '—',
                    $poCountByVendor[$code] ?? 0,
                ];
            }, $codes);
            $this->table(['Code', 'Name', 'Contact Email', 'CMT POs (12mo)'], $rows);
            $this->warn('Dry run: '.count($codes).' vendors resolved. Nothing written.');

            return Command::SUCCESS;
        }

        // --- 6. Confirm the destructive rebuild. ------------------------------
        $currentCount = Vendor::where('type', 'subcon')->count();
        if (! $this->option('force')) {
            $this->warn("This will DELETE all {$currentCount} current subcon vendors (and cascade their subcon_orders + cutting reports) "
                .'and recreate '.count($codes).' vendors with login password "'.$password.'".');
            if (! $this->confirm('Proceed?', false)) {
                $this->info('Cancelled — no DB changes made.');

                return Command::SUCCESS;
            }
        }

        // --- 7. Flush + rebuild in a single transaction. ----------------------
        $credentials = [];
        DB::beginTransaction();
        try {
            User::where('role', 'subcon_vendor')->delete();
            Vendor::where('type', 'subcon')->delete();

            foreach ($codes as $code) {
                $r = $master[$code] ?? [];
                $vendorName = $r['VendorOrganizationName'] ?? $r['VendorSearchName'] ?? $code;
                $phone = $r['PrimaryPhoneNumber'] ?? null;
                $contactEmail = $r['PrimaryEmailAddress'] ?? null;
                $address = $r['AddressStreet'] ?? null;
                $group = $r['VendorGroupId'] ?? 'subcon';

                $vendorId = (string) Str::uuid();
                $vendor = new Vendor([
                    'name' => $vendorName,
                    'vendor_code' => $code,
                    'group' => $group,
                    'type' => 'subcon',
                    'contact_info' => array_filter([
                        'phone' => $phone,
                        'email' => $contactEmail,
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
                    'role' => 'subcon_vendor',
                    'vendor_id' => $vendorId,
                ]);
                $user->id = (string) Str::uuid();
                $user->save();

                $credentials[] = [
                    'code' => $code,
                    'name' => $vendorName,
                    'group' => $group,
                    'contact_email' => $contactEmail,
                    'phone' => $phone,
                    'login_email' => $email,
                    'password' => $password,
                    'po_count' => $poCountByVendor[$code] ?? 0,
                ];
            }

            DB::commit();
            $this->info('Rebuilt subcon vendors: deleted '.$currentCount.', created '.count($credentials).'.');
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Failed to rebuild subcon vendors: '.$e->getMessage().' (rolled back — no changes).');

            return Command::FAILURE;
        }

        // --- 8. Export the list + credentials to Excel on the project root. ---
        try {
            $path = base_path('subcon_vendors_credentials.xlsx');
            $binary = Excel::raw(new SubconVendorCredentialsExport($credentials), ExcelWriter::XLSX);
            file_put_contents($path, $binary);
            $this->info('Wrote vendor credentials to '.$path);
        } catch (\Throwable $e) {
            $this->warn('Vendors rebuilt, but writing the Excel file failed: '.$e->getMessage());
        }

        $this->newLine();
        $this->info('Generated Subcon Vendor Logins:');
        $this->table(
            ['Vendor Code', 'Vendor Name', 'Login Email', 'Login Password', 'CMT POs'],
            array_map(fn ($c) => [$c['code'], $c['name'], $c['login_email'], $c['password'], $c['po_count']], $credentials)
        );

        return Command::SUCCESS;
    }

    /** Does this PO line item qualify as CMT? */
    private function isCmtLine(string $item, bool $strict): bool
    {
        if ($item === '') {
            return false;
        }
        if ($strict) {
            return $item === self::CMT_CORE_ITEM;
        }

        // Lenient: any item in the Jasa Subkon family except the known add-ons.
        return str_starts_with($item, self::JASA_FAMILY_PREFIX)
            && ! in_array($item, self::JASA_ADDON_ITEMS, true);
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
            return 'subcon';
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
