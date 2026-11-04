<?php

namespace App\Console\Commands;

use App\Models\SubconFabricReconciliation;
use App\Models\SubconOrder;
use App\Services\SubconConsumptionService;
use App\Services\SubconFabricLinePublisher;
use App\Services\SubconProductionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SubconRecalcFabricSent extends Command
{
    protected $signature = 'subcon:recalc-fabric-sent {--dry-run}';

    protected $description = "One-off backfill for the Fabric Sent -> Goods Receive change (SubconConsumptionService::persist() now pins a VSM-linked fabric's Fabric Sent to the confirmed D365 Goods Receive quantity instead of trusting a typed figure). Re-runs persist() for every order that already has fabric reconciliation data (cutting approved) but whose production group has NOT yet reached Director approval in the QC/HO/Director workflow, so stale typed figures get corrected and cutt_plan/actual_consumption/deduction recompute off the real receipt, then republishes the corrected snapshot to QMS. Orders already Director-approved are left untouched since those figures are final.";

    public function handle(SubconProductionService $production, SubconConsumptionService $consumption, SubconFabricLinePublisher $publisher): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $orderIds = SubconFabricReconciliation::query()->distinct()->pluck('order_id');
        if ($orderIds->isEmpty()) {
            $this->info('No fabric reconciliation data to recalculate.');

            return Command::SUCCESS;
        }

        $orders = SubconOrder::whereIn('id', $orderIds)->get();

        $directorApprovedGroups = [];
        if (Schema::connection('qms')->hasTable('packaging_project_sessions') && Schema::connection('qms')->hasTable('packaging_projects')) {
            $directorApprovedGroups = DB::connection('qms')->table('packaging_project_sessions as s')
                ->join('packaging_projects as p', 'p.project_id', '=', 's.project_id')
                ->whereNotNull('s.director_approval_signature')
                ->where('s.director_approval_signature', '!=', '')
                ->pluck('p.production_group')
                ->filter()
                ->map(fn ($pg) => trim((string) $pg))
                ->unique()
                ->flip()
                ->all();
        }

        $touched = 0;
        foreach ($orders as $order) {
            $pg = trim((string) $order->production_group);
            if ($pg !== '' && isset($directorApprovedGroups[$pg])) {
                continue; // already Director-approved — figures are final, leave them alone
            }

            $recons = SubconFabricReconciliation::where('order_id', $order->id)->get();
            if ($recons->isEmpty()) {
                continue;
            }

            if ($dryRun) {
                $vsmLines = $production->fabricLinesForPo($order->order_number);
                $goodsReceive = [];
                try {
                    $goodsReceive = $production->resolveGoodsReceipts($vsmLines);
                } catch (\Throwable $e) {
                    $this->warn("  {$order->order_number}: Goods Receive lookup failed ({$e->getMessage()}), skipping preview.");

                    continue;
                }

                foreach ($recons as $r) {
                    $gr = $goodsReceive[$r->label]['qty'] ?? null;
                    if ($gr === null) {
                        continue; // manual fabric or no receipt yet — nothing to change
                    }
                    $old = $r->fabric_sent !== null ? round((float) $r->fabric_sent, 2) : null;
                    $new = round((float) $gr, 2);
                    if ($old !== $new) {
                        $this->line("  {$order->order_number} / {$r->label}: fabric_sent {$old} -> {$new}");
                    }
                }

                continue;
            }

            $fabrics = $recons->map(fn ($r) => [
                'label' => $r->label,
                'fabric_sent' => $r->fabric_sent,
                'consumption_plan' => $r->consumption_plan,
                'fabric_price' => $r->fabric_price,
                'short_roll' => $r->short_roll,
                'sisa_kain' => $r->sisa_kain,
                'kepala_kain' => $r->kepala_kain,
                'retur_kain' => $r->retur_kain,
                'shortage' => $r->shortage,
            ])->all();

            $consumption->persist($order, $fabrics);
            $publisher->publish($order);
            $touched++;
        }

        $this->info($dryRun ? 'Dry run complete.' : "Recalculated {$touched} order(s).");

        return Command::SUCCESS;
    }
}
