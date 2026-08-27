<?php

namespace App\Services;

use App\Models\SubconOrder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class D365JobTransactionService
{
    private const JOBT_API_URL = 'http://172.17.0.1:8072/process';

    private const JOBT_API_KEY = 'JOB-Secret-2026';

    /**
     * Pemalang distribution warehouses that ship in sacks (karung) rather than
     * blisters, matched by store CODE — on TOC_PILines the StoreName is the
     * origin ("WH REPLENISH PEMALANG") for every row, so the name is useless
     * here. WH0503 = WH Replenish, WH0502 / WH0502-INB = WH Online (the StoreID
     * appears both with and without the -INB suffix across PIs, so match both).
     * Their Blister count = ceil(qty / sack_capacity), with Coli following 1:1.
     */
    private const SACK_STORES = ['WH0503', 'WH0502', 'WH0502-INB'];

    /**
     * Store CODEs for the two central distribution warehouses — WH0503 = WH
     * Replenish, WH0502-INB = WH (Inbound) Online. Exposed so the label print can
     * offer a warehouse-only run (these are the SACK_STORES).
     *
     * @return array<int, string>
     */
    public function warehouseStoreIds(): array
    {
        return self::SACK_STORES;
    }

    /** Fallback pieces-per-blister when an order has none (matches the print). */
    private const DEFAULT_BLISTER_CAPACITY = 10;

    /** Fallback pieces-per-sack for the sack stores when an order has none. */
    private const DEFAULT_SACK_CAPACITY = 50;

    /**
     * Get D365 environment resource URL from config.
     */
    private function getResource(): string
    {
        return config('services.d365.resource') ?? 'https://megaperintis.operations.dynamics.com';
    }

    /**
     * Get OAuth token for D365 environment.
     */
    private function getD365Token(): ?string
    {
        $tenantId = config('services.d365.tenant_id');
        $clientId = config('services.d365.client_id');
        $clientSecret = config('services.d365.client_secret');
        $resource = $this->getResource();

        $cacheKey = 'd365_access_token_'.md5($resource);
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
                // Cache slightly less than token duration
                Cache::put($cacheKey, $data['access_token'], ($data['expires_in'] ?? 3600) - 300);

                return $data['access_token'];
            }

            Log::error('D365 Token Auth Error: '.$response->body());
        } catch (\Throwable $e) {
            Log::error('D365 Token Exception: '.$e->getMessage());
        }

        return null;
    }

    /**
     * D365 returns the Operation code with inconsistent casing across vendors/
     * companies (e.g. 'cmt-pak' vs 'CMT-Pak') — match case-insensitively rather
     * than trusting one canonical casing.
     */
    private function isOperation(string $value, string $target): bool
    {
        return strcasecmp($value, $target) === 0;
    }

    /**
     * Render a value as an OData key literal (percent-encodes string values).
     */
    private function odataLit($val): string
    {
        if (is_bool($val)) {
            return $val ? 'true' : 'false';
        }
        if (is_string($val)) {
            $escaped = str_replace("'", "''", $val);

            return "'".rawurlencode($escaped)."'";
        }

        return (string) $val;
    }

    /**
     * Get X-Dynamic-Key header (today - 2 days formatted as YYMMDD).
     */
    private function getDynamicKey(): string
    {
        return date('ymd', strtotime('-2 days'));
    }

    /**
     * Send 'start' command to the jobt-api.
     */
    public function startJobs(string $productionGroup): bool
    {
        $dkey = $this->getDynamicKey();
        Log::info("Sending start jobs command to jobt-api for PRG: {$productionGroup} (DynamicKey: {$dkey})");

        try {
            // jobt-api's Playwright flow can take well over a minute per job row
            // (observed ~97s for 2 rows) — match the 180s/200s/210s HTTP/job/
            // retry_after ordering already used for the DTT label-gen call.
            $response = Http::withHeaders([
                'X-API-Key' => self::JOBT_API_KEY,
                'X-Dynamic-Key' => $dkey,
                'Content-Type' => 'application/json',
            ])->timeout(180)->post(self::JOBT_API_URL, [
                'production_group' => $productionGroup,
                'command' => 'start',
            ]);

            if ($response->successful()) {
                // jobt-api returns HTTP 200 even when its own Playwright flow failed —
                // the real outcome is in the body's "success" field, not the status code.
                if (($response->json('success') ?? true) === false) {
                    Log::error("Failed to start jobs for PRG {$productionGroup} (jobt-api reported failure): ".$response->body());

                    return false;
                }

                Log::info("Successfully started jobs for PRG {$productionGroup}: ".$response->body());

                return true;
            }

            Log::error("Failed to start jobs for PRG {$productionGroup}. Status: {$response->status()}, Body: ".$response->body());
        } catch (\Throwable $e) {
            Log::error("Exception starting jobs for PRG {$productionGroup}: ".$e->getMessage());
        }

        return false;
    }

    /**
     * Send 'trigger' command to the jobt-api.
     */
    public function triggerJobs(string $productionGroup, array $targets): bool
    {
        $dkey = $this->getDynamicKey();
        Log::info("Sending trigger jobs command to jobt-api for PRG: {$productionGroup} with targets: ".json_encode($targets));

        try {
            $response = Http::withHeaders([
                'X-API-Key' => self::JOBT_API_KEY,
                'X-Dynamic-Key' => $dkey,
                'Content-Type' => 'application/json',
            ])->timeout(120)->post(self::JOBT_API_URL, [
                'production_group' => $productionGroup,
                'command' => 'trigger',
                'targets' => $targets,
            ]);

            if ($response->successful()) {
                Log::info("Successfully triggered jobs for PRG {$productionGroup}: ".$response->body());

                return true;
            }

            Log::error("Failed to trigger jobs for PRG {$productionGroup}. Status: {$response->status()}, Body: ".$response->body());
        } catch (\Throwable $e) {
            Log::error("Exception triggering jobs for PRG {$productionGroup}: ".$e->getMessage());
        }

        return false;
    }

    /**
     * PATCH write to D365 OData entity.
     */
    private function patchOData(string $entity, array $key, array $changes): bool
    {
        $token = $this->getD365Token();
        if (! $token) {
            Log::error("Could not obtain UAT token for patching {$entity}");

            return false;
        }

        $keystr = implode(',', array_map(function ($k, $v) {
            return "{$k}=".$this->odataLit($v);
        }, array_keys($key), $key));

        $url = $this->getResource()."/data/{$entity}({$keystr})?cross-company=true";

        try {
            $response = Http::withToken($token)
                ->withHeaders([
                    'OData-MaxVersion' => '4.0',
                    'OData-Version' => '4.0',
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->patch($url, $changes);

            if ($response->successful() || $response->status() === 204) {
                Log::info("PATCH {$entity} successful. Status: {$response->status()}");

                return true;
            }

            Log::error("PATCH {$entity} failed. Status: {$response->status()}, URL: {$url}, Body: ".$response->body());
        } catch (\Throwable $e) {
            Log::error("PATCH {$entity} exception: ".$e->getMessage());
        }

        return false;
    }

    /**
     * Generic OData GET returning the entity's `value` array ([] on any failure).
     * $filter is an already-built filter expression (use odataLit for values),
     * mirroring fetchDTLines/fetchWarehouses.
     */
    private function odataGet(string $token, string $entity, string $filter): array
    {
        $url = $this->getResource()."/data/{$entity}?\$filter={$filter}&cross-company=true";

        try {
            $response = Http::withToken($token)
                ->withHeaders([
                    'OData-MaxVersion' => '4.0',
                    'OData-Version' => '4.0',
                    'Accept' => 'application/json',
                ])
                ->timeout(60)
                ->get($url);

            if ($response->successful()) {
                return $response->json()['value'] ?? [];
            }

            Log::error("OData GET {$entity} failed. Status: {$response->status()}, Body: ".$response->body());
        } catch (\Throwable $e) {
            Log::error("OData GET {$entity} exception: ".$e->getMessage());
        }

        return [];
    }

    /**
     * Recompute and write Coli/Blister on the packing instruction (TOC_PILines)
     * for a freshly generated distribution, per the agreed packing ruling:
     *
     *   blister = ceil(storeQty / blisterCapacity), min 1
     *   coli    = blister <= 2 ? blister : ceil(blister / 5)
     *
     * The two Pemalang distribution warehouses (SACK_STORES) ship in sacks
     * instead: blister = ceil(storeQty / sackCapacity), with coli following the
     * blister count 1:1. Per-store quantities come from TOC_PILinesDetails
     * (per-size grain) summed per store.
     *
     * Best-effort: every failure is logged and swallowed so it can never block
     * label generation, which has already unlocked printing by this point.
     *
     * @return array{pis:int, patched:int, skipped:int, errors:array<int,string>}
     */
    public function syncPackingColiBlister(SubconOrder $order): array
    {
        $stats = ['pis' => 0, 'patched' => 0, 'skipped' => 0, 'errors' => []];

        $dist = $order->distribution_id;
        if (empty($dist)) {
            $stats['errors'][] = 'No distribution_id on order.';

            return $stats;
        }

        $token = $this->getD365Token();
        if (! $token) {
            $stats['errors'][] = 'Could not obtain D365 token.';

            return $stats;
        }

        $capacity = (int) ($order->blister_capacity ?? 0);
        if ($capacity <= 0) {
            $capacity = self::DEFAULT_BLISTER_CAPACITY;
        }

        $sackCapacity = (int) ($order->sack_capacity ?? 0);
        if ($sackCapacity <= 0) {
            $sackCapacity = self::DEFAULT_SACK_CAPACITY;
        }

        // Resolve the packing instruction(s) created for this distribution.
        $pis = $this->odataGet($token, 'TOC_PI', 'DTID eq '.$this->odataLit($dist));
        if (empty($pis)) {
            $stats['errors'][] = "No TOC_PI found for distribution {$dist} (labels may not be generated yet).";

            return $stats;
        }

        foreach ($pis as $pi) {
            $packingId = $pi['PackingID'] ?? null;
            $area = $pi['dataAreaId'] ?? null;
            if (! $packingId || ! $area) {
                continue;
            }
            $stats['pis']++;

            $pidFilter = 'PackingID eq '.$this->odataLit($packingId);
            $lines = $this->odataGet($token, 'TOC_PILines', $pidFilter);
            $details = $this->odataGet($token, 'TOC_PILinesDetails', $pidFilter);

            // Sum store qty from the per-size detail rows. A TOC_PILines row is one
            // per store; its detail rows carry their OWN `No` sequence (unrelated to
            // the line's `No`), so they join back to the line by StoreID only.
            $qtyByStore = [];
            foreach ($details as $d) {
                $s = $d['StoreID'] ?? '';
                $qtyByStore[$s] = ($qtyByStore[$s] ?? 0) + (int) ($d['Qty'] ?? 0);
            }

            foreach ($lines as $line) {
                $no = $line['No'] ?? null;
                $storeId = $line['StoreID'] ?? null;
                if ($no === null || $storeId === null) {
                    continue;
                }

                $storeQty = $qtyByStore[$storeId] ?? 0;

                if (in_array($storeId, self::SACK_STORES, true)) {
                    // Sack (karung) stores: one sack per blister, coli 1:1.
                    $blister = $storeQty > 0 ? (int) ceil($storeQty / $sackCapacity) : 1;
                    $coli = $blister;
                } else {
                    $blister = $storeQty > 0 ? (int) ceil($storeQty / $capacity) : 1;
                    $coli = $blister <= 2 ? $blister : (int) ceil($blister / 5);
                }

                // Skip when D365 already holds the target values (only sync changes).
                if ((int) ($line['Blister'] ?? -1) === $blister
                    && (int) ($line['Coli'] ?? -1) === $coli) {
                    $stats['skipped']++;

                    continue;
                }

                $key = [
                    'dataAreaId' => $area,
                    'PackingID' => $packingId,
                    'No' => $no,
                    'StoreID' => $storeId,
                ];
                if ($this->patchOData('TOC_PILines', $key, ['Coli' => $coli, 'Blister' => $blister])) {
                    $stats['patched']++;
                } else {
                    $stats['errors'][] = "PATCH failed: {$packingId} No={$no} Store={$storeId}.";
                }
            }
        }

        return $stats;
    }

    /**
     * Build per-store packaging-label groups for an order's distribution, sourcing
     * ACTUAL quantities from the packing instruction (TOC_PILinesDetails, per size)
     * and the box/blister page count from TOC_PILines.Blister — the authoritative
     * value written by syncPackingColiBlister (sack stores are already sack-based
     * there, so WH Replenish / WH Online paginate by their sack blister too).
     *
     * Only when a PI line carries no Blister yet do we recompute it locally:
     * ceil(storeQty / capacity), where sack stores use sack_capacity.
     *
     * Returns [] when no packing instruction exists for the distribution (e.g.
     * labels not generated), so callers can show a friendly "not generated" notice.
     * A TOC_PILines row is one store; its detail rows share the line's StoreID.
     *
     * @return array<int, array{
     *   packing_code:string, store_id:string, store_name:string, status:string,
     *   page_count:int, total_qty:int,
     *   items: array<int, array{size:string, qty:int, variant_id:string, item_code:string, gramasi_real:float}>
     * }>
     */
    public function fetchPackingInstructionGroups(SubconOrder $order): array
    {
        if (empty($order->distribution_id)) {
            return [];
        }

        $token = $this->getD365Token();
        if (! $token) {
            return [];
        }

        $blisterCap = (int) ($order->blister_capacity ?? 0);
        if ($blisterCap <= 0) {
            $blisterCap = self::DEFAULT_BLISTER_CAPACITY;
        }
        $sackCap = (int) ($order->sack_capacity ?? 0);
        if ($sackCap <= 0) {
            $sackCap = self::DEFAULT_SACK_CAPACITY;
        }

        $pis = $this->odataGet($token, 'TOC_PI', 'DTID eq '.$this->odataLit($order->distribution_id));
        if (empty($pis)) {
            return [];
        }

        $groups = [];

        foreach ($pis as $pi) {
            $packingId = $pi['PackingID'] ?? null;
            if (! $packingId) {
                continue;
            }

            $pidFilter = 'PackingID eq '.$this->odataLit($packingId);
            $lines = $this->odataGet($token, 'TOC_PILines', $pidFilter);
            $details = $this->odataGet($token, 'TOC_PILinesDetails', $pidFilter);

            // Per-size actual quantities grouped by the store they belong to.
            $detailsByStore = [];
            foreach ($details as $d) {
                $storeId = (string) ($d['StoreID'] ?? '');
                if ($storeId === '') {
                    continue;
                }
                $detailsByStore[$storeId][] = [
                    'size' => (string) ($d['Size'] ?? ''),
                    'qty' => (int) ($d['Qty'] ?? 0),
                    'variant_id' => (string) ($d['VariantID'] ?? ''),
                    'item_code' => (string) ($d['ItemCode'] ?? ''),
                    // Actual real weight for the line (kg total = qty × kg/piece).
                    'gramasi_real' => (float) ($d['TOC_GramasiReal'] ?? 0),
                ];
            }

            foreach ($lines as $line) {
                $storeId = (string) ($line['StoreID'] ?? '');
                if ($storeId === '') {
                    continue;
                }

                $items = $detailsByStore[$storeId] ?? [];
                $storeQty = array_sum(array_column($items, 'qty'));

                // Page/box count = the blister already on the PI line; recompute
                // only when D365 has none yet (sack stores fall back on sacks).
                $blister = (int) ($line['Blister'] ?? 0);
                if ($blister <= 0) {
                    $cap = in_array($storeId, self::SACK_STORES, true) ? $sackCap : $blisterCap;
                    $blister = $storeQty > 0 ? (int) ceil($storeQty / $cap) : 1;
                }

                $groups[] = [
                    'packing_code' => (string) ($line['PackingCode'] ?? ''),
                    'store_id' => $storeId,
                    'store_name' => (string) ($line['StoreName'] ?? ''),
                    'status' => 'Normal',
                    'page_count' => max(1, $blister),
                    'total_qty' => $storeQty,
                    'items' => $items,
                ];
            }
        }

        return $groups;
    }

    /**
     * Perform the complete sync workflow for cutting quantities and gramasi.
     *
     * @param  string  $productionGroup  The production group code
     * @param  array  $reports  Array of reported metrics keyed by ProdId/Size
     * @return array Status results of the updates
     */
    public function syncReportToD365(string $productionGroup, array $reports): array
    {
        $results = [
            'odata_cut_updates' => 0,
            'odata_pak_updates' => 0,
            'trigger_cut' => false,
            'trigger_pak' => false,
            'errors' => [],
        ];

        // Filter updates
        $cutUpdates = [];
        $pakUpdates = [];
        foreach ($reports as $item) {
            $size = $item['size'];
            if (isset($item['cutting_qty']) && is_numeric($item['cutting_qty'])) {
                $cutUpdates[$size] = (int) $item['cutting_qty'];
            }
            if (isset($item['gramasi']) && is_numeric($item['gramasi'])) {
                $pakUpdates[$size] = (float) $item['gramasi'] / 1000;
            }
        }

        if (empty($cutUpdates) && empty($pakUpdates)) {
            Log::info("No OData updates to perform for PRG {$productionGroup}");

            return $results;
        }

        $token = $this->getD365Token();
        if (! $token) {
            $results['errors'][] = 'Authentication failed.';

            return $results;
        }

        // 1. Resolve Job Transaction IDs for CMT-Cut and CMT-Pak from headers
        Log::info("Fetching JobTransactionHeaders for PRG {$productionGroup}");
        $headersUrl = $this->getResource().'/data/JobTransactionHeaders?$filter=ProductionGroup eq '.$this->odataLit($productionGroup).'&cross-company=true';

        $cutJobId = null;
        $pakJobId = null;

        try {
            $response = Http::withToken($token)->acceptJson()->get($headersUrl);
            if ($response->successful()) {
                $headers = $response->json()['value'] ?? [];
                foreach ($headers as $h) {
                    if ($this->isOperation($h['Operation'] ?? '', 'CMT-Cut')) {
                        $cutJobId = $h['JobTransactionId'];
                    }
                    if ($this->isOperation($h['Operation'] ?? '', 'CMT-Pak')) {
                        $pakJobId = $h['JobTransactionId'];
                    }
                }
            } else {
                $results['errors'][] = 'Failed to fetch Job Transaction Headers. Status: '.$response->status();

                return $results;
            }
        } catch (\Throwable $e) {
            $results['errors'][] = 'Exception fetching headers: '.$e->getMessage();

            return $results;
        }

        // 2. Perform CMT-Cut OData updates if there are cutting quantity changes.
        // startJobs() (called by the caller just before this) fires an async RPA
        // command that CREATES the JobTransactionHeaders in D365 — it doesn't wait
        // for that to finish. If this runs before the header actually exists,
        // $cutJobId (or every line below) comes back empty and, previously, the
        // whole block silently did nothing while the caller still logged "success"
        // (no exception was thrown). Explicitly erroring on "had updates to send
        // but wrote zero" turns that into a visible, retryable failure instead.
        if (! empty($cutUpdates)) {
            if (! $cutJobId) {
                $results['errors'][] = 'CMT-Cut job header not found in D365 for this production group — cutting quantities were not patched (the D365 job may not be started yet; retry later).';
            } else {
                Log::info("CMT-Cut job resolved: {$cutJobId}. Fetching line details.");
                $linesUrl = $this->getResource().'/data/JobTransactionLinesDetails?$filter=JobTransactionId eq '.$this->odataLit($cutJobId).'&cross-company=true';

                try {
                    $response = Http::withToken($token)->acceptJson()->get($linesUrl);
                    if ($response->successful()) {
                        $lines = $response->json()['value'] ?? [];
                        foreach ($lines as $line) {
                            $size = $line['Size'] ?? '';
                            if (isset($cutUpdates[$size])) {
                                $key = [
                                    'dataAreaId' => $line['dataAreaId'],
                                    'No' => $line['No'],
                                    'JobTransactionId' => $line['JobTransactionId'],
                                    'ItemId' => $line['ItemId'],
                                    'Size' => $size,
                                ];
                                $qtyToPatch = $cutUpdates[$size];
                                Log::info("Patching CMT-Cut for size {$size} with Jam7: {$qtyToPatch}");
                                if ($this->patchOData('JobTransactionLinesDetails', $key, ['Jam7' => $qtyToPatch])) {
                                    $results['odata_cut_updates']++;
                                }
                            }
                        }
                        if ($results['odata_cut_updates'] === 0) {
                            $results['errors'][] = 'CMT-Cut job found but no matching size line was patched — check Size values on JobTransactionLinesDetails.';
                        }
                    } else {
                        $results['errors'][] = 'Failed to fetch CMT-Cut lines. Status: '.$response->status();
                    }
                } catch (\Throwable $e) {
                    $results['errors'][] = 'Exception patching CMT-Cut lines: '.$e->getMessage();
                }
            }
        }

        // 3. Perform CMT-Pak OData updates if there are gramasi changes. Same
        // "had updates but wrote zero must be an error" contract as CMT-Cut above.
        if (! empty($pakUpdates)) {
            if (! $pakJobId) {
                $results['errors'][] = 'CMT-Pak job header not found in D365 for this production group — gramasi values were not patched (the D365 job may not be started yet; retry later).';
            } else {
                Log::info("CMT-Pak job resolved: {$pakJobId}. Fetching line details.");
                $linesUrl = $this->getResource().'/data/JobTransactionLinesDetails?$filter=JobTransactionId eq '.$this->odataLit($pakJobId).'&cross-company=true';

                try {
                    $response = Http::withToken($token)->acceptJson()->get($linesUrl);
                    if ($response->successful()) {
                        $lines = $response->json()['value'] ?? [];
                        foreach ($lines as $line) {
                            $size = $line['Size'] ?? '';
                            if (isset($pakUpdates[$size])) {
                                $key = [
                                    'dataAreaId' => $line['dataAreaId'],
                                    'No' => $line['No'],
                                    'JobTransactionId' => $line['JobTransactionId'],
                                    'ItemId' => $line['ItemId'],
                                    'Size' => $size,
                                ];
                                $gramasiToPatch = $pakUpdates[$size];
                                Log::info("Patching CMT-Pak for size {$size} with Gramasi: {$gramasiToPatch}");
                                if ($this->patchOData('JobTransactionLinesDetails', $key, ['Gramasi' => $gramasiToPatch])) {
                                    $results['odata_pak_updates']++;
                                }
                            }
                        }
                        if ($results['odata_pak_updates'] === 0) {
                            $results['errors'][] = 'CMT-Pak job found but no matching size line was patched — check Size values on JobTransactionLinesDetails.';
                        }
                    } else {
                        $results['errors'][] = 'Failed to fetch CMT-Pak lines. Status: '.$response->status();
                    }
                } catch (\Throwable $e) {
                    $results['errors'][] = 'Exception patching CMT-Pak lines: '.$e->getMessage();
                }
            }
        }

        // 4. Send triggers via jobt-api
        $triggerTargets = [];
        if ($results['odata_cut_updates'] > 0) {
            $triggerTargets[] = ['operation' => 'CMT-Cut', 'actions' => ['jam8']];
            $results['trigger_cut'] = true;
        }
        if ($results['odata_pak_updates'] > 0) {
            $triggerTargets[] = ['operation' => 'CMT-Pak', 'actions' => ['update_gramasi']];
            $results['trigger_pak'] = true;
        }

        if (! empty($triggerTargets)) {
            $triggerSuccess = $this->triggerJobs($productionGroup, $triggerTargets);
            if (! $triggerSuccess) {
                $results['errors'][] = 'Automation triggers failed.';
            }
        }

        return $results;
    }

    /**
     * Resolve the current CMT-Cut / CMT-Pak JobTransactionId for a production
     * group directly from D365 JobTransactionHeaders — never trust a job id
     * cached elsewhere (e.g. packaging_projects.cmt_pak_job_id): D365 can
     * reissue these (same reissue event that renumbers ProdIds on
     * production_group_lines), silently orphaning anything still keyed to the
     * old id. Headers-only fetch, so this is cheap enough to call before every
     * downstream job that needs the id.
     *
     * @return array{cut: ?string, pak: ?string}
     */
    public function resolveJobTransactionIds(string $productionGroup): array
    {
        $token = $this->getD365Token();
        if (! $token) {
            Log::error('Could not obtain token for resolving Job Transaction ids');

            return ['cut' => null, 'pak' => null];
        }

        $headersUrl = $this->getResource().'/data/JobTransactionHeaders?$filter=ProductionGroup eq '.$this->odataLit($productionGroup).'&cross-company=true';
        $cutJobId = null;
        $pakJobId = null;

        try {
            $response = Http::withToken($token)->acceptJson()->get($headersUrl);
            if ($response->successful()) {
                $headers = $response->json()['value'] ?? [];
                foreach ($headers as $h) {
                    if ($this->isOperation($h['Operation'] ?? '', 'CMT-Cut')) {
                        $cutJobId = $h['JobTransactionId'];
                    }
                    if ($this->isOperation($h['Operation'] ?? '', 'CMT-Pak')) {
                        $pakJobId = $h['JobTransactionId'];
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error("Exception resolving Job Transaction ids for {$productionGroup}: ".$e->getMessage());
        }

        return ['cut' => $cutJobId, 'pak' => $pakJobId];
    }

    /**
     * Fetch job transaction headers and details for a production group from D365.
     *
     * @return array{
     *   cutting: array<string, int>, // size => Jam7
     *   gramasi: array<string, float> // size => Gramasi (in kg)
     * }
     */
    public function fetchJobTransactionDetails(string $productionGroup): array
    {
        $token = $this->getD365Token();
        if (! $token) {
            Log::error('Could not obtain token for fetching Job Transaction Details');

            return ['cutting' => [], 'gramasi' => []];
        }

        $headersUrl = $this->getResource().'/data/JobTransactionHeaders?$filter=ProductionGroup eq '.$this->odataLit($productionGroup).'&cross-company=true';
        $cutJobId = null;
        $pakJobId = null;

        try {
            $response = Http::withToken($token)->acceptJson()->get($headersUrl);
            if ($response->successful()) {
                $headers = $response->json()['value'] ?? [];
                foreach ($headers as $h) {
                    if ($this->isOperation($h['Operation'] ?? '', 'CMT-Cut')) {
                        $cutJobId = $h['JobTransactionId'];
                    }
                    if ($this->isOperation($h['Operation'] ?? '', 'CMT-Pak')) {
                        $pakJobId = $h['JobTransactionId'];
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error("Exception fetching Job Transaction Headers for {$productionGroup}: ".$e->getMessage());
        }

        $cutting = [];
        $gramasi = [];

        if ($cutJobId) {
            $linesUrl = $this->getResource().'/data/JobTransactionLinesDetails?$filter=JobTransactionId eq '.$this->odataLit($cutJobId).'&cross-company=true';
            try {
                $response = Http::withToken($token)->acceptJson()->get($linesUrl);
                if ($response->successful()) {
                    $lines = $response->json()['value'] ?? [];
                    foreach ($lines as $line) {
                        $size = $line['Size'] ?? '';
                        $jam7 = $line['Jam7'] ?? 0;
                        if ($size !== '' && $jam7 > 0) {
                            $cutting[$size] = (int) $jam7;
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::error("Exception fetching CMT-Cut lines for {$productionGroup}: ".$e->getMessage());
            }
        }

        if ($pakJobId) {
            $linesUrl = $this->getResource().'/data/JobTransactionLinesDetails?$filter=JobTransactionId eq '.$this->odataLit($pakJobId).'&cross-company=true';
            try {
                $response = Http::withToken($token)->acceptJson()->get($linesUrl);
                if ($response->successful()) {
                    $lines = $response->json()['value'] ?? [];
                    foreach ($lines as $line) {
                        $size = $line['Size'] ?? '';
                        $gram = $line['Gramasi'] ?? 0.0;
                        if ($size !== '' && $gram > 0) {
                            $gramasi[$size] = (float) $gram;
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::error("Exception fetching CMT-Pak lines for {$productionGroup}: ".$e->getMessage());
            }
        }

        return [
            'cutting' => $cutting,
            'gramasi' => $gramasi,
        ];
    }

    /**
     * Fetch DTLines from D365 OData entity filter by DTID (distribution_id).
     */
    public function fetchDTLines(string $distributionId): array
    {
        $token = $this->getD365Token();
        if (! $token) {
            Log::error('Could not obtain UAT token for fetching DTLines');

            return [];
        }

        $url = $this->getResource().'/data/DTLines?$filter=DTID eq '.$this->odataLit($distributionId).'&cross-company=true';

        try {
            $response = Http::withToken($token)
                ->withHeaders([
                    'OData-MaxVersion' => '4.0',
                    'OData-Version' => '4.0',
                    'Accept' => 'application/json',
                ])
                ->timeout(60)
                ->get($url);

            if ($response->successful()) {
                return $response->json()['value'] ?? [];
            }

            Log::error("Failed to fetch DTLines for DTID {$distributionId}. Status: {$response->status()}, Body: ".$response->body());
        } catch (\Throwable $e) {
            Log::error("Exception fetching DTLines for DTID {$distributionId}: ".$e->getMessage());
        }

        return [];
    }

    /**
     * Fetch Warehouses from D365 OData entity matching the given list of IDs.
     *
     * @return array Array of warehouses keyed by WarehouseId
     */
    public function fetchWarehouses(array $warehouseIds): array
    {
        $token = $this->getD365Token();
        if (! $token) {
            Log::error('Could not obtain UAT token for fetching Warehouses');

            return [];
        }

        $chunks = array_chunk($warehouseIds, 20);
        $allWarehouses = [];

        foreach ($chunks as $chunk) {
            $filters = array_map(function ($id) {
                return 'WarehouseId eq '.$this->odataLit($id);
            }, $chunk);
            $filterStr = '('.implode(' or ', $filters).')';

            $url = $this->getResource()."/data/Warehouses?\$filter={$filterStr}&cross-company=true";

            try {
                $response = Http::withToken($token)
                    ->withHeaders([
                        'OData-MaxVersion' => '4.0',
                        'OData-Version' => '4.0',
                        'Accept' => 'application/json',
                    ])
                    ->timeout(60)
                    ->get($url);

                if ($response->successful()) {
                    $values = $response->json()['value'] ?? [];
                    foreach ($values as $wh) {
                        $allWarehouses[$wh['WarehouseId']] = $wh;
                    }
                } else {
                    Log::error("Failed to fetch Warehouses for chunk. Status: {$response->status()}, Body: ".$response->body());
                }
            } catch (\Throwable $e) {
                Log::error('Exception fetching Warehouses: '.$e->getMessage());
            }
        }

        return $allWarehouses;
    }

    /**
     * Fetch per-line fabric prices + currency for the given fabric PO numbers,
     * direct from D365 OData (PurchaseOrderLinesV2 — same entity the fabric sync
     * uses). Unit price is derived as LineAmount / OrderedPurchaseQuantity — price
     * PER UNIT metric — NOT PurchasePrice (D365 quotes that per a price-unit basis
     * of 1/100/1000, which over-states). CurrencyCode comes off the line. Best-effort:
     * returns [] on any failure, cached ~1h so a no-login page never re-hits D365.
     *
     * @param  array<int, string>  $poNumbers
     * @return array<string, array{currency: ?string, items: array<string, float>}> keyed by PO number
     */
    public function fetchFabricPricing(array $poNumbers): array
    {
        $poNumbers = array_values(array_filter(array_unique($poNumbers)));
        if (empty($poNumbers)) {
            return [];
        }

        $cacheKey = 'd365_fabric_pricing_'.md5(implode(',', $poNumbers));
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $token = $this->getD365Token();
        if (! $token) {
            return [];
        }

        // Accumulate line amount + qty per (PO, item) so the unit price is the
        // weighted LineAmount / qty (price PER UNIT metric), robust to multi-line fabrics.
        $currency = [];
        $acc = [];
        try {
            foreach (array_chunk($poNumbers, 20) as $chunk) {
                $filters = array_map(fn ($po) => 'PurchaseOrderNumber eq '.$this->odataLit($po), $chunk);
                $filterStr = '('.implode(' or ', $filters).')';
                $url = $this->getResource()."/data/PurchaseOrderLinesV2?\$filter={$filterStr}&cross-company=true";

                $response = Http::withToken($token)
                    ->withHeaders(['OData-MaxVersion' => '4.0', 'OData-Version' => '4.0', 'Accept' => 'application/json'])
                    ->timeout(30)
                    ->get($url);

                if (! $response->successful()) {
                    Log::warning('D365 fabric pricing fetch failed. Status: '.$response->status());

                    continue;
                }

                foreach ($response->json()['value'] ?? [] as $line) {
                    $po = $line['PurchaseOrderNumber'] ?? null;
                    $item = $line['ItemNumber'] ?? null;
                    if ($po === null || $item === null) {
                        continue;
                    }
                    $currency[$po] ??= $line['CurrencyCode'] ?? null;
                    $acc[$po][$item]['amount'] = ($acc[$po][$item]['amount'] ?? 0.0) + (float) ($line['LineAmount'] ?? 0);
                    $acc[$po][$item]['qty'] = ($acc[$po][$item]['qty'] ?? 0.0) + (float) ($line['OrderedPurchaseQuantity'] ?? 0);
                }
            }

            // Unit price PER metric = sum(LineAmount) / sum(qty). Never PurchasePrice,
            // which D365 quotes per a price-unit basis (1/100/1000) and over-states.
            $out = [];
            foreach ($acc as $po => $items) {
                $out[$po] = ['currency' => $currency[$po] ?? null, 'items' => []];
                foreach ($items as $item => $a) {
                    $out[$po]['items'][$item] = $a['qty'] > 0 ? round($a['amount'] / $a['qty'], 2) : 0.0;
                }
            }

            Cache::put($cacheKey, $out, now()->addHour());
        } catch (\Throwable $e) {
            Log::error('Exception fetching D365 fabric pricing: '.$e->getMessage());

            return [];
        }

        return $out;
    }

    /**
     * Item-master lookup (D365 `ReleasedProductsV2`) for fabric items: Inventory
     * Group (`TOC_InventoryGroup`, e.g. "COMBINASI" on the D365 item details page)
     * and the item's real description (`ProductSearchName`) — the PO line's own
     * `LineDescription` is free text and can be stale/wrong (seen in production:
     * a genuine fabric item with a leftover "HANGTAG ..." line description from
     * a copy-paste), so this is the description to display, never the PO line's.
     * Best-effort, cached ~1h like fetchFabricPricing. `cross-company=true`
     * returns one row per legal entity for the same item number (mpg/mpr/...);
     * fabric items are mpg-scoped (see CLAUDE.md), so an mpg row wins when more
     * than one company returns a match.
     *
     * @param  array<int, string>  $itemNumbers
     * @return array<string, array{inventory_group: ?string, description: ?string}> keyed by item number
     */
    public function fetchItemMaster(array $itemNumbers): array
    {
        // Force back to string: callers commonly collect item numbers via an
        // associative array used as a set (`$set[$itemNumber] = true`), and PHP
        // silently casts a purely-numeric string key to an int — which then skips
        // the quoting in odataLit() below and breaks the filter against D365's
        // Edm.String ItemNumber/ProductNumber fields ("incompatible types" 400).
        $itemNumbers = array_values(array_unique(array_map('strval', array_filter($itemNumbers))));
        if (empty($itemNumbers)) {
            return [];
        }

        $cacheKey = 'd365_item_master_'.md5(implode(',', $itemNumbers));
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $token = $this->getD365Token();
        if (! $token) {
            return [];
        }

        $out = [];
        try {
            foreach (array_chunk($itemNumbers, 20) as $chunk) {
                $filters = array_map(fn ($it) => 'ItemNumber eq '.$this->odataLit($it), $chunk);
                $filterStr = '('.implode(' or ', $filters).')';
                $url = $this->getResource()."/data/ReleasedProductsV2?\$filter={$filterStr}&cross-company=true";

                $response = Http::withToken($token)
                    ->withHeaders(['OData-MaxVersion' => '4.0', 'OData-Version' => '4.0', 'Accept' => 'application/json'])
                    ->timeout(30)
                    ->get($url);

                if (! $response->successful()) {
                    Log::warning('D365 item master fetch failed. Status: '.$response->status());

                    continue;
                }

                foreach ($response->json()['value'] ?? [] as $row) {
                    $item = $row['ItemNumber'] ?? null;
                    if ($item === null) {
                        continue;
                    }
                    $isMpg = strcasecmp((string) ($row['dataAreaId'] ?? ''), 'mpg') === 0;
                    if (! isset($out[$item]) || $isMpg) {
                        $out[$item] = [
                            'inventory_group' => $row['TOC_InventoryGroup'] ?? null,
                            'description' => null,
                        ];
                    }
                }
            }

            // The item's real display name lives on `ProductTranslations` (its
            // `ProductName`), NOT `ReleasedProductsV2.ProductSearchName`/`SearchName`
            // — confirmed in production: for item 2508000000398 SearchName reads
            // "WOVEN 70% COTTON..." (itself wrong — TOC_FabricType says KNITTING) while
            // ProductTranslations.ProductName correctly reads "KNITTING 70% COTTON...
            // SM26-CALL BLACK". `ProductTranslations.Description` is what a PO line's
            // own (possibly stale/wrong) LineDescription is actually sourced from —
            // e.g. that same item's Description is "HANGTAG EDWIN VEGAS..." even
            // though it's genuinely fabric — so never use Description as the identity.
            foreach (array_chunk($itemNumbers, 20) as $chunk) {
                $filters = array_map(fn ($it) => 'ProductNumber eq '.$this->odataLit($it), $chunk);
                $filterStr = '('.implode(' or ', $filters).") and LanguageId eq 'en-US'";
                $url = $this->getResource()."/data/ProductTranslations?\$filter={$filterStr}&cross-company=true";

                $response = Http::withToken($token)
                    ->withHeaders(['OData-MaxVersion' => '4.0', 'OData-Version' => '4.0', 'Accept' => 'application/json'])
                    ->timeout(30)
                    ->get($url);

                if (! $response->successful()) {
                    Log::warning('D365 product translation fetch failed. Status: '.$response->status());

                    continue;
                }

                foreach ($response->json()['value'] ?? [] as $row) {
                    $item = $row['ProductNumber'] ?? null;
                    $name = $row['ProductName'] ?? null;
                    if ($item === null || empty($name)) {
                        continue;
                    }
                    $out[$item] ??= ['inventory_group' => null, 'description' => null];
                    $out[$item]['description'] = $name;
                }
            }

            Cache::put($cacheKey, $out, now()->addHour());
        } catch (\Throwable $e) {
            Log::error('Exception fetching D365 item master: '.$e->getMessage());

            return [];
        }

        return $out;
    }

    /**
     * Fetch actual goods-receipt quantities per fabric PO, direct from D365
     * OData. Unlike PurchLineBiEntities (ordered/remaining, procurement-side)
     * or the PurchaseOrderStatus flag (received or not, no quantity),
     * VendPackingSlipTransBiEntities is the real receipt evidence — one row per
     * packing-slip line, so a PO delivered in several partial shipments is
     * summed across all of them. VendPackingSlipJourBiEntities (the packing
     * slip header) supplies the latest delivery date, shown as a hint only.
     * Best-effort: returns [] on any failure, cached ~1h like fetchFabricPricing
     * so a no-login page never re-hits D365 on every view.
     *
     * @param  array<int, string>  $poNumbers
     * @return array<string, array{items: array<string, float>, last_delivery_date: ?string}> keyed by PO number
     */
    public function fetchGoodsReceipts(array $poNumbers): array
    {
        $poNumbers = array_values(array_filter(array_unique($poNumbers)));
        if (empty($poNumbers)) {
            return [];
        }

        $cacheKey = 'd365_goods_receipt_'.md5(implode(',', $poNumbers));
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $token = $this->getD365Token();
        if (! $token) {
            return [];
        }

        $qty = [];
        $lastDelivery = [];
        try {
            foreach (array_chunk($poNumbers, 20) as $chunk) {
                $filters = array_map(fn ($po) => 'OrigPurchid eq '.$this->odataLit($po), $chunk);
                $filterStr = '('.implode(' or ', $filters).')';
                $url = $this->getResource()."/data/VendPackingSlipTransBiEntities?\$filter={$filterStr}&cross-company=true";

                $response = Http::withToken($token)
                    ->withHeaders(['OData-MaxVersion' => '4.0', 'OData-Version' => '4.0', 'Accept' => 'application/json'])
                    ->timeout(30)
                    ->get($url);

                if (! $response->successful()) {
                    Log::warning('D365 goods receipt fetch failed. Status: '.$response->status());

                    continue;
                }

                foreach ($response->json()['value'] ?? [] as $line) {
                    $po = $line['OrigPurchid'] ?? null;
                    $item = $line['ItemId'] ?? null;
                    if ($po === null || $item === null) {
                        continue;
                    }
                    $qty[$po][$item] = ($qty[$po][$item] ?? 0.0) + (float) ($line['Qty'] ?? 0);
                }
            }

            // Delivery date off the packing-slip header — a second batched call,
            // best-effort (a missing header just means no date hint is shown).
            foreach (array_chunk($poNumbers, 20) as $chunk) {
                $filters = array_map(fn ($po) => 'PurchId eq '.$this->odataLit($po), $chunk);
                $filterStr = '('.implode(' or ', $filters).')';
                $url = $this->getResource()."/data/VendPackingSlipJourBiEntities?\$filter={$filterStr}&cross-company=true";

                $response = Http::withToken($token)
                    ->withHeaders(['OData-MaxVersion' => '4.0', 'OData-Version' => '4.0', 'Accept' => 'application/json'])
                    ->timeout(30)
                    ->get($url);

                if (! $response->successful()) {
                    continue;
                }

                foreach ($response->json()['value'] ?? [] as $jour) {
                    $po = $jour['PurchId'] ?? null;
                    $date = $jour['DeliveryDate'] ?? null;
                    if ($po === null || ! $date) {
                        continue;
                    }
                    if (! isset($lastDelivery[$po]) || $date > $lastDelivery[$po]) {
                        $lastDelivery[$po] = $date;
                    }
                }
            }

            $out = [];
            foreach ($qty as $po => $items) {
                $out[$po] = ['items' => $items, 'last_delivery_date' => $lastDelivery[$po] ?? null];
            }

            Cache::put($cacheKey, $out, now()->addHour());
        } catch (\Throwable $e) {
            Log::error('Exception fetching D365 goods receipts: '.$e->getMessage());

            return [];
        }

        return $out;
    }
}
