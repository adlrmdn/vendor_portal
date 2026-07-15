<?php

namespace App\Jobs;

use App\Models\SubconOrder;
use App\Services\D365JobTransactionService;
use App\Services\SubconProductionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Push approved cutting quantities or gramasi values for a subcon work order
 * to D365, off the request cycle. This is the "original mechanism" that only
 * runs once the relevant gate has been *approved* — so it is dispatched from
 * SubconApprovalController after the stage transition is saved, never blocking
 * the Approve action. Best-effort: D365 problems are logged, not surfaced to
 * the approver (the local state is already authoritative).
 */
class SyncSubconReportToD365 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 200;

    /**
     * @param  string  $orderId  SubconOrder UUID
     * @param  string  $gate  'cutting' | 'gramasi'
     */
    public function __construct(public string $orderId, public string $gate) {}

    public function handle(SubconProductionService $production, D365JobTransactionService $d365): void
    {
        $order = SubconOrder::with('cuttingReports')->find($this->orderId);
        if (! $order) {
            return;
        }

        $groups = $production->forPo($order->order_number);
        $group = ! empty($groups) ? ($groups[0]['production_group'] ?? null) : null;
        if (! $group) {
            Log::warning("D365 {$this->gate} sync skipped for {$order->order_number}: no production group link.");

            return;
        }

        // Build the changed-rows payload for the approved gate.
        $changed = [];
        foreach ($order->cuttingReports as $r) {
            if ($this->gate === 'cutting' && (int) $r->cutting_qty > 0) {
                $changed[] = ['size' => $r->size, 'cutting_qty' => (int) $r->cutting_qty];
            } elseif ($this->gate === 'gramasi' && $r->gramasi !== null && (float) $r->gramasi > 0) {
                $changed[] = ['size' => $r->size, 'gramasi' => (float) $r->gramasi];
            }
        }

        if (empty($changed)) {
            return;
        }

        $d365->startJobs($group);
        $result = $d365->syncReportToD365($group, $changed);

        if (! empty($result['errors'])) {
            $msg = implode(' ', $result['errors']);
            Log::warning("D365 {$this->gate} sync for {$order->order_number} returned warnings: " . $msg);
            
            \App\Models\SubconJobLog::create([
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'job_type' => $this->gate,
                'status' => 'failed',
                'message' => $msg,
            ]);
        } else {
            Log::info("D365 {$this->gate} sync completed for {$order->order_number} (".count($changed).' rows).');
            
            \App\Models\SubconJobLog::create([
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'job_type' => $this->gate,
                'status' => 'success',
                'message' => "Successfully synced " . ($this->gate === 'cutting' ? 'cutting quantities' : 'grammage weights') . " (" . count($changed) . " sizes).",
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error("D365 {$this->gate} sync failed for order {$this->orderId}: ".$e->getMessage());

        $order = SubconOrder::find($this->orderId);
        if ($order) {
            \App\Models\SubconJobLog::create([
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'job_type' => $this->gate,
                'status' => 'failed',
                'message' => $e->getMessage(),
            ]);
        }
    }
}
