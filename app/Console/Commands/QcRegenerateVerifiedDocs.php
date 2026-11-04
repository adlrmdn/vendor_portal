<?php

namespace App\Console\Commands;

use App\Services\QcReportPdfService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off backfill: re-render packaging_projects.verified_doc for every QC
 * session that has actually been reviewed (QC and/or HO signed off) but has
 * NOT yet reached Director approval, so stale documents generated before a
 * report-formatting fix (e.g. the Retur Kain/Shortage double-count fix +
 * Selisih/Shortage field) pick up the correction. Sessions already
 * Director-approved are left alone — that document is final, same policy as
 * subcon:recalc-fabric-sent.
 *
 * Scoped to approval_signature/ho_approval_signature being set, NOT just
 * "has a verified_doc" — of ~800 not-yet-director-approved sessions, the
 * vast majority are untouched draft placeholders the console auto-creates
 * (one "BASE-" row per project) that already carry a blank verified_doc but
 * were never actually reviewed by anyone; regenerating those is pointless
 * churn against an external production DB. Restricting to signed sessions
 * narrows this to the ~40 reports a human has actually looked at.
 */
class QcRegenerateVerifiedDocs extends Command
{
    protected $signature = 'qc:regenerate-pdfs {--dry-run}';

    protected $description = 'Re-render verified_doc for every QC session that has been QC/HO-reviewed but not yet Director-approved.';

    public function handle(QcReportPdfService $pdfService): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $rows = DB::connection('qms')->table('packaging_project_sessions as s')
            ->join('packaging_projects as p', 'p.project_id', '=', 's.project_id')
            ->where(function ($q) {
                $q->whereNull('s.director_approval_signature')
                    ->orWhere('s.director_approval_signature', '');
            })
            ->whereNotNull('p.verified_doc')
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNotNull('s.approval_signature')->where('s.approval_signature', '!=', '');
                })->orWhere(function ($q2) {
                    $q2->whereNotNull('s.ho_approval_signature')->where('s.ho_approval_signature', '!=', '');
                });
            })
            ->select('s.project_id', 's.session_id', 'p.production_group')
            ->get();

        if ($rows->isEmpty()) {
            $this->info('No not-yet-director-approved sessions with a stored PDF to regenerate.');

            return Command::SUCCESS;
        }

        $this->info("{$rows->count()} session(s) to regenerate.");

        $touched = 0;
        $failed = 0;
        foreach ($rows as $row) {
            if ($dryRun) {
                $this->line("  would regenerate: {$row->project_id} / {$row->session_id} ({$row->production_group})");

                continue;
            }

            try {
                $doc = $pdfService->renderDataUri((string) $row->project_id, (string) $row->session_id);
                if ($doc === null) {
                    $this->warn("  {$row->project_id} / {$row->session_id}: render returned nothing, skipped.");
                    $failed++;

                    continue;
                }
                $pdfService->writeVerifiedDoc((string) $row->project_id, $doc);
                $this->line("  regenerated: {$row->project_id} / {$row->session_id}");
                $touched++;
            } catch (\Throwable $e) {
                $this->warn("  {$row->project_id} / {$row->session_id}: failed ({$e->getMessage()})");
                $failed++;
            }
        }

        $this->info($dryRun ? 'Dry run complete.' : "Regenerated {$touched} PDF(s), {$failed} failed/skipped.");

        return Command::SUCCESS;
    }
}
