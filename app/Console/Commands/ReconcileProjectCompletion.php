<?php

namespace App\Console\Commands;

use App\Models\SubconOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ReconcileProjectCompletion extends Command
{
    protected $signature = 'subcon:reconcile-project-completion
                            {--fix : Revert affected packaging_projects.status to \'downloaded\' (default: report only)}';

    protected $description = "Safety net for a QC Console bug (forge db.rs save_packaging_project_db_only — source fixed 2026-08-24 commit 95adae1, but not yet rebuilt/redistributed): its completion guard only checked Stage 1/2 signatures, so a stale client-side 'completed' status could mark a project done while it was still mid Director re-review (ho_validation_signature set, director_approval_signature never actually signed). Scans packaging_projects for exactly that state and reports or reverts to 'downloaded' so the project reappears in the pending Director-approval queue. Projects that never entered the Director gate (the older two-stage flow, ho_validation_signature never set) are left untouched.";

    public function handle(): int
    {
        if (! Schema::connection('qms')->hasTable('packaging_projects')
            || ! Schema::connection('qms')->hasTable('packaging_project_sessions')) {
            $this->info('QMS tables unreachable — nothing to check.');

            return Command::SUCCESS;
        }

        $projects = DB::connection('qms')->table('packaging_projects')
            ->where('status', 'completed')
            ->get(['project_id', 'production_group', 'po_info', 'updated_at']);

        $fix = (bool) $this->option('fix');
        $affected = 0;

        foreach ($projects as $project) {
            $session = DB::connection('qms')->table('packaging_project_sessions')
                ->where('project_id', $project->project_id)
                ->orderByDesc('cycle_number')
                ->first(['ho_validation_signature', 'director_approval_signature']);

            if (! $session) {
                continue;
            }

            $enteredDirectorGate = trim((string) ($session->ho_validation_signature ?? '')) !== '';
            $directorAuthorized = str_contains((string) ($session->director_approval_signature ?? ''), 'Digitally Signed:');

            if (! $enteredDirectorGate || $directorAuthorized) {
                continue; // legacy two-stage completion, or genuinely authorized — leave alone
            }

            $affected++;
            $order = SubconOrder::where('production_group', $project->production_group)->first();
            $label = $order->order_number ?? $project->po_info ?? $project->project_id;

            if ($fix) {
                DB::connection('qms')->table('packaging_projects')
                    ->where('project_id', $project->project_id)
                    ->where('status', 'completed')
                    ->update(['status' => 'downloaded', 'updated_at' => now()]);

                Log::warning('subcon:reconcile-project-completion reverted a mis-completed project (no Director authorization)', [
                    'project_id' => $project->project_id,
                    'order_number' => $label,
                ]);
                $this->info("Reverted: {$label} ({$project->project_id}) — was marked completed {$project->updated_at} without Director authorization.");
            } else {
                $this->warn("Would revert: {$label} ({$project->project_id}) — marked completed {$project->updated_at} but Director never authorized it.");
            }
        }

        if ($affected === 0) {
            $this->info('Nothing to reconcile.');
        } else {
            $this->info($fix
                ? "Reverted {$affected} project(s)."
                : "Found {$affected} project(s) needing correction. Re-run with --fix to apply.");
        }

        return Command::SUCCESS;
    }
}
