<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Run the full subcon refresh off the request cycle so the admin "Sync POs"
 * button returns immediately. The flow is, in order:
 *
 *   1. Refresh VSM (D365 -> VSM ETL) via the host vsm-sync-api service, and
 *      wait for it to finish — VSM holds the PLM / distribution data the portal
 *      reads, so it must be current before we pull POs.
 *   2. Run the local d365:sync-subcon-orders ETL.
 *
 * A cache flag (RUNNING_KEY) marks the run in progress so the dashboard can
 * disable the button and poll; STAGE_KEY exposes which step we are on, and
 * LAST_KEY stores the outcome for display.
 */
class SyncSubconOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Single run at a time; a retry would double-run the ETL. */
    public int $tries = 1;

    /** VSM (~20 min) + PO sync — cap generously so a wedged run still ends. */
    public int $timeout = 2700;

    /** Cache key: truthy while a run is in progress. */
    public const RUNNING_KEY = 'subcon_sync:running';

    /** Cache key: human-readable current step (for the dashboard). */
    public const STAGE_KEY = 'subcon_sync:stage';

    /** Cache key: last finished outcome (result/message/finished_at). */
    public const LAST_KEY = 'subcon_sync:last';

    /** Safety TTL on the running flag in case the worker dies mid-run. */
    public const RUNNING_TTL = 2700;

    public function handle(): void
    {
        try {
            $vsmNote = $this->runVsmSync();

            $this->stage('Syncing subcon POs…');
            $exit = Artisan::call('d365:sync-subcon-orders', [
                '--company' => 'mpg',
                '--days' => 180,
            ]);
            $poMessage = $this->lastLine(Artisan::output());

            if ($exit === 0) {
                $this->finish('success', trim($vsmNote.' '.($poMessage ?: 'PO sync completed.')));
            } else {
                $this->finish('failed', trim($vsmNote.' PO sync exited with code '.$exit.'. '.$poMessage));
            }
        } catch (\Throwable $e) {
            Log::error('Subcon sync failed: '.$e->getMessage());
            $this->finish('failed', 'Sync failed: '.$e->getMessage());
        }
    }

    /**
     * Trigger the host VSM sync and wait for completion. Best-effort: if the
     * service is unreachable or VSM itself fails, we log it and still continue
     * to the PO sync against whatever VSM currently holds (mirrors the hourly
     * scheduler, which never had a VSM step). Returns a short note for the UI.
     */
    private function runVsmSync(): string
    {
        $base = rtrim((string) env('VSM_SYNC_URL', ''), '/');
        if ($base === '') {
            return 'VSM: not configured.';
        }

        $headers = [
            'X-API-Key' => env('VSM_SYNC_API_KEY', ''),
            'X-Dynamic-Key' => date('ymd', strtotime('-2 days')),
            'Accept' => 'application/json',
        ];

        $this->stage('Syncing VSM…');

        try {
            $start = Http::withHeaders($headers)->timeout(30)->post($base.'/sync');
            if (! $start->successful()) {
                Log::warning('VSM sync trigger failed: HTTP '.$start->status());

                return 'VSM: trigger failed (HTTP '.$start->status().').';
            }

            // Poll until the service reports it is no longer running, or we hit
            // the ceiling (~25 min) — then fall through to the PO sync anyway.
            $deadline = time() + 1500;
            do {
                sleep(15);
                $status = Http::withHeaders($headers)->timeout(30)->get($base.'/status');
                $running = $status->successful() ? (bool) $status->json('running') : false;
            } while ($running && time() < $deadline);

            if ($running) {
                return 'VSM: timed out, using existing data.';
            }

            $result = $status->json('last_result');

            return $result === 'success'
                ? 'VSM: refreshed.'
                : 'VSM: '.($result ?: 'unknown').'.';
        } catch (\Throwable $e) {
            Log::warning('VSM sync step error: '.$e->getMessage());

            return 'VSM: unavailable.';
        }
    }

    private function stage(string $label): void
    {
        Cache::put(self::STAGE_KEY, $label, self::RUNNING_TTL);
    }

    /** Persist outcome and release the running flag + stage. */
    private function finish(string $result, string $message): void
    {
        Cache::put(self::LAST_KEY, [
            'result' => $result,
            'message' => $message,
            'finished_at' => now()->toIso8601String(),
        ], now()->addDays(7));

        Cache::forget(self::RUNNING_KEY);
        Cache::forget(self::STAGE_KEY);
    }

    /** If the worker is killed mid-run, still clear the flags. */
    public function failed(\Throwable $e): void
    {
        $this->finish('failed', 'Sync failed: '.$e->getMessage());
    }

    private function lastLine(string $output): string
    {
        $lines = array_values(array_filter(
            array_map('trim', explode("\n", $output)),
            fn ($l) => $l !== ''
        ));

        return $lines ? (string) end($lines) : '';
    }
}
