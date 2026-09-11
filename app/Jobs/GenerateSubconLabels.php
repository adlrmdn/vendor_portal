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
class GenerateSubconLabels implements ShouldBeUnique, ShouldQueue
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

        // Labels already exist (this is a re-trigger, e.g. after the admin
        // corrected blister/sack capacity): the packing instruction is already
        // built in D365, so there is nothing to (re)generate via the DTT bot —
        // just resync Coli/Blister on it with the current capacity values.
        if (in_array($order->workflow_stage, [SubconOrder::STAGE_LABELS, SubconOrder::STAGE_COMPLETED], true)) {
            $this->recalculateColiBlister($order);

            return;
        }

        // Stage may have moved on (e.g. reset) between dispatch and execution —
        // only run the full generation flow while genuinely waiting.
        if ($order->workflow_stage !== SubconOrder::STAGE_WAITING_DISTRIBUTION) {
            Log::info("Label generation skipped for {$order->order_number}: stage is {$order->workflow_stage}.");
            $order->clearLabelGenState();

            return;
        }

        $unresolvedReason = $this->resolveDistributionId($order);

        if (empty($order->distribution_id)) {
            throw new \RuntimeException($unresolvedReason
                ?? "Distribution ID missing/unresolvable for {$order->order_number}.");
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

        \App\Models\SubconJobLog::create([
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'job_type' => 'label_generation',
            'status' => 'success',
            'message' => 'Successfully generated packing instruction and unlocked printing.',
        ]);

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

    /**
     * Re-trigger path for an order that already has labels: resync Coli/Blister
     * on the existing packing instruction against the order's current
     * blister_capacity/sack_capacity, without touching the DTT bot or the
     * workflow stage. Used both for the "Generate Labels" retry on an
     * already-labelled order and for the on-demand button on the order detail
     * page after the admin edits capacity.
     */
    private function recalculateColiBlister(SubconOrder $order): void
    {
        if (empty($order->distribution_id)) {
            $order->markLabelGenFailed('No distribution ID on file — labels must be generated at least once before capacity can be recalculated.');

            return;
        }

        try {
            $stats = app(\App\Services\D365JobTransactionService::class)->syncPackingColiBlister($order);
            Log::info("Coli/Blister recalculation for {$order->order_number}: ".json_encode($stats));

            if (! empty($stats['errors'])) {
                $order->markLabelGenFailed('Recalculation errors: '.implode('; ', $stats['errors']));

                \App\Models\SubconJobLog::create([
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'job_type' => 'label_generation',
                    'status' => 'failed',
                    'message' => 'Coli/Blister recalculation errors: '.implode('; ', $stats['errors']),
                ]);

                return;
            }

            $order->clearLabelGenState();

            \App\Models\SubconJobLog::create([
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'job_type' => 'label_generation',
                'status' => 'success',
                'message' => "Recalculated Coli/Blister with updated capacity ({$stats['patched']} line(s) patched, {$stats['skipped']} unchanged).",
            ]);
        } catch (\Throwable $e) {
            Log::warning("Coli/Blister recalculation threw for {$order->order_number}: ".$e->getMessage());
            $order->markLabelGenFailed($e->getMessage());

            \App\Models\SubconJobLog::create([
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'job_type' => 'label_generation',
                'status' => 'failed',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Best-effort resolution of distribution_id from VSM → D365 TOC_DT.
     *
     * Primary path: `production_groups` carries a direct PONumber → SONumber
     * mapping — it covers "standalone" production groups (no PLMId at all;
     * `po_lines.PLMId` being empty there is expected, not a data gap) as well
     * as PLM-routed ones. It's preferred over the PLM chain below because
     * it's keyed on the PO itself, whereas a single PLMId can legitimately be
     * shared across unrelated articles/seasons (its SO Intercompany activity
     * would then resolve to the wrong order's DST).
     *
     * Fallback: the PLM 'SO Intercompany' activity → TOC_DT by SOID, then the
     * PLM 'Budget Buying' activity, which carries the DST number directly (it
     * names the same TOC_DT document the SO path resolves to) — used when the
     * SO Intercompany activity was left unfilled in D365. The Budget Buying
     * candidate is verified against TOC_DT (must exist, be Confirmed, and
     * match the PLM's ArticleCode when both sides carry one) before use.
     *
     * Returns null on success (or when nothing was found at all); returns a
     * human-readable reason when a DST document was located but rejected —
     * currently only "not confirmed" — so the caller can report the real
     * blocker instead of the generic "missing/unresolvable".
     *
     * Manual overrides: some standalone PO/PRG rows never get `SONumber`
     * filled in on the VSM `production_groups` mirror even though the SO
     * Intercompany + DST already exist and are Confirmed in D365 (seen on
     * MPG/PO/2607/01528 and .../01536 — production_groups never resolved,
     * and the PLM chain was a dead end because PLM/26/05/00005 and
     * PLM/26/05/00003 are reused for an unrelated "Manzone Raka" article
     * under mpr, so their SO Intercompany/Budget Buying activities point at
     * the wrong DST entirely). Rather than guess a DST via ArticleID
     * matching — which the same PLMId-reuse problem makes unsafe to
     * automate — known-good DSTs confirmed by hand against D365 TOC_DT are
     * listed here and applied first, still re-verified as Confirmed.
     *
     * PLM-id fallback via local production_group: `po_lines` normally carries
     * the PO's own "Item Jasa CMT" line (with its PLMId), but that row can be
     * missing from the VSM mirror entirely — either sync lag, or (seen on
     * MPG/PO/2609/00189) the production group is tagged `ProductionType:
     * "In-house"` in VSM, so the automated PONumber backfill on
     * `production_groups` never expects a CMT subcon PO to attach to it and
     * skips it. `SubconOrder.production_group` is already known locally
     * (set at PO sync time), so when po_lines comes up empty, look up its
     * PLMId directly off `production_groups.ProductionGroup` — that column
     * is populated independently of PONumber/ProductionType — before giving
     * up on the PLM chain.
     */
    private const DISTRIBUTION_ID_OVERRIDES = [
        'MPG/PO/2607/01528' => 'MTI/DST/2605/00028',
        'MPG/PO/2607/01536' => 'MTI/DST/2605/00029',
    ];

    private function resolveDistributionId(SubconOrder $order): ?string
    {
        if (! empty($order->distribution_id)) {
            return null;
        }

        if ($override = self::DISTRIBUTION_ID_OVERRIDES[$order->order_number] ?? null) {
            $row = $this->fetchTocDt("DistributionID eq '{$override}'");
            if (! empty($row['DistributionID']) && ($row['DocumentStatus'] ?? '') === 'Confirmed') {
                Log::info("Distribution ID for {$order->order_number} resolved via manual override: {$override}.");
                $order->distribution_id = $row['DistributionID'];
                $order->save();

                return null;
            }

            Log::warning("Distribution override {$override} for {$order->order_number} is no longer valid/confirmed in TOC_DT; falling back to normal resolution.");
        }

        $unconfirmed = null;

        try {
            $groupSoId = DB::connection('vsm')->table('production_groups')
                ->where('PONumber', $order->order_number)
                ->whereNotNull('SONumber')
                ->where('SONumber', '!=', '')
                ->value('SONumber');

            if ($groupSoId) {
                $row = $this->fetchTocDt("SOID eq '{$groupSoId}'");
                if (! empty($row['DistributionID'])) {
                    if (($row['DocumentStatus'] ?? '') === 'Confirmed') {
                        Log::info("Distribution ID for {$order->order_number} resolved via production_groups: SO {$groupSoId}.");
                        $order->distribution_id = $row['DistributionID'];
                        $order->save();

                        return null;
                    }

                    // The DST exists but merchandising hasn't confirmed the DT
                    // document yet — keep trying the PLM paths, but remember the
                    // real blocker so the admin isn't told the DST is "missing".
                    $unconfirmed = "DST {$row['DistributionID']} is not confirmed (status: "
                        .(($row['DocumentStatus'] ?? '') !== '' ? $row['DocumentStatus'] : 'unknown')
                        .") for {$order->order_number}.";
                    Log::info("Distribution for {$order->order_number} found via SO {$groupSoId} but not usable: {$unconfirmed}");
                }
            }

            $plmId = DB::connection('vsm')->table('po_lines')
                ->where('PurchaseOrderNumber', $order->order_number)
                ->whereNotNull('PLMId')
                ->where('PLMId', '!=', '')
                ->value('PLMId');

            if (! $plmId && ! empty($order->production_group)) {
                $plmId = DB::connection('vsm')->table('production_groups')
                    ->where('ProductionGroup', $order->production_group)
                    ->whereNotNull('PLMId')
                    ->where('PLMId', '!=', '')
                    ->value('PLMId');

                if ($plmId) {
                    Log::info("PLM id for {$order->order_number} resolved via local production_group {$order->production_group} (po_lines had no row).");
                }
            }

            if (! $plmId) {
                return $unconfirmed;
            }

            // Primary: SO Intercompany → TOC_DT by SOID.
            $soId = DB::connection('vsm')->table('plm_trans')
                ->where('PLMId', $plmId)
                ->where('ActivityName', 'SO Intercompany')
                ->whereNotNull('ActivityNo')
                ->where('ActivityNo', '!=', '')
                ->value('ActivityNo');

            if ($soId) {
                $row = $this->fetchTocDt("SOID eq '{$soId}'");
                if (! empty($row['DistributionID'])) {
                    $order->distribution_id = $row['DistributionID'];
                    $order->save();

                    return null;
                }
            }

            // Fallback: Budget Buying names the DST document directly.
            $candidate = (string) DB::connection('vsm')->table('plm_trans')
                ->where('PLMId', $plmId)
                ->where('ActivityName', 'Budget Buying')
                ->whereNotNull('ActivityNo')
                ->where('ActivityNo', '!=', '')
                ->value('ActivityNo');

            if (! str_contains($candidate, '/DST/')) {
                return $unconfirmed;
            }

            $row = $this->fetchTocDt("DistributionID eq '{$candidate}'");
            if (empty($row['DistributionID'])) {
                return $unconfirmed;
            }

            if (($row['DocumentStatus'] ?? '') !== 'Confirmed') {
                $unconfirmed ??= "DST {$row['DistributionID']} is not confirmed (status: "
                    .(($row['DocumentStatus'] ?? '') !== '' ? $row['DocumentStatus'] : 'unknown')
                    .") for {$order->order_number}.";

                return $unconfirmed;
            }

            $article = (string) DB::connection('vsm')->table('plm_activity')
                ->where('PLMId', $plmId)->value('ArticleCode');
            if ($article !== '' && ($row['ArticleID'] ?? '') !== '' && $row['ArticleID'] !== $article) {
                Log::warning("Distribution fallback rejected for {$order->order_number}: {$candidate} carries article {$row['ArticleID']}, PLM says {$article}.");

                return $unconfirmed;
            }

            Log::info("Distribution ID for {$order->order_number} resolved via Budget Buying fallback: {$candidate} (SO Intercompany unfilled in PLM).");
            $order->distribution_id = $row['DistributionID'];
            $order->save();

            return null;
        } catch (\Throwable $e) {
            Log::warning("Failed to resolve distribution ID for order {$order->order_number}: ".$e->getMessage());
        }

        return $unconfirmed;
    }

    /** First TOC_DT row (cross-company) matching the OData filter, or null. */
    private function fetchTocDt(string $filter): ?array
    {
        $resource = config('services.d365.resource');
        $token = $this->d365Token();
        if (! $token) {
            return null;
        }

        $queryUrl = "{$resource}/data/TOC_DT?\$filter=".rawurlencode($filter).'&cross-company=true';
        $response = Http::withToken($token)->acceptJson()->get($queryUrl);

        return $response->successful() ? ($response->json()['value'][0] ?? null) : null;
    }

    /** Client-credentials token for the D365 OData API (cached per job run). */
    private ?string $d365Token = null;

    private function d365Token(): ?string
    {
        if ($this->d365Token !== null) {
            return $this->d365Token;
        }

        $tenantId = config('services.d365.tenant_id');
        $resource = config('services.d365.resource');

        $authResponse = Http::asForm()->post("https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token", [
            'grant_type' => 'client_credentials',
            'client_id' => config('services.d365.client_id'),
            'client_secret' => config('services.d365.client_secret'),
            'scope' => "{$resource}/.default",
        ]);

        return $this->d365Token = ($authResponse->successful() ? ($authResponse->json()['access_token'] ?? null) : null);
    }

    public function failed(\Throwable $e): void
    {
        Log::error("Label generation failed for order {$this->orderId}: ".$e->getMessage());

        // Persist the reason so the portal can show the admin exactly why the RPA
        // could not generate the packing instruction, and re-enable the button.
        $order = SubconOrder::find($this->orderId);
        if ($order) {
            $order->markLabelGenFailed($e->getMessage());

            \App\Models\SubconJobLog::create([
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'job_type' => 'label_generation',
                'status' => 'failed',
                'message' => $e->getMessage(),
            ]);
        }
    }
}
