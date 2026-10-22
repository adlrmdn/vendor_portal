<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/**
 * Runs one of the three PC D365/VSM sync commands off the request cycle, so
 * the admin "Refresh now" button returns immediately. Mirrors
 * SyncSubconOrdersJob's RUNNING_KEY/LAST_KEY cache contract, but
 * parameterized per feed since there are three independent feeds here
 * rather than one.
 */
class PcSyncFeedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public const RUNNING_TTL = 600;

    /** @var array<string, string> feed name => artisan command */
    public const COMMANDS = [
        'vendor-enrichment' => 'pc:enrich-vendors',
        'fabric-items' => 'pc:sync-fabric-items',
        'plm-refs' => 'pc:sync-plm-refs',
    ];

    public function __construct(public string $feed) {}

    public static function runningKey(string $feed): string
    {
        return "pc_sync:{$feed}:running";
    }

    public static function lastKey(string $feed): string
    {
        return "pc_sync:{$feed}:last";
    }

    public function handle(): void
    {
        $command = self::COMMANDS[$this->feed] ?? null;

        if (! $command) {
            return;
        }

        try {
            // Each command defaults --company itself where it applies (pc:sync-plm-refs
            // has no such option) — no args passed here so this job works for all three.
            $exit = Artisan::call($command);
            $message = trim((string) Artisan::output());

            Cache::put(self::lastKey($this->feed), [
                'result' => $exit === 0 ? 'success' : 'failed',
                'message' => $message !== '' ? $message : null,
                'finished_at' => now()->toIso8601String(),
            ], now()->addDays(7));
        } finally {
            Cache::forget(self::runningKey($this->feed));
        }
    }
}
