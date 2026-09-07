<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Pull a single newly-created fabric vendor's confirmed POs from D365 right
 * away, off the request cycle — otherwise the vendor sits with zero orders
 * until the next scheduled `d365:sync-orders` run. Best-effort: a D365/network
 * problem here just means the vendor waits for the next scheduled sync like
 * before, so it is logged rather than surfaced to the admin who created it.
 */
class SyncNewFabricVendorOrders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public string $vendorCode) {}

    public function handle(): void
    {
        try {
            $exit = Artisan::call('d365:sync-orders', ['--vendor' => [$this->vendorCode]]);

            if ($exit !== 0) {
                Log::warning('Immediate PO sync for new fabric vendor exited non-zero.', [
                    'vendor_code' => $this->vendorCode,
                    'exit' => $exit,
                    'output' => Artisan::output(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Immediate PO sync for new fabric vendor failed: '.$e->getMessage(), [
                'vendor_code' => $this->vendorCode,
            ]);
        }
    }
}
