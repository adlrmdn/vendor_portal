<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Supplementary, best-effort lookup against D365's TOC_FDRs entity
 * ("Fabric Development Request") — confirmed live (FDR/25/06/00175) to
 * carry vendor + item + composition/construction/HS code/price for a
 * specific vendor's sampled/approved fabric. User's explicit call:
 * this stays supplementary — it does NOT replace the item+PLM picker or
 * change pc_fabric_items' sync source. It only offers a non-binding
 * reference panel next to a contract line when a match exists, never
 * auto-filled, never required, never blocks line entry if D365 is
 * unreachable. Same defensive/cached shape as
 * D365JobTransactionService::fetchFabricPricing.
 */
class PcFdrLookupService
{
    private function getResource(): string
    {
        return config('services.d365.resource') ?? 'https://megaperintis.operations.dynamics.com';
    }

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
     * @return array{fdr_number: string, composition: ?string, construction: ?string,
     *     cut_width: ?string, full_width: ?string, fabric_weight: ?string,
     *     fabric_process: ?string, fabric_finishing: ?string, hs_code: ?string,
     *     uom: ?string, price: ?float, currency: ?string}|null
     */
    public function findForItemAndVendor(string $itemNumber, string $vendorCode): ?array
    {
        $cacheKey = 'pc_fdr_lookup_'.md5($itemNumber.'|'.$vendorCode);
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $result = null;

        try {
            $token = $this->getD365Token();
            if ($token) {
                $filter = "ItemId eq '{$itemNumber}' and VendAccountLines eq '{$vendorCode}'";
                $url = $this->getResource()."/data/TOC_FDRs?\$filter=".rawurlencode($filter).'&cross-company=true';

                $response = Http::withToken($token)->acceptJson()->timeout(15)->get($url);

                if ($response->successful()) {
                    $rows = $response->json()['value'] ?? [];
                    // Prefer a Completed/Approve line if more than one matches.
                    usort($rows, fn ($a, $b) => ($b['ApprovalLines'] === 'Approve' ? 1 : 0) <=> ($a['ApprovalLines'] === 'Approve' ? 1 : 0));
                    $row = $rows[0] ?? null;

                    if ($row) {
                        $result = [
                            'fdr_number' => $row['TOC_FDR_IdLines'] ?? $row['TOC_FDR_Id'] ?? '',
                            'composition' => $row['Composition'] ?? null,
                            'construction' => $row['Construction'] ?? null,
                            'cut_width' => $row['CutWidth'] ?? null,
                            'full_width' => $row['FullWidth'] ?? null,
                            'fabric_weight' => $row['FabricWeight'] ?? null,
                            'fabric_process' => $row['FabricProcess'] ?? null,
                            'fabric_finishing' => $row['FabricFinishing'] ?? null,
                            'hs_code' => $row['HS_Code'] ?? null,
                            'uom' => $row['UnitId'] ?? null,
                            'price' => isset($row['Price']) ? (float) $row['Price'] : null,
                            'currency' => $row['Currency'] ?? null,
                        ];
                    }
                } else {
                    Log::warning('PC FDR lookup failed. Status: '.$response->status());
                }
            }
        } catch (\Throwable $e) {
            Log::warning('PC FDR lookup exception: '.$e->getMessage());
        }

        Cache::put($cacheKey, $result, now()->addHour());

        return $result;
    }
}
