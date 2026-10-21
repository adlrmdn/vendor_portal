<?php

namespace App\Services;

use App\Models\SubconOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
 *     status 'pending'; amount = sales_price × po_qty. Payload carries the
 *     fully-signed PDF as a data URI (signed_doc) — the heaviest of the three.
 *   - deduction      — queued with invoice, only when the portal-computed
 *     deduction total (Σ fabric_lines.deduction + Σ session deduction lines +
 *     reject/lost-items penalty) > 0. Same signed_doc-bearing shape as invoice.
 *   - goods_receive_addprocess — queued when an Add Process (ADD-Was/Embro/
 *     Sab/Pleat) report syncs to D365 successfully (SyncSubconAddProcessToD365).
 *     NOT a port of a console builder — this is a portal-original rpa_type,
 *     deliberately kept separate from the WMS Goods Receiver scanner app's
 *     own `wms_queues`/`good_receipt_headers` (that schema's
 *     `good_receipt_lines.roll_id` is NOT NULL — fabric-roll bookkeeping that
 *     doesn't fit a garment service). A dedicated listener (automaton/
 *     pw_service/goods_receive/addprocess_listener.py) polls this rpa_type
 *     and forwards the payload as-is to the SAME automation the scanner app
 *     already drives (goods_receive/gr-api, POST /process-receipt) — see
 *     queueAddProcessGoodsReceive()'s docblock.
 *
 * S3 offload (shared `rpa_lake` bucket, stable per-entity key overwritten on
 * every upsert — mirrors the DB row's own upsert semantics) is split by how
 * heavy each payload actually is:
 *   - job_trans_raf: the whole payload is small (a few KB even at max cycles)
 *     but is offloaded wholesale via offloadToS3() anyway, since nothing in
 *     this app reads it back — only the automaton listener does, from S3.
 *     DB pointer: {s3_bucket, s3_key, project_id, job_transaction_id}.
 *   - invoice/deduction: PO/vendor_name/amount/latest_version are read
 *     directly off the DB row by RpaQueueReadService (finance admin tables +
 *     the subcon vendor's Invoices tab) and must stay inline and cheap to
 *     list — only `signed_doc` (the fully-signed PDF, easily several MB) is
 *     offloaded via offloadSignedDocToS3(), replaced by a plain `s3_key`
 *     (no bucket field — the debit_note/invoice RPA bot's existing payload
 *     contract already checks for `s3_key` and defaults to bucket
 *     `rpa-lake`; see offloadSignedDocToS3()'s docblock) alongside the
 *     untouched fields. FinanceAdminController/SubconAdminController resolve
 *     it from S3 via RpaQueueReadService::resolveSignedDoc().
 * The automaton-side consumers must resolve these pointers before use, so
 * both shapes are a fixed, stable contract.
 *
 * Every method is best-effort: the RPA DB is remote (ap-southeast-3) and an
 * outage must never block an approval — failures are logged and swallowed.
 */
class RpaQueueService
{
    /** Tags rows this portal inserts, distinguishing them from the QC Console's own queue writes. */
    private const SOURCE = 'subcon_vendor_portal';

    public function __construct(private D365JobTransactionService $d365, private QcReportPdfService $reportPdf) {}

    /**
     * Port of the console's queue_job_trans_raf_internal(). Reads the project +
     * report matrix from QMS, groups sessions into final_N version labels
     * (cycles 1+2 → final_1, 3 → final_2, 4 → final_3, then one per cycle) and
     * upserts the 'job_trans_raf' job (update payload while still 'pending').
     *
     * One deliberate deviation from the console: a version-label group whose
     * aggregated qty is all-zero (every size, good AND reject) is dropped from
     * the payload instead of being sent as a to-be-created line. The automaton
     * (automaton/pw_service/reg_raf) can only write zeros into a line that
     * D365 already has — a genuinely NEW line with nothing to report never
     * gets its "Add line" button enabled, so the RPA bot burns all 3 retries
     * and the job lands 'failed' (rpa_queues id 390, project
     * PRJ-MPG-PRG-2606-000274-1786780150937, 2026-09-21 — cycles 1+2 both
     * recorded literally nothing). Each group still consumes a line number
     * whether or not it's sent, and a sent group carries that number
     * explicitly as `line_no` (the automaton's Session.line_no override), so
     * dropping an empty early group can never shift a later, already-filled
     * D365 line out from under itself.
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

            // The stored cmt_pak_job_id is a snapshot from whenever the console
            // first downloaded the project — D365 can reissue job transaction
            // ids for a production group later (the same event that renumbers
            // production_group_lines.ProdId in VSM), which silently stales it
            // and makes the RPA bot fail against a job id that no longer
            // exists. Re-resolve from D365 every time and self-heal the column
            // when it drifts; best-effort — fall back to the stored value if
            // D365 is unreachable rather than blocking the queue.
            $productionGroup = (string) ($project->production_group ?? '');
            $pakJobId = $project->cmt_pak_job_id;
            if ($productionGroup !== '') {
                $resolved = $this->d365->resolveJobTransactionIds($productionGroup);
                if (! empty($resolved['pak']) && $resolved['pak'] !== $pakJobId) {
                    Log::warning('RPA raf: cmt_pak_job_id drifted from D365, self-healing', [
                        'project' => $projectId,
                        'stored' => $pakJobId,
                        'resolved' => $resolved['pak'],
                    ]);
                    DB::connection('qms')->table('packaging_projects')
                        ->where('project_id', $projectId)
                        ->update(['cmt_pak_job_id' => $resolved['pak']]);
                    $pakJobId = $resolved['pak'];
                }
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
            $lineNo = 0;
            foreach ($groupDefs as $g) {
                // Counted against every group def (not just ones we end up sending)
                // so a group skipped below for being empty doesn't shift later
                // groups' line numbers — those must stay pinned to their D365
                // job-transaction line via the explicit line_no below, since
                // "Add line" always appends after the current max line and the
                // automaton has no other way to target a specific line.
                $lineNo++;

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
                $hasQty = false;
                foreach ($orderedSizes as $sz) {
                    if (isset($sizeMap[$sz])) {
                        $sizesJson[] = array_merge(['size' => $sz], $sizeMap[$sz]);
                        if ($sizeMap[$sz]['good_qty'] > 0 || $sizeMap[$sz]['reject_qty'] > 0) {
                            $hasQty = true;
                        }
                    }
                }

                // A QC cycle that recorded literally nothing (no good qty, no
                // reject of any kind, on every size) has nothing to report as
                // finished — skip it rather than asking the RPA bot to "Add
                // line" a brand-new, all-zero D365 job-transaction line. D365
                // rejects that: the Add-line button never becomes enabled for
                // it, which burns all 3 retry attempts and lands the job as
                // 'failed' (e.g. rpa_queues id 390, project
                // PRJ-MPG-PRG-2606-000274-1786780150937, cycles 1+2 both
                // recorded zero — 2026-09-21). Existing groups already filled
                // in D365 (e.g. final_2 -> line 2) are unaffected: line_no is
                // pinned explicitly below so a skipped earlier group can't
                // shift them.
                if (! $hasQty) {
                    continue;
                }

                $sessionsPayload[] = [
                    'line_no' => $lineNo,
                    'version_label' => $g['label'],
                    'inspection_date' => $inspectionDate,
                    'sizes' => $sizesJson,
                ];
            }

            $payload = [
                'project_id' => $projectId,
                'production_group' => $productionGroup,
                'job_transaction_id' => (string) ($pakJobId ?? ''),
                'po_info' => (string) ($project->po_info ?? ''),
                'sessions' => $sessionsPayload,
            ];

            $queuedPayload = $this->offloadToS3('job_trans_raf', $projectId, $payload);
            if (isset($queuedPayload['s3_key'])) {
                $queuedPayload['job_transaction_id'] = $payload['job_transaction_id'];
            }

            // A previously failed attempt (e.g. against a since-reissued job id)
            // must not block this retry from landing as a fresh 'pending' row —
            // upsertJob() only refreshes 'pending'/'processing' jobs in place, so
            // a stale 'failed' row would otherwise sit next to a new one forever.
            DB::connection('rpa')->table('rpa_queues')
                ->where('entity_id', $projectId)->where('rpa_type', 'job_trans_raf')
                ->where('status', 'failed')
                ->delete();

            $this->upsertJob($projectId, 'job_trans_raf', 'pending', $queuedPayload, updatableStatus: 'pending', activeStatuses: ['pending', 'processing']);

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
        if (! $this->matchesLocalOrder($project)) {
            return false;
        }

        try {
            $projectId = (string) $project->project_id;

            $payload = [
                'PO' => (string) ($project->po_info ?? ''),
                'vendor_name' => (string) ($project->po_vendor ?? ''),
                'latest_version' => $latestVersion,
                'signed_doc' => $signedDoc,
                'amount' => (float) ($project->sales_price ?? 0) * (float) ($project->po_qty ?? 0),
            ];

            $queuedPayload = $this->offloadSignedDocToS3('invoice', $projectId, $payload);

            $this->upsertJob($projectId, 'invoice', 'pending', $queuedPayload, updatableStatus: 'pending', activeStatuses: ['pending', 'waiting', 'incomplete', 'processing']);

            return true;
        } catch (\Throwable $e) {
            Log::error('RPA invoice queue failed', ['project' => $project->project_id ?? null, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Deduction job — queued only when $amount > 0 (the portal-computed total:
     * Σ fabric overconsumption deductions + Σ manual HO deduction lines).
     * When the total is zero, stale unprocessed/failed jobs are cleaned up
     * instead — mirroring the console's old behaviour.
     *
     * Lands as 'pending' same as invoice — the debit-note PDF is generated
     * with Document No/Invoice Date blank (RPA hasn't run yet); the
     * debit_note RPA bot fills them in (pdf_fill.overlay_fields) once it
     * looks up the D365 invoice journal entry, no portal-side gating.
     *
     * Two documents travel with this job: $signedDoc (the debit-note page
     * combined with the report — what D365 gets attached, and what Finance
     * reviews) under the existing `signed_doc`/`s3_key` keys the RPA bot's
     * contract already expects, and $originalDoc (the plain report, same
     * document invoice gets) under `original_report`/`original_report_s3_key`
     * — Subcon Admin's Debit Notes tab always shows this one
     * (SubconAdminController::debitNoteReport), never the debit-note variant.
     */
    public function queueDeduction(object $project, string $latestVersion, string $signedDoc, float $amount, ?string $originalDoc = null): bool
    {
        if (! $this->matchesLocalOrder($project)) {
            return false;
        }

        try {
            $projectId = (string) $project->project_id;

            if ($amount <= 0) {
                DB::connection('rpa')->table('rpa_queues')
                    ->where('entity_id', $projectId)->where('rpa_type', 'deduction')
                    ->whereIn('status', ['pending', 'waiting', 'incomplete', 'failed'])
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
            if ($originalDoc !== null && $originalDoc !== '') {
                $payload['original_report'] = $originalDoc;
            }

            $queuedPayload = $this->offloadSignedDocToS3('deduction', $projectId, $payload);
            $queuedPayload = $this->offloadOriginalReportToS3('deduction', $projectId, $queuedPayload);

            $this->upsertJob($projectId, 'deduction', 'pending', $queuedPayload, updatableStatus: 'pending', activeStatuses: ['pending', 'waiting', 'incomplete', 'processing']);

            return true;
        } catch (\Throwable $e) {
            Log::error('RPA deduction queue failed', ['project' => $project->project_id ?? null, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Re-renders both documents (debit-note-combined + plain original report)
     * for an already-queued deduction row and re-offloads them to the same S3
     * keys the row already points at, in place — status/payload's other
     * fields (PO/vendor_name/amount/latest_version) are left untouched.
     *
     * The reusable version of what used to be a one-off tinker script: called
     * from `rpa:regenerate-debit-notes` (app/Console/Commands/
     * RegenerateDebitNoteReports.php), and usable the same way from anywhere
     * else in the app that needs to force a re-render (e.g. after a Blade
     * template change) without re-running a full Director approval.
     *
     * Resolves the session by taking the most recently director-approved
     * session for the row's project (entity_id) — the same one a fresh
     * approval would have used. Returns false (logged, not thrown) if the
     * project/session/render can't be resolved, so a batch run can skip a bad
     * row and keep going.
     *
     * $documentNo/$invoiceDate: when set, this is the debit_note RPA bot's
     * "finalize" call (RpaFinalizeController::finalize) — it has just scraped
     * these off the D365 journal it created and needs the debit-note page
     * genuinely re-rendered with them baked in via Blade, not overlaid onto
     * the existing PDF after the fact. Only the combined doc is re-rendered
     * in that case (the plain original report is untouched — it never
     * depended on these two fields).
     */
    public function regenerateDeductionDocs(int $rowId, ?string $documentNo = null, ?string $invoiceDate = null): bool
    {
        $row = DB::connection('rpa')->table('rpa_queues')->where('id', $rowId)->where('rpa_type', 'deduction')->first();
        if (! $row) {
            Log::warning('RPA deduction regenerate: row not found', ['id' => $rowId]);

            return false;
        }

        $payload = json_decode($row->payload ?? '', true) ?: [];
        $entityId = (string) $row->entity_id;
        $po = (string) ($payload['PO'] ?? '');
        $vendorName = (string) ($payload['vendor_name'] ?? '');
        $amount = (float) ($payload['amount'] ?? 0);

        $project = DB::connection('qms')->table('packaging_projects')->where('project_id', $entityId)->first();
        if (! $project) {
            Log::warning('RPA deduction regenerate: project not found', ['id' => $rowId, 'entity' => $entityId]);

            return false;
        }

        $session = DB::connection('qms')->table('packaging_project_sessions')
            ->where('project_id', $entityId)
            ->whereNotNull('director_approval_signature')->where('director_approval_signature', '!=', '')
            ->orderByDesc('cycle_number')
            ->first();
        if (! $session) {
            Log::warning('RPA deduction regenerate: no director-approved session found', ['id' => $rowId, 'entity' => $entityId]);

            return false;
        }

        $isFinalize = $documentNo !== null;

        $originalDoc = $isFinalize ? null : $this->reportPdf->renderDataUri($entityId, (string) $session->session_id);
        $combinedDoc = $this->reportPdf->renderDeductionDataUri(
            $entityId,
            (string) $session->session_id,
            $po !== '' ? $po : (string) ($project->po_info ?? ''),
            $vendorName !== '' ? $vendorName : (string) ($project->po_vendor ?? ''),
            $amount,
            $documentNo,
            $invoiceDate,
        );

        if ($combinedDoc === null || (! $isFinalize && $originalDoc === null)) {
            Log::warning('RPA deduction regenerate: PDF render failed', [
                'id' => $rowId, 'entity' => $entityId, 'original_ok' => $originalDoc !== null, 'combined_ok' => $combinedDoc !== null,
            ]);

            return false;
        }

        $payload['signed_doc'] = $combinedDoc;
        if (! $isFinalize) {
            $payload['original_report'] = $originalDoc;
        } else {
            // Persist the D365 journal number as its own field once known —
            // it's baked into the PDF too, but Finance Admin's Debit Notes
            // list (RpaQueueReadService::rows) needs it as a plain column so
            // it doesn't have to be opened to see which journal it landed in.
            $payload['document_no'] = $documentNo;
            $payload['invoice_date'] = $invoiceDate;
        }
        $payload = $this->offloadSignedDocToS3('deduction', $entityId, $payload);
        $payload = $this->offloadOriginalReportToS3('deduction', $entityId, $payload);

        DB::connection('rpa')->table('rpa_queues')->where('id', $rowId)->update([
            'payload' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);

        Log::info('RPA deduction documents regenerated', ['id' => $rowId, 'entity' => $entityId]);

        return true;
    }

    /**
     * Re-renders the invoice job's signed_doc (the QC inspection report PDF —
     * invoice has no document of its own, see queueInvoice()) from the
     * project's current po_info/po_qty/etc. Companion to
     * regenerateDeductionDocs() for the invoice side: needed after a
     * matchesLocalOrder() self-heal or an rpa:reconcile-po --fix corrects a
     * stale PO/qty, so the PO Number/Qty Order printed in the already-queued
     * report reflect the correction, not just the payload's PO/amount fields.
     */
    public function regenerateInvoiceDoc(int $rowId): bool
    {
        $row = DB::connection('rpa')->table('rpa_queues')->where('id', $rowId)->where('rpa_type', 'invoice')->first();
        if (! $row) {
            Log::warning('RPA invoice regenerate: row not found', ['id' => $rowId]);

            return false;
        }

        $entityId = (string) $row->entity_id;

        $session = DB::connection('qms')->table('packaging_project_sessions')
            ->where('project_id', $entityId)
            ->whereNotNull('director_approval_signature')->where('director_approval_signature', '!=', '')
            ->orderByDesc('cycle_number')
            ->first();
        if (! $session) {
            Log::warning('RPA invoice regenerate: no director-approved session found', ['id' => $rowId, 'entity' => $entityId]);

            return false;
        }

        $signedDoc = $this->reportPdf->renderDataUri($entityId, (string) $session->session_id);
        if ($signedDoc === null) {
            Log::warning('RPA invoice regenerate: PDF render failed', ['id' => $rowId, 'entity' => $entityId]);

            return false;
        }

        $payload = json_decode($row->payload ?? '', true) ?: [];
        $payload['signed_doc'] = $signedDoc;
        $payload = $this->offloadSignedDocToS3('invoice', $entityId, $payload);

        DB::connection('rpa')->table('rpa_queues')->where('id', $rowId)->update([
            'payload' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);

        Log::info('RPA invoice document regenerated', ['id' => $rowId, 'entity' => $entityId]);

        return true;
    }

    /**
     * Guards invoice/deduction queuing against a packaging_project whose
     * po_info/po_qty is a stale snapshot (captured once at project creation,
     * never refreshed) — e.g. D365 reissues the CMT PO under a new number
     * with the real quantity after the project was created against a draft
     * placeholder PO. Left uncorrected, that stale PO/qty gets baked straight
     * into the invoice payload's PO + amount (= sales_price × po_qty), and
     * the RPA bot then retries forever against a PO with nothing to invoice.
     *
     * Self-heals in place — same pattern as queueJobTransRaf()'s
     * cmt_pak_job_id self-heal above: SubconOrder (synced straight from
     * D365, see CLAUDE.md) is the actual source of truth, so a drift here is
     * corrected and persisted back to packaging_projects rather than just
     * blocked-and-logged, which used to leave the Director approval
     * reporting success while the queue silently rejected it.
     */
    private function matchesLocalOrder(object $project): bool
    {
        try {
            $productionGroup = (string) ($project->production_group ?? '');
            if ($productionGroup === '') {
                return true;
            }

            $order = SubconOrder::where('production_group', $productionGroup)->first();
            if (! $order) {
                return true;
            }

            $projectId = (string) ($project->project_id ?? '');
            $updates = [];

            $poInfo = (string) ($project->po_info ?? '');
            if ($poInfo !== '' && $poInfo !== $order->order_number) {
                Log::warning('RPA queue: po_info drifted from the synced PO, self-healing', [
                    'project' => $projectId,
                    'stale_po_info' => $poInfo,
                    'order_number' => $order->order_number,
                    'production_group' => $productionGroup,
                ]);
                $updates['po_info'] = $order->order_number;
                $project->po_info = $order->order_number;
            }

            $orderQty = (float) DB::table('subcon_order_items')->where('order_id', $order->id)->sum('quantity');
            $projectQty = (float) ($project->po_qty ?? 0);
            if ($orderQty > 0 && $projectQty > 0 && abs($orderQty - $projectQty) > max(1.0, $orderQty * 0.01)) {
                Log::warning('RPA queue: po_qty drifted from the synced PO, self-healing', [
                    'project' => $projectId,
                    'stale_po_qty' => $projectQty,
                    'order_qty' => $orderQty,
                    'production_group' => $productionGroup,
                ]);
                $updates['po_qty'] = $orderQty;
                $project->po_qty = $orderQty;
            }

            if (! empty($updates) && $projectId !== '') {
                $updates['updated_at'] = now();
                DB::connection('qms')->table('packaging_projects')->where('project_id', $projectId)->update($updates);
            }
        } catch (\Throwable $e) {
            Log::warning('RPA queue: local order cross-check failed, proceeding without it', [
                'project' => $project->project_id ?? null, 'error' => $e->getMessage(),
            ]);
        }

        return true;
    }

    /**
     * The portal-computed deduction total for a project: fabric overconsumption
     * charges (packaging_project_fabric_lines.deduction) + the manual deduction
     * lines HO added on this session + the reject/lost-items penalty for this
     * session (QcReportPdfService::calculateDeductions — the same 1%-reject-limit
     * and barang-hilang charges the console used to write to
     * packaging_projects.deduction_amount before "Complete & Sync" was removed;
     * that field is no longer read anywhere, this recomputes it live instead so
     * it can't go stale) + the accessory retur shortfall claim (per-accessory
     * `claim` in QcReportPdfService::context()'s accessoryBreakdown, filtered
     * through the tiered ruling in accessoryClaimRuling() — see that method's
     * docblock; a full context() call, so this is deliberately the most
     * expensive summand here).
     */
    public function deductionTotal(string $projectId, ?string $sessionId): float
    {
        return $this->deductionBreakdown($projectId, $sessionId)['total'];
    }

    /**
     * Same computation as deductionTotal() above, but broken out by source so
     * callers that need to explain the figure (not just charge it) — e.g. the
     * Report Validation / Director list rows, which want to flag a material
     * recon shortfall distinctly from a fabric overconsumption charge — don't
     * have to re-run the expensive accessoryBreakdown context() call a second
     * time just to get the same number split apart.
     *
     * @return array{fabric:float,manual:float,rejectProduksiPenalty:float,barangHilangPenalty:float,accessoryClaimTotal:float,accessoryClaimCount:int,total:float}
     */
    public function deductionBreakdown(string $projectId, ?string $sessionId): array
    {
        $fabric = 0.0;
        $manual = 0.0;
        $rejectProduksiPenalty = 0.0;
        $barangHilangPenalty = 0.0;
        $accessoryClaimTotal = 0.0;
        $accessoryClaimCount = 0;

        try {
            $fabric = (float) DB::connection('qms')->table('packaging_project_fabric_lines')
                ->where('project_id', $projectId)->sum('deduction');
        } catch (\Throwable $e) {
            Log::warning('RPA deduction total: fabric lines read failed', ['project' => $projectId, 'error' => $e->getMessage()]);
        }

        try {
            if ($sessionId) {
                $manual = (float) DB::connection('qms')->table('packaging_session_deduction_lines')
                    ->where('session_id', $sessionId)->sum('amount');
            }
        } catch (\Throwable $e) {
            Log::warning('RPA deduction total: deduction lines read failed', ['project' => $projectId, 'error' => $e->getMessage()]);
        }

        try {
            if ($sessionId) {
                // context() computes rejectProduksiPenalty/barangHilangPenalty via
                // calculateDeductions() internally and returns it as $ctx['deductions']
                // — calling calculateDeductions() separately here (as this used to)
                // re-ran the exact same qms queries + computation a second time for
                // no reason. One context() call now supplies both figures.
                // $summaryOnly=true: this only reads accessoryBreakdown's `claim` —
                // see context()'s docblock for exactly which D365 calls that skips.
                $ctx = $this->reportPdf->context($projectId, $sessionId, summaryOnly: true);
                $penalties = $ctx['deductions'] ?? [];
                $rejectProduksiPenalty = (float) ($penalties['rejectProduksiPenalty'] ?? 0);
                $barangHilangPenalty = (float) ($penalties['barangHilangPenalty'] ?? 0);
                // Ruling now lives on QcReportPdfService::accessoryClaimRuling()
                // (called once inside context() itself) so this, the report, and
                // the debit-note template all read the exact same ruled figure —
                // see that method's docblock for why this moved.
                $ruling = $ctx['accessoryClaimRuling'] ?? ['total' => 0.0, 'count' => 0];
                $accessoryClaimTotal = (float) $ruling['total'];
                $accessoryClaimCount = (int) $ruling['count'];
            }
        } catch (\Throwable $e) {
            Log::warning('RPA deduction total: context/penalty calc failed', ['project' => $projectId, 'error' => $e->getMessage()]);
        }

        return [
            'fabric' => round($fabric, 2),
            'manual' => round($manual, 2),
            'rejectProduksiPenalty' => round($rejectProduksiPenalty, 2),
            'barangHilangPenalty' => round($barangHilangPenalty, 2),
            'accessoryClaimTotal' => round($accessoryClaimTotal, 2),
            'accessoryClaimCount' => $accessoryClaimCount,
            'total' => round($fabric + $manual + $rejectProduksiPenalty + $barangHilangPenalty + $accessoryClaimTotal, 2),
        ];
    }

    /**
     * Uploads a heavy payload to the shared `rpa_lake` S3 bucket under a stable
     * per-entity key (overwritten on every call, same lifecycle as the DB row
     * it replaces) and returns the small pointer to store in `rpa_queues.payload`
     * instead. Best-effort: if the upload fails, returns the payload unchanged
     * so the queue write still lands — offloading must never block an approval.
     *
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function offloadToS3(string $rpaType, string $entityId, array $payload): array
    {
        $key = "{$rpaType}/{$entityId}.json";

        try {
            Storage::disk('rpa_lake')->put($key, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return [
                's3_bucket' => config('filesystems.disks.rpa_lake.bucket'),
                's3_key' => 'automaton/'.$key,
                'project_id' => $entityId,
            ];
        } catch (\Throwable $e) {
            Log::warning('RPA payload S3 offload failed, storing inline', [
                'entity' => $entityId, 'type' => $rpaType, 'error' => $e->getMessage(),
            ]);

            return $payload;
        }
    }

    /**
     * Invoice/deduction variant: unlike job_trans_raf, PO/vendor_name/amount
     * are small and read directly off the DB row for listing (RpaQueueReadService,
     * FinanceAdminController) — offloading the whole payload would force an S3
     * round-trip per row just to render a table. Only `signed_doc` (the
     * fully-signed PDF as a base64 data URI, easily several MB) is heavy, so
     * only it moves to S3; everything else stays inline. Same key convention
     * as offloadToS3 ({rpaType}/{entityId}...), overwritten on every call.
     *
     * Field name is plain `s3_key` (not `signed_doc_s3_key`) and carries no
     * bucket — this must match the debit_note/invoice RPA bot's existing
     * payload contract exactly, which already checks for `s3_key` and
     * defaults to the `rpa-lake` bucket. Confirmed with the bot's owner
     * 2026-08-12 after task #145 failed against the old, unrecognized field
     * name (`signed_doc_s3_key`/`signed_doc_s3_bucket`).
     *
     * The object body is the raw decoded PDF (application/pdf bytes), not the
     * `data:application/pdf;base64,...` string — the bot downloads and
     * attaches it directly with no decoding of its own (confirmed 2026-08-12
     * after task #145 failed a second time, past the missing-key issue, with
     * "Failed to extract PDF/Image from payload signed_doc": it was reading
     * the data-URI text as if it were already binary). resolveSignedDoc()
     * re-wraps the bytes back into a data URI for this portal's own readers.
     *
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function offloadSignedDocToS3(string $rpaType, string $entityId, array $payload): array
    {
        $signedDoc = (string) ($payload['signed_doc'] ?? '');
        if ($signedDoc === '' || ! str_contains($signedDoc, 'base64,')) {
            return $payload;
        }

        $pdfBytes = base64_decode(substr($signedDoc, strpos($signedDoc, 'base64,') + 7));
        $key = "{$rpaType}/{$entityId}-signed-doc.pdf";

        try {
            Storage::disk('rpa_lake')->put($key, $pdfBytes);

            unset($payload['signed_doc']);
            $payload['s3_key'] = 'automaton/'.$key;

            return $payload;
        } catch (\Throwable $e) {
            Log::warning('RPA signed_doc S3 offload failed, storing inline', [
                'entity' => $entityId, 'type' => $rpaType, 'error' => $e->getMessage(),
            ]);

            return $payload;
        }
    }

    /**
     * Same offload as offloadSignedDocToS3, for the deduction job's second
     * document (`original_report` → `original_report_s3_key`) — the plain
     * report Subcon Admin's Debit Notes tab always shows. Distinct field
     * names from `signed_doc`/`s3_key` on purpose: those stay reserved for
     * the debit_note RPA bot's existing D365-attachment contract.
     *
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function offloadOriginalReportToS3(string $rpaType, string $entityId, array $payload): array
    {
        $doc = (string) ($payload['original_report'] ?? '');
        if ($doc === '' || ! str_contains($doc, 'base64,')) {
            return $payload;
        }

        $pdfBytes = base64_decode(substr($doc, strpos($doc, 'base64,') + 7));
        $key = "{$rpaType}/{$entityId}-original-report.pdf";

        try {
            Storage::disk('rpa_lake')->put($key, $pdfBytes);

            unset($payload['original_report']);
            $payload['original_report_s3_key'] = 'automaton/'.$key;

            return $payload;
        } catch (\Throwable $e) {
            Log::warning('RPA original_report S3 offload failed, storing inline', [
                'entity' => $entityId, 'type' => $rpaType, 'error' => $e->getMessage(),
            ]);

            return $payload;
        }
    }

    /**
     * Console-identical upsert: refresh the payload while a job of this type is
     * still in its updatable status; insert a fresh row otherwise (unless one is
     * already processing).
     *
     * `$entityType` defaults to 'packaging_project' — correct for every
     * existing caller (queueJobTransRaf/queueInvoice/queueDeduction, all qms
     * packaging-project entities). Add-Process-scoped callers (e.g.
     * queueOfficialReportInvoice/queueOfficialReportDeduction) must pass
     * 'add_process' explicitly — downstream listeners (e.g. automaton/
     * pw_service/debit_note/listener.py) filter on this column, and
     * queueAddProcessGoodsReceive() already established 'add_process' as the
     * correct value for this entity family.
     *
     * @param  array<string,mixed>  $payload
     * @param  list<string>  $activeStatuses
     */
    private function upsertJob(string $entityId, string $rpaType, string $insertStatus, array $payload, string $updatableStatus, array $activeStatuses, string $entityType = 'packaging_project'): void
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
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'rpa_type' => $rpaType,
                'status' => $insertStatus,
                'payload' => $json,
                'source' => self::SOURCE,
            ]);
        }

        Log::info('RPA job queued', ['entity' => $entityId, 'type' => $rpaType]);
    }

    /**
     * Queue one goods-receipt request for an Add Process delivery — see the
     * class docblock's `goods_receive_addprocess` entry for the routing
     * rationale (own rpa_type + listener, never the WMS scanner's tables).
     *
     * `receipt_number` is `{$poNumber}-{roman}` (I, II, III, ...) — one per
     * delivery/try against that PO, since Add Process has no vendor-supplied
     * delivery-note number the way the scanner app's operator-entered
     * receipt_number does (real examples: "1006", "SJ 1005"). The roman
     * numeral is the count of goods_receive_addprocess rows already queued
     * for this PO (any status, including prior failures — a retried "try"
     * still counts) plus one.
     *
     * `close_receipt` is always true. `$qty` must already be the caller's
     * intended delta for this one receipt (SyncSubconAddProcessToD365 sums
     * good qty across sizes for the current sync batch, not an all-time
     * running total) — this method does no aggregation of its own.
     *
     * Best-effort/never-throwing, same contract as every other method here.
     */
    public function queueAddProcessGoodsReceive(string $productionGroup, string $operation, string $poNumber, int $lineNumber, float $qty): void
    {
        try {
            $priorTries = DB::connection('rpa')->table('rpa_queues')
                ->where('rpa_type', 'goods_receive_addprocess')
                ->whereRaw("payload->>'target_po' = ?", [$poNumber])
                ->count();

            $receiptNumber = $poNumber.'-'.$this->romanNumeral($priorTries + 1);

            $payload = [
                'target_po' => $poNumber,
                'receipt_number' => $receiptNumber,
                'company' => 'mpg',
                'lines' => [[
                    'line_number' => $lineNumber,
                    'quantity' => number_format($qty, 2, '.', ''),
                    'close_receipt' => true,
                ]],
            ];

            DB::connection('rpa')->table('rpa_queues')->insert([
                'entity_type' => 'add_process',
                'entity_id' => "{$productionGroup}:{$operation}",
                'rpa_type' => 'goods_receive_addprocess',
                'status' => 'pending',
                'payload' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'source' => self::SOURCE,
            ]);

            Log::info("Queued goods_receive_addprocess for {$operation} on PRG {$productionGroup} (PO {$poNumber}, receipt {$receiptNumber}, line {$lineNumber}, qty {$qty}).");
        } catch (\Throwable $e) {
            Log::error("Failed to queue goods_receive_addprocess for {$operation} on PRG {$productionGroup}: ".$e->getMessage());
            report($e);
        }
    }

    /**
     * Standard subtractive roman-numeral conversion, for queueAddProcessGoodsReceive()'s
     * receipt_number. Add Process deliveries against one PO are expected to
     * number in the single digits, but this handles any positive integer
     * rather than hardcoding a lookup table.
     */
    private function romanNumeral(int $number): string
    {
        $map = [
            'M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400,
            'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40,
            'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1,
        ];

        $result = '';
        foreach ($map as $roman => $value) {
            while ($number >= $value) {
                $result .= $roman;
                $number -= $value;
            }
        }

        return $result;
    }

    /**
     * Invoice for one Official Report's own AddPro PO — spec §10. Same
     * rpa_type ('invoice') AND entity_type ('packaging_project', the
     * upsertJob() default) as queueInvoice() — pending_invoice/listener.py
     * hard-filters entity_type = 'packaging_project', so tagging this
     * 'add_process' (as goods_receive's queue does, which has its own
     * dedicated addprocess_listener.py) would leave it stuck at status=
     * 'pending' forever, picked up by no bot. Deliberately indistinguishable
     * from a normal invoice downstream — only the entity scheme differs (no
     * qms `$project` object here): entity_id = "{production_group}:{operation}", amount =
     * unit_price (matched PO line, resolveAddProcessLineUnitPrice()) *
     * good_qty. Best-effort/never-throwing, same contract as every other
     * method here.
     */
    public function queueOfficialReportInvoice(\App\Models\SubconOfficialReport $report, string $signedDoc): bool
    {
        try {
            $entityId = "{$report->production_group}:{$report->operation}";

            $unitPrice = app(SubconProductionService::class)->resolveAddProcessLineUnitPrice(
                (string) $report->matched_po_number,
                $report->operation
            );
            if ($unitPrice === null) {
                Log::warning("Official Report invoice for {$report->production_group}:{$report->operation} — could not resolve a unit price for PO {$report->matched_po_number}, queuing at Rp 0.");
            }
            $unitPrice = $unitPrice ?? 0.0;

            $payload = [
                'PO' => (string) $report->matched_po_number,
                'vendor_name' => (string) $report->matched_po_vendor_name,
                'signed_doc' => $signedDoc,
                'amount' => $unitPrice * (float) $report->good_qty,
            ];

            $queuedPayload = $this->offloadSignedDocToS3('invoice', $entityId, $payload);

            $this->upsertJob($entityId, 'invoice', 'pending', $queuedPayload, updatableStatus: 'pending', activeStatuses: ['pending', 'waiting', 'incomplete', 'processing']);

            return true;
        } catch (\Throwable $e) {
            Log::error('RPA official-report invoice queue failed', ['report' => $report->id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Deduction for one Official Report — only queued when
     * $report->totalDeduction() > 0, same "only when > 0" rule as
     * queueDeduction(). Same rpa_type ('deduction') AND entity_type
     * ('packaging_project') as queueDeduction() — see
     * queueOfficialReportInvoice()'s docblock for why.
     */
    public function queueOfficialReportDeduction(\App\Models\SubconOfficialReport $report, string $signedDoc): bool
    {
        try {
            $entityId = "{$report->production_group}:{$report->operation}";
            $amount = $report->totalDeduction();

            if ($amount <= 0) {
                return true;
            }

            $payload = [
                'PO' => (string) $report->matched_po_number,
                'vendor_name' => (string) $report->matched_po_vendor_name,
                'signed_doc' => $signedDoc,
                'amount' => $amount,
            ];

            $queuedPayload = $this->offloadSignedDocToS3('deduction', $entityId, $payload);

            $this->upsertJob($entityId, 'deduction', 'pending', $queuedPayload, updatableStatus: 'pending', activeStatuses: ['pending', 'waiting', 'incomplete', 'processing']);

            return true;
        } catch (\Throwable $e) {
            Log::error('RPA official-report deduction queue failed', ['report' => $report->id, 'error' => $e->getMessage()]);

            return false;
        }
    }
}
