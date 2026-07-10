<?php

namespace App\Jobs;

use App\Models\SubconOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Generate packing labels for a subcon work order off the request cycle.
 *
 * Resolves the distribution_id (if still empty) from VSM/D365, then calls the
 * external DTT label-generator service. On success the order advances to the
 * "labels" stage, which unlocks printing for the vendor. Dispatched from both
 * the in-app admin button and the signed email link so neither click blocks on
 * the (up-to-30s) DTT call. Failures are retried a few times, then logged.
 */
class GenerateSubconLabels implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** DTT/D365 hiccups are usually transient — retry a few times. */
    public int $tries = 3;

    public int $backoff = 30;

    /**
     * Keep at most one in-flight label-gen job per order. Each attempt blocks the
     * single queue worker for ~50s on the DTT call; without this, repeated
     * "Generate Labels" clicks stack duplicate jobs and saturate the worker while
     * the DTT bot is down (order never advances — the "stuck" symptom). The lock
     * releases when the job finishes (success or final failure), so the admin can
     * retry once the current attempt has exhausted its tries.
     */
    public int $uniqueFor = 600;

    public function uniqueId(): string
    {
        return $this->orderId;
    }

    /**
     * Must exceed the DTT HTTP timeout below (180s) so the queue worker doesn't
     * kill the job mid-call — Laravel's default 60s would otherwise cut off a
     * DTT run that legitimately takes minutes to build the packing instruction.
     */
    public int $timeout = 200;

    /** @param string $orderId SubconOrder UUID */
    public function __construct(public string $orderId) {}

    public function handle(): void
    {
        $order = SubconOrder::find($this->orderId);
        if (! $order) {
            return;
        }

        // Stage may have moved on (e.g. generated already, or reset) between
        // dispatch and execution — only act while genuinely waiting.
        if ($order->workflow_stage !== SubconOrder::STAGE_WAITING_DISTRIBUTION) {
            Log::info("Label generation skipped for {$order->order_number}: stage is {$order->workflow_stage}.");
            $order->clearLabelGenState();

            return;
        }

        $this->resolveDistributionId($order);

        if (empty($order->distribution_id)) {
            throw new \RuntimeException("Distribution ID missing/unresolvable for {$order->order_number}.");
        }

        $url = env('LABEL_GENERATOR_URL', 'http://localhost:8071/process');
        $apiKey = env('LABEL_GENERATOR_API_KEY', 'DT-Secret-2026');
        $dynamicKey = date('ymd', strtotime('-2 days'));

        $response = Http::withHeaders([
            'X-API-Key' => $apiKey,
            'X-Dynamic-Key' => $dynamicKey,
            'Content-Type' => 'application/json',
        ])->timeout(180)->post($url, ['dst' => $order->distribution_id]);

        $apiSuccess = false;
        $errorMsg = '';

        if ($response->successful()) {
            $resData = $response->json();
            if ($resData && ($resData['success'] ?? false) === true) {
                $apiSuccess = true;
            } else {
                // DTT reports the reason under "error" (Playwright/D365 UI faults,
                // e.g. the FilterField_TOC_DT_DTID selector timing out); fall back
                // to "message" for older responses.
                $errorMsg = $resData['error'] ?? $resData['message'] ?? 'API response indicated failure.';
            }
        } else {
            // FastAPI puts the reason under "detail" (e.g. 401 "Login failed: …",
            // 500 unexpected error); fall back to "error" then the bare status.
            $body = $response->json();
            $detail = is_array($body) ? ($body['detail'] ?? $body['error'] ?? null) : null;
            $errorMsg = $detail
                ? 'API '.$response->status().': '.$detail
                : 'API returned status '.$response->status();
        }

        // Outside production, treat a DTT failure as success so the workflow can
        // still be exercised without the service running (mirrors the old
        // synchronous behaviour).
        if (! $apiSuccess && config('app.env') !== 'production') {
            Log::warning("Bypassing label generator failure on non-production for {$order->order_number}: {$errorMsg}");
            $apiSuccess = true;
        }

        if (! $apiSuccess) {
            throw new \RuntimeException("DTT label generation failed for {$order->order_number}: {$errorMsg}");
        }

        $order->workflow_stage = SubconOrder::STAGE_LABELS;
        $order->label_gen_status = null;
        $order->label_gen_error = null;
        $order->save();

        Log::info("Packing labels generated for {$order->order_number}; printing unlocked.");

        // The DTT bot has just created the packing instruction (TOC_PI). Recompute
        // Coli/Blister on its lines per the agreed packing ruling. Best-effort —
        // printing is already unlocked, so a D365 hiccup here must not fail the job.
        try {
            $stats = app(\App\Services\D365JobTransactionService::class)->syncPackingColiBlister($order);
            Log::info("Coli/Blister sync for {$order->order_number}: ".json_encode($stats));
        } catch (\Throwable $e) {
            Log::warning("Coli/Blister sync threw for {$order->order_number}: ".$e->getMessage());
        }
    }

    /** Best-effort resolution of distribution_id from VSM → D365 TOC_DT. */
    private function resolveDistributionId(SubconOrder $order): void
    {
        if (! empty($order->distribution_id)) {
            return;
        }

        try {
            $plmId = DB::connection('vsm')->table('po_lines')
                ->where('PurchaseOrderNumber', $order->order_number)
                ->whereNotNull('PLMId')
                ->where('PLMId', '!=', '')
                ->value('PLMId');

            if (! $plmId) {
                return;
            }

            $soId = DB::connection('vsm')->table('plm_trans')
                ->where('PLMId', $plmId)
                ->where('ActivityName', 'SO Intercompany')
                ->whereNotNull('ActivityNo')
                ->where('ActivityNo', '!=', '')
                ->value('ActivityNo');

            if (! $soId) {
                return;
            }

            $tenantId = config('services.d365.tenant_id');
            $clientId = config('services.d365.client_id');
            $clientSecret = config('services.d365.client_secret');
            $resource = config('services.d365.resource');

            $authResponse = Http::asForm()->post("https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token", [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'scope' => "{$resource}/.default",
            ]);

            $token = $authResponse->successful() ? ($authResponse->json()['access_token'] ?? null) : null;
            if (! $token) {
                return;
            }

            $filter = "SOID eq '{$soId}'";
            $queryUrl = "{$resource}/data/TOC_DT?\$filter=".rawurlencode($filter).'&cross-company=true';
            $queryResponse = Http::withToken($token)->acceptJson()->get($queryUrl);

            if ($queryResponse->successful()) {
                $dst = $queryResponse->json()['value'][0]['DistributionID'] ?? null;
                if ($dst) {
                    $order->distribution_id = $dst;
                    $order->save();
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to resolve distribution ID for order {$order->order_number}: ".$e->getMessage());
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error("Label generation failed for order {$this->orderId}: ".$e->getMessage());

        // Persist the reason so the portal can show the admin exactly why the RPA
        // could not generate the packing instruction, and re-enable the button.
        $order = SubconOrder::find($this->orderId);
        if ($order) {
            $order->markLabelGenFailed($e->getMessage());
        }
    }
}
