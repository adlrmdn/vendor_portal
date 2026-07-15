<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Writes jobs into the shared `rpa_queues` table (remote `rpa` connection) at
 * approval milestones, replacing the QC Console's removed "Complete & Sync"
 * triggers. Payload shapes are exact ports of the console's Rust builders
 * (forge src-tauri/src/db.rs) so the downstream RPA bots see no difference:
 *
 *   - job_trans_raf  — queued when MD Production approves (stage 2).
 *     status 'pending'; payload carries the per-version (final_1/2/3…) per-size
 *     good/reject matrix aggregated from QMS packaging_project_reports.
 *   - invoice        — queued when the Director approves (stage 3).
 *     status 'incomplete'; amount = sales_price × po_qty.
 *   - deduction      — queued with invoice, only when the portal-computed
 *     deduction total (Σ fabric_lines.deduction + Σ session deduction lines) > 0.
 *
 * Every method is best-effort: the RPA DB is remote (ap-southeast-3) and an
 * outage must never block an approval — failures are logged and swallowed.
 */
class RpaQueueService
{
    /**
     * Port of the console's queue_job_trans_raf_internal(). Reads the project +
     * report matrix from QMS, groups sessions into final_N version labels
     * (cycles 1+2 → final_1, 3 → final_2, 4 → final_3, then one per cycle) and
     * upserts the 'job_trans_raf' job (update payload while still 'pending').
     */
    public function queueJobTransRaf(string $projectId): bool
    {
        try {
            $project = DB::connection('qms')->table('packaging_projects')
                ->where('project_id', $projectId)
                ->first(['production_group', 'cmt_pak_job_id', 'po_info']);
            if (! $project) {
                Log::warning('RPA raf: project not found', ['project' => $projectId]);

                return false;
            }

            // Unique sizes in display order.
            $sizeRows = DB::connection('qms')->table('packaging_project_reports')
                ->where('project_id', $projectId)
                ->orderBy('line_no')->orderBy('global_display_order')
                ->get(['size_val']);
            $orderedSizes = [];
            foreach ($sizeRows as $r) {
                $sz = trim((string) ($r->size_val ?? ''));
                if ($sz !== '' && ! in_array($sz, $orderedSizes, true)) {
                    $orderedSizes[] = $sz;
                }
            }

            // Inspection sessions (excluding the cycle-0 baseline) + their lines.
            $sessions = DB::connection('qms')->table('packaging_project_sessions')
                ->where('project_id', $projectId)->where('cycle_number', '>=', 1)
                ->orderBy('cycle_number')
                ->get(['session_id', 'cycle_number', 'inspection_date']);

            $sessionsList = [];
            $maxCycle = 4;
            foreach ($sessions as $s) {
                $lines = DB::connection('qms')->table('packaging_project_reports')
                    ->where('project_id', $projectId)->where('session_id', $s->session_id)
                    ->get([
                        'size_val', 'session_qty', 'reject_produksi', 'reject_finishing',
                        'reject_embro', 'barang_hilang', 'reject_cutting', 'reject_printing',
                        'reject_sewing', 'reject_washing', 'btj', 'reject_bahan',
                    ]);
                $sessionsList[] = ['cycle' => (int) $s->cycle_number, 'date' => $s->inspection_date, 'lines' => $lines];
                $maxCycle = max($maxCycle, (int) $s->cycle_number);
            }

            // Version-label groups: final_1 = cycles 1+2, final_2 = 3, final_3 = 4,
            // then final_(c-1) for each further cycle — identical to the console.
            $groupDefs = [
                ['label' => 'final_1', 'cycles' => [1, 2]],
                ['label' => 'final_2', 'cycles' => [3]],
                ['label' => 'final_3', 'cycles' => [4]],
            ];
            for ($c = 5; $c <= $maxCycle; $c++) {
                $groupDefs[] = ['label' => 'final_'.($c - 1), 'cycles' => [$c]];
            }

            $sessionsPayload = [];
            foreach ($groupDefs as $g) {
                $groupSessions = array_values(array_filter($sessionsList, fn ($s) => in_array($s['cycle'], $g['cycles'], true)));
                if (empty($groupSessions)) {
                    continue;
                }

                $inspectionDate = '';
                foreach ($groupSessions as $gs) {
                    if (! empty($gs['date'])) {
                        $inspectionDate = substr((string) $gs['date'], 0, 10);
                    }
                }

                // Aggregate good qty + each reject metric per size across the group.
                $sizeMap = [];
                foreach ($groupSessions as $gs) {
                    foreach ($gs['lines'] as $l) {
                        $sz = trim((string) ($l->size_val ?? ''));
                        if ($sz === '') {
                            continue;
                        }
                        $m = $sizeMap[$sz] ?? array_fill_keys([
                            'good_qty', 'reject_qty', 'reject_produksi', 'reject_finishing',
                            'reject_embro', 'barang_hilang', 'reject_cutting', 'reject_printing',
                            'reject_sewing', 'reject_washing', 'btj', 'reject_bahan',
                        ], 0);
                        $rejBah = (int) ($l->reject_bahan ?? 0);
                        $rejCut = (int) ($l->reject_cutting ?? 0);
                        $rejSew = (int) ($l->reject_sewing ?? 0);
                        $rejFin = (int) ($l->reject_finishing ?? 0);
                        $rejPrt = (int) ($l->reject_printing ?? 0);
                        $rejEmb = (int) ($l->reject_embro ?? 0);
                        $rejWas = (int) ($l->reject_washing ?? 0);
                        $btj = (int) ($l->btj ?? 0);
                        $barHil = (int) ($l->barang_hilang ?? 0);

                        $m['good_qty'] += (float) ($l->session_qty ?? 0);
                        $m['reject_qty'] += $rejBah + $rejCut + $rejSew + $rejFin + $rejPrt + $rejEmb + $rejWas + $btj + $barHil;
                        $m['reject_produksi'] += (int) ($l->reject_produksi ?? 0);
                        $m['reject_finishing'] += $rejFin;
                        $m['reject_embro'] += $rejEmb;
                        $m['barang_hilang'] += $barHil;
                        $m['reject_cutting'] += $rejCut;
                        $m['reject_printing'] += $rejPrt;
                        $m['reject_sewing'] += $rejSew;
                        $m['reject_washing'] += $rejWas;
                        $m['btj'] += $btj;
                        $m['reject_bahan'] += $rejBah;
                        $sizeMap[$sz] = $m;
                    }
                }

                $sizesJson = [];
                foreach ($orderedSizes as $sz) {
                    if (isset($sizeMap[$sz])) {
                        $sizesJson[] = array_merge(['size' => $sz], $sizeMap[$sz]);
                    }
                }

                $sessionsPayload[] = [
                    'version_label' => $g['label'],
                    'inspection_date' => $inspectionDate,
                    'sizes' => $sizesJson,
                ];
            }

            $payload = [
                'project_id' => $projectId,
                'production_group' => (string) ($project->production_group ?? ''),
                'job_transaction_id' => (string) ($project->cmt_pak_job_id ?? ''),
                'po_info' => (string) ($project->po_info ?? ''),
                'sessions' => $sessionsPayload,
            ];

            $this->upsertJob($projectId, 'job_trans_raf', 'pending', $payload, updatableStatus: 'pending', activeStatuses: ['pending', 'processing']);

            return true;
        } catch (\Throwable $e) {
            Log::error('RPA raf queue failed', ['project' => $projectId, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Invoice job — console shape: PO / vendor_name / latest_version /
     * signed_doc (the fully-signed PDF data URI) / amount = sales_price × po_qty.
     */
    public function queueInvoice(object $project, string $latestVersion, string $signedDoc): bool
    {
        try {
            $payload = [
                'PO' => (string) ($project->po_info ?? ''),
                'vendor_name' => (string) ($project->po_vendor ?? ''),
                'latest_version' => $latestVersion,
                'signed_doc' => $signedDoc,
                'amount' => (float) ($project->sales_price ?? 0) * (float) ($project->po_qty ?? 0),
            ];

            $this->upsertJob((string) $project->project_id, 'invoice', 'incomplete', $payload, updatableStatus: 'incomplete', activeStatuses: ['incomplete', 'processing']);

            return true;
        } catch (\Throwable $e) {
            Log::error('RPA invoice queue failed', ['project' => $project->project_id ?? null, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Deduction job — queued only when $amount > 0 (the portal-computed total:
     * Σ fabric overconsumption deductions + Σ manual HO deduction lines).
     * When the total is zero, stale incomplete/failed jobs are cleaned up
     * instead — mirroring the console's old behaviour.
     */
    public function queueDeduction(object $project, string $latestVersion, string $signedDoc, float $amount): bool
    {
        try {
            $projectId = (string) $project->project_id;

            if ($amount <= 0) {
                DB::connection('rpa')->table('rpa_queues')
                    ->where('entity_id', $projectId)->where('rpa_type', 'deduction')
                    ->whereIn('status', ['incomplete', 'failed'])
                    ->delete();

                return true;
            }

            $payload = [
                'PO' => (string) ($project->po_info ?? ''),
                'vendor_name' => (string) ($project->po_vendor ?? ''),
                'latest_version' => $latestVersion,
                'signed_doc' => $signedDoc,
                'amount' => $amount,
            ];

            $this->upsertJob($projectId, 'deduction', 'incomplete', $payload, updatableStatus: 'incomplete', activeStatuses: ['incomplete', 'processing']);

            return true;
        } catch (\Throwable $e) {
            Log::error('RPA deduction queue failed', ['project' => $project->project_id ?? null, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * The portal-computed deduction total for a project: fabric overconsumption
     * charges (packaging_project_fabric_lines.deduction) + the manual deduction
     * lines HO added on this session. Replaces the console-entered
     * packaging_projects.deduction_amount now that Complete & Sync is gone.
     */
    public function deductionTotal(string $projectId, ?string $sessionId): float
    {
        $total = 0.0;

        try {
            $total += (float) DB::connection('qms')->table('packaging_project_fabric_lines')
                ->where('project_id', $projectId)->sum('deduction');
        } catch (\Throwable $e) {
            Log::warning('RPA deduction total: fabric lines read failed', ['project' => $projectId, 'error' => $e->getMessage()]);
        }

        try {
            if ($sessionId) {
                $total += (float) DB::connection('qms')->table('packaging_session_deduction_lines')
                    ->where('session_id', $sessionId)->sum('amount');
            }
        } catch (\Throwable $e) {
            Log::warning('RPA deduction total: deduction lines read failed', ['project' => $projectId, 'error' => $e->getMessage()]);
        }

        return round($total, 2);
    }

    /**
     * Console-identical upsert: refresh the payload while a job of this type is
     * still in its updatable status; insert a fresh row otherwise (unless one is
     * already processing).
     *
     * @param  array<string,mixed>  $payload
     * @param  list<string>  $activeStatuses
     */
    private function upsertJob(string $entityId, string $rpaType, string $insertStatus, array $payload, string $updatableStatus, array $activeStatuses): void
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $exists = DB::connection('rpa')->table('rpa_queues')
            ->where('entity_id', $entityId)->where('rpa_type', $rpaType)
            ->whereIn('status', $activeStatuses)
            ->exists();

        if ($exists) {
            DB::connection('rpa')->table('rpa_queues')
                ->where('entity_id', $entityId)->where('rpa_type', $rpaType)
                ->where('status', $updatableStatus)
                ->update(['payload' => $json, 'updated_at' => now()]);
        } else {
            DB::connection('rpa')->table('rpa_queues')->insert([
                'entity_type' => 'packaging_project',
                'entity_id' => $entityId,
                'rpa_type' => $rpaType,
                'status' => $insertStatus,
                'payload' => $json,
            ]);
        }

        Log::info('RPA job queued', ['entity' => $entityId, 'type' => $rpaType]);
    }
}
