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
    protected function getD365Token(): ?string
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
     *
     * @return array{success: bool, message: string} `message` carries jobt-api's
     *                                               own reported reason (its "message"/"error" field, or a Laravel-side
     *                                               description) so callers can put something more specific than "it failed"
     *                                               in front of an admin — e.g. in SubconJobLog after retries are exhausted.
     */
    public function startJobs(string $productionGroup): array
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
                    $reason = $response->json('error') ?? $response->json('message') ?? $response->body();
                    Log::error("Failed to start jobs for PRG {$productionGroup} (jobt-api reported failure): {$reason}");

                    return ['success' => false, 'message' => "jobt-api failed to start the D365 job transaction for {$productionGroup}: {$reason}"];
                }

                Log::info("Successfully started jobs for PRG {$productionGroup}: ".$response->body());

                return ['success' => true, 'message' => ''];
            }

            Log::error("Failed to start jobs for PRG {$productionGroup}. Status: {$response->status()}, Body: ".$response->body());

            return ['success' => false, 'message' => "jobt-api HTTP {$response->status()} starting jobs for {$productionGroup}."];
        } catch (\Throwable $e) {
            Log::error("Exception starting jobs for PRG {$productionGroup}: ".$e->getMessage());

            return ['success' => false, 'message' => "Exception starting jobs for {$productionGroup}: ".$e->getMessage()];
        }
    }

    /**
     * Send 'trigger' command to the jobt-api.
     *
     * @return array{success: bool, message: string} See startJobs() for why this
     *                                               isn't a bare bool.
     */
    public function triggerJobs(string $productionGroup, array $targets): array
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
                // Same jobt-api quirk as startJobs(): HTTP 200 even when the
                // Playwright flow itself failed — the real outcome is in the
                // body's "success" field, not the status code. This used to be
                // unchecked here, so a jam8/update_gramasi failure inside
                // jobt-api was reported as "Successfully triggered jobs" and the
                // caller (syncReportToD365) never recorded an error.
                if (($response->json('success') ?? true) === false) {
                    $reason = $response->json('error') ?? $response->json('message') ?? $response->body();
                    Log::error("Failed to trigger jobs for PRG {$productionGroup} (jobt-api reported failure): {$reason}");

                    return ['success' => false, 'message' => "Automation trigger failed for {$productionGroup}: {$reason}"];
                }

                Log::info("Successfully triggered jobs for PRG {$productionGroup}: ".$response->body());

                return ['success' => true, 'message' => ''];
            }

            Log::error("Failed to trigger jobs for PRG {$productionGroup}. Status: {$response->status()}, Body: ".$response->body());

            return ['success' => false, 'message' => "jobt-api HTTP {$response->status()} triggering jobs for {$productionGroup}."];
        } catch (\Throwable $e) {
            Log::error("Exception triggering jobs for PRG {$productionGroup}: ".$e->getMessage());

            return ['success' => false, 'message' => "Exception triggering jobs for {$productionGroup}: ".$e->getMessage()];
        }
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
     * `total_lines` lets a caller (GenerateSubconLabels' recalc fallback)
     * distinguish "a TOC_PI header exists but D365 never finished writing its
     * TOC_PILines" from a normal recalc with nothing to patch — both leave
     * `patched`/`skipped` at 0 and `errors` empty, but only the former means
     * the packing instruction is actually incomplete.
     *
     * @return array{pis:int, total_lines:int, patched:int, skipped:int, errors:array<int,string>}
     */
    public function syncPackingColiBlister(SubconOrder $order): array
    {
        $stats = ['pis' => 0, 'total_lines' => 0, 'patched' => 0, 'skipped' => 0, 'errors' => []];

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
            $stats['total_lines'] += count($lines);

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
            $trigger = $this->triggerJobs($productionGroup, $triggerTargets);
            if (! $trigger['success']) {
                $results['errors'][] = $trigger['message'];
            }
        }

        return $results;
    }

    /**
     * Reject-quantity field on `JobTransactionLinesDetails` a given add-process
     * Operation's reject qty should be PATCHed into. Only a handful of named
     * reject columns exist in D365 (see raf-service-flow memory's leaf-reject
     * list); ADD-Pleat, an "Add Process Belt" (if/when it exists), and any
     * other unrecognised ADD-* operation have no dedicated column, so they
     * fall back to the general RejectFinishing bucket.
     */
    private function rejectFieldForOperation(string $operation): string
    {
        return match (true) {
            $this->isOperation($operation, 'ADD-Was') => 'RejectWashing',
            $this->isOperation($operation, 'ADD-Embro') => 'RejectEmbro',
            $this->isOperation($operation, 'ADD-Sab') => 'RejectPrinting',
            default => 'RejectFinishing',
        };
    }

    /**
     * Same shape/contract as syncReportToD365(), generalised to any add-process
     * Operation (ADD-Was/ADD-Embro/ADD-Sab/ADD-Pleat/...) instead of the
     * hardcoded CMT-Cut/CMT-Pak pair — see that method's docblock for the
     * shared reasoning (e.g. the "had updates but resolved zero JobTransactionId
     * → explicit retryable error" contract).
     *
     * @param  string  $productionGroup  The production group code
     * @param  string  $operation  Raw D365 Operation value, e.g. "ADD-Was"
     * @param  array  $reports  Array of ['size' => ..., 'qty' => ..., 'reject_qty' => ...]
     * @return array Status results of the updates
     */
    public function syncAddProcessToD365(string $productionGroup, string $operation, array $reports): array
    {
        $results = [
            'odata_updates' => 0,
            'trigger' => false,
            'errors' => [],
        ];

        $qtyUpdates = [];
        $rejectUpdates = [];
        foreach ($reports as $item) {
            $size = $item['size'];
            if (isset($item['qty']) && is_numeric($item['qty'])) {
                $qtyUpdates[$size] = (int) $item['qty'];
            }
            if (isset($item['reject_qty']) && is_numeric($item['reject_qty'])) {
                $rejectUpdates[$size] = (int) $item['reject_qty'];
            }
        }

        if (empty($qtyUpdates) && empty($rejectUpdates)) {
            Log::info("No OData updates to perform for PRG {$productionGroup} ({$operation})");

            return $results;
        }

        $token = $this->getD365Token();
        if (! $token) {
            $results['errors'][] = 'Authentication failed.';

            return $results;
        }

        Log::info("Fetching JobTransactionHeaders for PRG {$productionGroup} ({$operation})");
        $headersUrl = $this->getResource().'/data/JobTransactionHeaders?$filter=ProductionGroup eq '.$this->odataLit($productionGroup).'&cross-company=true';

        $jobId = null;

        try {
            $response = Http::withToken($token)->acceptJson()->get($headersUrl);
            if ($response->successful()) {
                $headers = $response->json()['value'] ?? [];
                foreach ($headers as $h) {
                    if ($this->isOperation($h['Operation'] ?? '', $operation)) {
                        $jobId = $h['JobTransactionId'];
                        break;
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

        if (! $jobId) {
            $results['errors'][] = "{$operation} job header not found in D365 for this production group — quantities were not patched (the D365 job may not be started yet; retry later).";

            return $results;
        }

        $rejectField = $this->rejectFieldForOperation($operation);
        // Whether any successful PATCH above actually included $rejectField —
        // distinct from "$rejectUpdates was requested": a size can be in
        // $rejectUpdates but have no matching line, or the patch itself can
        // fail. Only a PATCH that actually landed makes the reject-formula
        // trigger below meaningful (see jobt-api's "reject" action).
        $rejectPatched = false;

        Log::info("{$operation} job resolved: {$jobId}. Fetching line details.");
        $linesUrl = $this->getResource().'/data/JobTransactionLinesDetails?$filter=JobTransactionId eq '.$this->odataLit($jobId).'&cross-company=true';

        try {
            $response = Http::withToken($token)->acceptJson()->get($linesUrl);
            if ($response->successful()) {
                $lines = $response->json()['value'] ?? [];
                foreach ($lines as $line) {
                    $size = $line['Size'] ?? '';
                    $changes = [];
                    if (isset($qtyUpdates[$size])) {
                        $changes['Jam7'] = $qtyUpdates[$size];
                    }
                    $includesReject = isset($rejectUpdates[$size]);
                    if ($includesReject) {
                        $changes[$rejectField] = $rejectUpdates[$size];
                    }
                    if (empty($changes)) {
                        continue;
                    }

                    $key = [
                        'dataAreaId' => $line['dataAreaId'],
                        'No' => $line['No'],
                        'JobTransactionId' => $line['JobTransactionId'],
                        'ItemId' => $line['ItemId'],
                        'Size' => $size,
                    ];
                    Log::info("Patching {$operation} for size {$size}: ".json_encode($changes));
                    if ($this->patchOData('JobTransactionLinesDetails', $key, $changes)) {
                        $results['odata_updates']++;
                        if ($includesReject) {
                            $rejectPatched = true;
                        }
                    }
                }
                if ($results['odata_updates'] === 0) {
                    $results['errors'][] = "{$operation} job found but no matching size line was patched — check Size values on JobTransactionLinesDetails.";
                }
            } else {
                $results['errors'][] = "Failed to fetch {$operation} lines. Status: ".$response->status();
            }
        } catch (\Throwable $e) {
            $results['errors'][] = "Exception patching {$operation} lines: ".$e->getMessage();
        }

        if ($results['odata_updates'] > 0) {
            // "jam8" fires the good-qty (Jam7) formula. "reject" fires D365's
            // shared reject-recompute formula by re-typing RejectFinishing's
            // own value — NOT $rejectField. Touching RejectFinishing is what
            // actually fires the recompute regardless of which named column
            // ($rejectField above) the reject qty itself was PATCHed into;
            // it isn't a per-field-specific trigger, it's the one field
            // reg_raf already proved this on (CMT-Pak). Retargeting the
            // *trigger* at $rejectField instead would mean touching
            // RejectWashing/RejectEmbro/RejectPrinting selectors that have
            // never actually been verified against the real D365 grid.
            $target = ['operation' => $operation, 'actions' => ['jam8']];
            if ($rejectPatched) {
                $target['actions'][] = 'reject';
                $target['reject_field'] = 'RejectFinishing';
            }

            $trigger = $this->triggerJobs($productionGroup, [$target]);
            $results['trigger'] = $trigger['success'];
            if (! $trigger['success']) {
                $results['errors'][] = $trigger['message'];
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
     * Live D365 Good/Reject qty for a single ADD-* operation's job
     * transaction, keyed by size — confirmed live that D365 can carry this
     * (TotalGoodQty/TotalRejectQty on JobTransactionLinesDetails) well before
     * the admin has entered anything into the local subcon_add_process_reports
     * table, since the vendor/factory reports progress straight into D365's
     * own shop-floor system independently of this portal's Add Process form.
     * SubconAddProcessController::index() uses this as a prefill so the admin
     * isn't stuck re-typing numbers D365 already has. Read-only — does not
     * write syncAddProcessToD365()'s job of pushing local edits to D365.
     * Best-effort + cached (1h, matches fetchSalesPrice()/fetchGoodsReceipts()'s
     * convention): [] on any failure or if the operation's job isn't found.
     *
     * @return array<string, array{good: float, reject: float}> keyed by Size
     */
    public function fetchAddProcessQuantities(string $productionGroup, string $operation): array
    {
        $productionGroup = trim($productionGroup);
        $operation = trim($operation);
        if ($productionGroup === '' || $operation === '') {
            return [];
        }

        $cacheKey = 'd365_add_process_qty_'.md5($productionGroup.'|'.$operation);
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $token = $this->getD365Token();
        if (! $token) {
            return [];
        }

        try {
            $headers = $this->odataGet($token, 'JobTransactionHeaders', 'ProductionGroup eq '.$this->odataLit($productionGroup));
            $jobId = null;
            foreach ($headers as $h) {
                if ($this->isOperation($h['Operation'] ?? '', $operation)) {
                    $jobId = $h['JobTransactionId'];
                    break;
                }
            }
            if (! $jobId) {
                Cache::put($cacheKey, [], now()->addHour());

                return [];
            }

            $lines = $this->odataGet($token, 'JobTransactionLinesDetails', 'JobTransactionId eq '.$this->odataLit($jobId));
            $quantities = [];
            foreach ($lines as $line) {
                $size = $line['Size'] ?? '';
                if ($size === '') {
                    continue;
                }
                $quantities[$size] = [
                    'good' => (float) ($line['TotalGoodQty'] ?? 0),
                    'reject' => (float) ($line['TotalRejectQty'] ?? 0),
                ];
            }

            Cache::put($cacheKey, $quantities, now()->addHour());

            return $quantities;
        } catch (\Throwable $e) {
            Log::error("Exception fetching {$operation} quantities for {$productionGroup}: ".$e->getMessage());

            return [];
        }
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
     * Live fetch of a production group's per-size lines straight from D365's
     * `ProductionGroupLinesItem` OData entity — used only as a fallback for a
     * manually-attached "extra" production group (see
     * SubconOrderExtraProductionGroup) that VSM hasn't synced yet: a brand-new
     * PLM/production batch can lag VSM's own sync by a cycle, and this lets
     * the admin/vendor see it in the portal immediately instead of waiting.
     * Not used anywhere in the normal forPo() path, which stays VSM-only.
     *
     * Field names differ from vsm.production_group_lines (no ProdStatus,
     * SearchName here) — the caller fills in sensible blanks.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchProductionGroupLinesLive(string $productionGroup): array
    {
        $token = $this->getD365Token();
        if (! $token) {
            Log::error('Could not obtain UAT token for fetching ProductionGroupLinesItem');

            return [];
        }

        $url = $this->getResource().'/data/ProductionGroupLinesItem?$filter=TOC_ProductionGroup eq '.$this->odataLit($productionGroup).'&cross-company=true';

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

            Log::error("Failed to fetch ProductionGroupLinesItem for {$productionGroup}. Status: {$response->status()}, Body: ".$response->body());
        } catch (\Throwable $e) {
            Log::error("Exception fetching ProductionGroupLinesItem for {$productionGroup}: ".$e->getMessage());
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
                    // Meters → yards: the price this derives feeds the fabric
                    // deduction formula, which always multiplies against a
                    // yard-denominated consumption figure (the cutting floor
                    // convention — see SubconProductionService::resolveGoodsReceipts()
                    // and fabricLinesForPo()) regardless of what unit the fabric
                    // was actually purchased in. Left unconverted, a meter-priced
                    // fabric's resolved price (and so any deduction charged
                    // against it) came out ~9.36% too high.
                    $qty = (float) ($line['OrderedPurchaseQuantity'] ?? 0);
                    if (strcasecmp((string) ($line['PurchaseUnitSymbol'] ?? ''), 'M') === 0) {
                        $qty *= \App\Services\SubconProductionService::METER_TO_YARD;
                    }
                    $acc[$po][$item]['amount'] = ($acc[$po][$item]['amount'] ?? 0.0) + (float) ($line['LineAmount'] ?? 0);
                    $acc[$po][$item]['qty'] = ($acc[$po][$item]['qty'] ?? 0.0) + $qty;
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
     * Vendor display name per vendor account code, direct from D365's
     * `VendorsV2` entity (the same one `d365:sync-subcon-vendors` reads for
     * VendorOrganizationName/VendorSearchName) — used for vendors that are
     * deliberately NOT registered as a local `Vendor` (the Add Process/AddPro
     * PO's own vendor, see SubconProductionService::
     * addProcessPurchaseOrdersForGroup()'s docblock), so there's no local
     * name to fall back on. Scoped to `dataAreaId eq 'mpg'` — vendor account
     * codes are company-scoped in D365 (same code can be a different vendor
     * in mpr/mti), see CLAUDE.md's cross-company gotcha. Best-effort, cached
     * ~1h like the other D365 lookups here.
     *
     * @param  array<int, string>  $vendorCodes
     * @return array<string, string> keyed by vendor account code
     */
    public function fetchVendorNames(array $vendorCodes): array
    {
        $vendorCodes = array_values(array_unique(array_filter(array_map('strval', $vendorCodes))));
        if (empty($vendorCodes)) {
            return [];
        }

        $cacheKey = 'd365_vendor_names_'.md5(implode(',', $vendorCodes));
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $token = $this->getD365Token();
        if (! $token) {
            return [];
        }

        $out = [];
        try {
            foreach (array_chunk($vendorCodes, 20) as $chunk) {
                $filters = array_map(fn ($c) => 'VendorAccountNumber eq '.$this->odataLit($c), $chunk);
                $filterStr = '('.implode(' or ', $filters).") and dataAreaId eq 'mpg'";
                $url = $this->getResource()."/data/VendorsV2?\$filter={$filterStr}&cross-company=true";

                $response = Http::withToken($token)
                    ->withHeaders(['OData-MaxVersion' => '4.0', 'OData-Version' => '4.0', 'Accept' => 'application/json'])
                    ->timeout(30)
                    ->get($url);

                if (! $response->successful()) {
                    Log::warning('D365 vendor name fetch failed. Status: '.$response->status());

                    continue;
                }

                foreach ($response->json()['value'] ?? [] as $r) {
                    $code = $r['VendorAccountNumber'] ?? null;
                    if ($code === null) {
                        continue;
                    }
                    $out[$code] = $r['VendorOrganizationName'] ?? $r['VendorSearchName'] ?? $code;
                }
            }

            Cache::put($cacheKey, $out, now()->addHour());
        } catch (\Throwable $e) {
            Log::error('Exception fetching D365 vendor names: '.$e->getMessage());

            return [];
        }

        return $out;
    }

    /**
     * Garment sales price direct from D365's `ReleasedProductMastersV2`
     * (`SalesPrice` field), keyed by item number = PLM id. Mirrors the QC
     * Console's own `fetch_odata_sales_price` (Rust) — reused here for the
     * Official Report's reject-tolerance deduction (penalty_price =
     * sales_price * 0.70, see SubconOfficialReportService). Best-effort:
     * null on any failure, a blank PLMId, or no matching D365 record —
     * callers must let the admin fill the price in by hand in that case.
     */
    public function fetchSalesPrice(string $plmId): ?float
    {
        $plmId = trim($plmId);
        if ($plmId === '') {
            return null;
        }

        // Wrapped (['v' => $price]) rather than caching $price bare: Cache::has()
        // is implemented as `! is_null($this->get($key))`, so a bare-cached null
        // (the "no matching D365 record" result) would make Cache::has() report
        // false forever after, silently defeating the cache for every PLMId with
        // no D365 match — this wrapper keeps a cached null distinguishable from
        // "not cached yet".
        $cacheKey = 'd365_sales_price_'.md5($plmId);
        if (Cache::has($cacheKey)) {
            // Defensive unwrap: a persistent cache store may still hold a
            // pre-fix bare float/null entry written before this ['v' => ...]
            // wrapping was introduced — reading ['v'] off that would warn and
            // misread, so only unwrap when it's actually wrapped.
            $cached = Cache::get($cacheKey);

            return is_array($cached) ? $cached['v'] : $cached;
        }

        $token = $this->getD365Token();
        if (! $token) {
            return null;
        }

        try {
            $filter = 'ItemNumber eq '.$this->odataLit($plmId);
            $rows = $this->odataGet($token, 'ReleasedProductMastersV2', $filter);
            $price = isset($rows[0]['SalesPrice']) ? (float) $rows[0]['SalesPrice'] : null;

            Cache::put($cacheKey, ['v' => $price], now()->addHour());

            return $price;
        } catch (\Throwable $e) {
            Log::error('D365 sales price fetch exception: '.$e->getMessage());

            return null;
        }
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
     * `items` is keyed one level deeper than PO+item alone — by
     * PurchaseLineLineNumber too — because the same item number can appear on
     * multiple lines of the same PO, one per style sharing that PO (confirmed
     * live: SubconProductionService::resolveGoodsReceipts()'s docblock has the
     * exact case). Collapsing straight to PO+item, as this used to, summed
     * every style's receipt into each one's own figure. The consumer
     * (resolveGoodsReceipts()) matches against its own line's LineNumber
     * (po_lines.LineNumber, which is exactly this field) to pick out only
     * what was actually received against its own PO line.
     *
     * @param  array<int, string>  $poNumbers
     * @return array<string, array{items: array<string, array<string, float>>, last_delivery_date: ?string}> keyed by PO number
     */
    public function fetchGoodsReceipts(array $poNumbers): array
    {
        $poNumbers = array_values(array_filter(array_unique($poNumbers)));
        if (empty($poNumbers)) {
            return [];
        }

        // Versioned ("v2") so a future change to this method's return shape can't be
        // silently served stale by an old cache entry — bump the version, not just
        // the logic, whenever $out's shape changes. Confirmed live: the v1 shape was
        // items[$item] = float, now items[$item][$lineNumber] = float; without the
        // version bump, entries written by the old code (up to 1h old) still under
        // the same key would hand SubconProductionService::resolveGoodsReceipts() a
        // float where it now expects an array, fatally erroring the HO approval page.
        $cacheKey = 'd365_goods_receipt_v2_'.md5(implode(',', $poNumbers));
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
                    // PurchaseLineLineNumber matches po_lines.LineNumber exactly —
                    // see this method's docblock for why keying by it (not just
                    // PO+item) is required.
                    $lineNum = isset($line['PurchaseLineLineNumber']) ? (string) $line['PurchaseLineLineNumber'] : '';
                    $qty[$po][$item][$lineNum] = ($qty[$po][$item][$lineNum] ?? 0.0) + (float) ($line['Qty'] ?? 0);
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

    /**
     * Real per-pc consumption rate per raw material, direct from D365's custom
     * `TOC_FinalRMPrices` entity — the actual BOM Final costing/consumption
     * record (`TOC_Consumption`), NOT `vsm.bom_lines.RequiredQty` (a scaled
     * total for whatever order last triggered the BOM calc — see
     * SubconProductionService::bomMaterialsForOrder()'s docblock). This is a
     * genuine per-unit design rate (e.g. "1 hangtag per garment", "0.09 YD
     * interlining per garment") that does NOT need dividing by an order's
     * cutting qty, and — being a per-unit spec rather than a scaled total —
     * is far less sensitive to which order's run last recalculated the
     * underlying BOM Final snapshot (see materialIssueForOrder()'s docblock
     * for the related total-vs-rate distinction on the Material Issue side).
     *
     * A raw material can appear on multiple rows for the same PLM when it's
     * genuinely size-specific (e.g. a belt SKU used only for sizes S/M, each
     * its own row carrying its own `TOC_Size`). `consumption` is a simple
     * average across rows — fine for *display* when they agree, and
     * `consistent` is false when they don't so the caller can flag it as a
     * size-blended approximation. `rows` carries each row's own size + rate
     * so the caller can scale each one by that SIZE's real, independently
     * tracked cutting qty (from the QC yield matrix, not from D365 at all)
     * — deliberately NOT pre-multiplied here by `TOC_QtyOrder`/`OrderQty`.
     * Both of those are D365's own PLANNING figures — the same basis
     * `ProposalBOMQuantity` (Mats Sent's fallback, see
     * SubconProductionService::materialIssueForOrder()) is already computed
     * from — so multiplying by either one here would make any "Mats Sent vs.
     * this rate scaled up" comparison tautological (plan vs. a second
     * derivation of the same plan, canceling to ~0 regardless of what
     * actually happened; confirmed live on real orders). Scaling by the
     * REAL cutting qty instead — production's actual, independently-tracked
     * output, which is usually lower than the planned order qty — is what
     * makes that comparison capable of showing genuine signal.
     *
     * Deliberately does NOT surface `TOC_Price` — Price stays sourced from
     * the existing local/VSM pricing path (resolveFabricPricing() for fabric,
     * accessoryLinesForPo()'s weighted average for accessories); this call is
     * for Consumption only.
     *
     * Best-effort: returns [] on any failure, cached ~1h like the other D365
     * lookups here.
     *
     * @param  array<int, string>  $plmIds
     * @return array<string, array{consumption: float, unit: ?string, consistent: bool, rows: array<int, array{size: ?string, consumption: float}>}> keyed by ItemId
     */
    public function fetchFinalRMConsumption(array $plmIds): array
    {
        $plmIds = array_values(array_filter(array_unique($plmIds)));
        if (empty($plmIds)) {
            return [];
        }

        $cacheKey = 'd365_final_rm_consumption_'.md5(implode(',', $plmIds));
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $token = $this->getD365Token();
        if (! $token) {
            return [];
        }

        $rowsByItem = [];
        try {
            foreach (array_chunk($plmIds, 20) as $chunk) {
                $filters = array_map(fn ($id) => 'TOC_PLM_Id eq '.$this->odataLit($id), $chunk);
                $filterStr = '('.implode(' or ', $filters).')';
                $url = $this->getResource()."/data/TOC_FinalRMPrices?\$filter={$filterStr}&cross-company=true";

                $response = Http::withToken($token)
                    ->withHeaders(['OData-MaxVersion' => '4.0', 'OData-Version' => '4.0', 'Accept' => 'application/json'])
                    ->timeout(30)
                    ->get($url);

                if (! $response->successful()) {
                    Log::warning('D365 final RM consumption fetch failed. Status: '.$response->status());

                    continue;
                }

                foreach ($response->json()['value'] ?? [] as $line) {
                    $item = $line['TOC_ItemIDLine'] ?? null;
                    if ($item === null || $item === '') {
                        continue;
                    }
                    // TOC_Size: which size this row's rate applies to (blank
                    // for a non-size-specific material). Deliberately NOT
                    // capturing TOC_QtyOrder/OrderQty here — both are D365
                    // planning figures, not real production output; see this
                    // method's docblock for why scaling by them made "Mats
                    // Sent vs. plan" comparisons tautological.
                    $rowsByItem[$item][] = [
                        'consumption' => (float) ($line['TOC_Consumption'] ?? 0),
                        'size' => trim((string) ($line['TOC_Size'] ?? '')) ?: null,
                        'unit' => $line['UOM'] ?? null,
                    ];
                }
            }

            $out = [];
            foreach ($rowsByItem as $item => $rows) {
                $values = array_column($rows, 'consumption');
                $min = min($values);
                $max = max($values);
                $out[$item] = [
                    'consumption' => array_sum($values) / count($values),
                    'unit' => $rows[0]['unit'] ?? null,
                    'consistent' => ($max - $min) < 0.0001,
                    'rows' => array_map(fn ($r) => ['size' => $r['size'], 'consumption' => $r['consumption']], $rows),
                ];
            }

            Cache::put($cacheKey, $out, now()->addHour());
        } catch (\Throwable $e) {
            Log::error('Exception fetching D365 final RM consumption: '.$e->getMessage());

            return [];
        }

        return $out;
    }
}
