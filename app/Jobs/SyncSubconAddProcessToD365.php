<?php

namespace App\Jobs;

use App\Models\SubconOrder;
use App\Services\D365JobTransactionService;
use App\Services\RpaQueueService;
use App\Services\SubconProductionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Push admin-entered add-process (ADD-Was/ADD-Embro/ADD-Sab/ADD-Pleat/...)
 * quantities for a production group to D365, off the request cycle. Keyed
 * directly by ProductionGroup rather than a SubconOrder — add-process applies
 * to in-house production groups too, which never get a subcon work order.
 * Unlike SyncSubconReportToD365 (cutting/gramasi), this dispatches straight
 * from the admin's submit — there is no separate approval gate, since the
 * admin is entering the figures directly rather than a vendor self-reporting
 * them. A production group with more than one ADD-* operation gets one of
 * these jobs per operation — each is entirely independent.
 *
 * DELIBERATELY does NOT call D365JobTransactionService::startJobs() — that
 * jobt-api command creates/starts job transactions for the production
 * group's WHOLE route (CMT-Cut/CMT-Pak or IN-Cut/IN-Sew/IN-Fin, not just the
 * ADD-* step), which is the CMT/in-house cutting workflow's own RPA action
 * (SyncSubconReportToD365 already calls it for that reason). Add Process
 * must stay disconnected from that lifecycle: it only ever PATCHes/triggers
 * the specific ADD-* JobTransactionId that must already exist. If it
 * doesn't yet, syncAddProcessToD365() surfaces that as a normal retryable
 * error ("job header not found ... may not be started yet") instead of this
 * job reaching over to start the whole route itself.
 */
class SyncSubconAddProcessToD365 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 200;

    /**
     * @param  string  $productionGroup  D365 ProductionGroup code
     * @param  string  $operation  Raw D365 Operation value, e.g. "ADD-Was"
     * @param  array  $changed  [['size' => ..., 'qty' => ..., 'reject_qty' => ...], ...]
     */
    public function __construct(public string $productionGroup, public string $operation, public array $changed) {}

    public function handle(D365JobTransactionService $d365, SubconProductionService $production, RpaQueueService $rpaQueue): void
    {
        if (empty($this->changed)) {
            return;
        }

        // Throwing on failure is what makes $tries/$backoff actually fire — see
        // SyncSubconReportToD365's docblock for the same reasoning. No
        // startJobs() call here — see class docblock for why.
        $result = $d365->syncAddProcessToD365($this->productionGroup, $this->operation, $this->changed);

        if (! empty($result['errors'])) {
            throw new \RuntimeException(implode(' ', $result['errors']));
        }

        Log::info("D365 add-process ({$this->operation}) sync completed for PRG {$this->productionGroup} (".count($this->changed).' rows).');

        $this->logResult('success', "Successfully synced {$this->operation} quantities (".count($this->changed).' sizes).');

        // Goods receipt against the matched AddPro PO — qty is the sum of
        // good qty across sizes in THIS batch (a delivery/try), not an
        // all-time running total; see RpaQueueService::queueAddProcessGoodsReceive()'s
        // docblock for why (each submit becomes its own numbered receipt,
        // PO-I/PO-II/...). Best-effort and after the D365 side has already
        // succeeded — never blocks or retries the job transaction sync above.
        $goodQty = array_sum(array_column($this->changed, 'qty'));
        if ($goodQty > 0) {
            $matchedPo = $production->matchAddProcessPoForOperation(
                $production->addProcessPurchaseOrdersForGroup($this->productionGroup),
                $this->operation
            );
            $lineNumber = $matchedPo
                ? $production->resolveAddProcessLineNumber($matchedPo['po_number'], $this->operation)
                : null;

            if ($matchedPo && $lineNumber !== null) {
                $rpaQueue->queueAddProcessGoodsReceive($this->productionGroup, $this->operation, $matchedPo['po_number'], $lineNumber, $goodQty);
            } else {
                Log::warning("Skipped goods_receive_addprocess queueing for {$this->operation} on PRG {$this->productionGroup} — no matched PO or resolvable line number.");
            }
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error("D365 add-process ({$this->operation}) sync failed for PRG {$this->productionGroup} after {$this->tries} attempt(s): ".$e->getMessage());

        $this->logResult('failed', $e->getMessage());
    }

    /**
     * SubconJobLog is order-shaped (order_number required, order_id optional) —
     * an in-house production group has no SubconOrder at all, so this falls
     * back to the raw production group as the log's "order_number" when no
     * matching CMT order exists, rather than skipping the log entirely.
     */
    private function logResult(string $status, string $message): void
    {
        $order = SubconOrder::where('production_group', $this->productionGroup)->first();

        \App\Models\SubconJobLog::create([
            'order_id' => $order?->id,
            'order_number' => $order?->order_number ?? $this->productionGroup,
            'job_type' => "add_process:{$this->operation}",
            'status' => $status,
            'message' => $message,
        ]);
    }
}
