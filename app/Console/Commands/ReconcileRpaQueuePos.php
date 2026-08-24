<?php

namespace App\Console\Commands;

use App\Models\SubconOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileRpaQueuePos extends Command
{
    protected $signature = 'rpa:reconcile-po
                            {--id=* : Specific rpa_queues row id(s) to check}
                            {--fix : Correct packaging_projects.po_info/po_qty and the queued payload, and set status=pending for immediate retry (default: report only)}
                            {--include-test : Also process rows whose entity_id starts with "TEST" (excluded by default)}';

    protected $description = 'Safety net for RpaQueueService::matchesLocalOrder() self-heal: scans already-queued invoice/deduction rows for a PO/qty that has since drifted from the synced SubconOrder (e.g. D365 reissued the PO after the row was queued) and reports or corrects them.';

    public function handle(): int
    {
        $query = DB::connection('rpa')->table('rpa_queues')
            ->whereIn('rpa_type', ['invoice', 'deduction'])
            ->whereIn('status', ['pending', 'waiting']);

        $ids = array_map('intval', $this->option('id'));
        if (! empty($ids)) {
            $query->whereIn('id', $ids);
        }

        $rows = $query->get(['id', 'rpa_type', 'status', 'entity_id', 'payload']);

        if (! $this->option('include-test')) {
            $rows = $rows->reject(fn ($r) => str_starts_with((string) $r->entity_id, 'TEST'));
        }

        if ($rows->isEmpty()) {
            $this->info('Nothing to check.');

            return Command::SUCCESS;
        }

        $fix = (bool) $this->option('fix');
        $mismatches = 0;

        foreach ($rows->groupBy('entity_id') as $projectId => $projectRows) {
            $project = DB::connection('qms')->table('packaging_projects')
                ->where('project_id', $projectId)
                ->first(['project_id', 'production_group', 'po_info', 'po_qty', 'sales_price']);

            if (! $project || empty($project->production_group)) {
                continue;
            }

            $order = SubconOrder::where('production_group', $project->production_group)->first();
            if (! $order) {
                continue;
            }

            $orderQty = (float) DB::table('subcon_order_items')->where('order_id', $order->id)->sum('quantity');
            $poDrifted = (string) $project->po_info !== '' && (string) $project->po_info !== $order->order_number;
            $qtyDrifted = $orderQty > 0 && (float) $project->po_qty > 0
                && abs($orderQty - (float) $project->po_qty) > max(1.0, $orderQty * 0.01);

            if (! $poDrifted && ! $qtyDrifted) {
                continue;
            }

            $mismatches++;
            $this->warn(sprintf(
                'project %s: po_info=%s%s po_qty=%s%s (production_group=%s)',
                $projectId,
                $project->po_info,
                $poDrifted ? " -> {$order->order_number}" : '',
                $project->po_qty,
                $qtyDrifted ? " -> {$orderQty}" : '',
                $project->production_group,
            ));

            foreach ($projectRows as $row) {
                $this->line("  #{$row->id} ({$row->rpa_type}, {$row->status})".($fix ? ' — fixing' : ' — would fix'));
            }

            if (! $fix) {
                continue;
            }

            DB::connection('qms')->table('packaging_projects')
                ->where('project_id', $projectId)
                ->update([
                    'po_info' => $order->order_number,
                    'po_qty' => $orderQty,
                    'updated_at' => now(),
                ]);

            foreach ($projectRows as $row) {
                $payload = json_decode($row->payload, true) ?: [];
                $payload['PO'] = $order->order_number;
                if ($row->rpa_type === 'invoice') {
                    $payload['amount'] = (float) $project->sales_price * $orderQty;
                }

                DB::connection('rpa')->table('rpa_queues')->where('id', $row->id)->update([
                    'payload' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'status' => 'pending',
                    'updated_at' => now(),
                ]);
            }
        }

        if ($mismatches === 0) {
            $this->info('No PO/qty drift found among '.$rows->count().' queued row(s).');
        } elseif ($fix) {
            $this->info("Fixed {$mismatches} project(s).");
        } else {
            $this->info("Found {$mismatches} drifted project(s). Re-run with --fix to correct them.");
        }

        return Command::SUCCESS;
    }
}
