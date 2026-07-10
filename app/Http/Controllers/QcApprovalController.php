<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\SubconOrder;
use App\Services\SubconConsumptionService;
use App\Services\SubconFabricLinePublisher;
use App\Services\SubconProductionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Handles the "Approve & Sign" / "Reject" clicks from the QC Console's
 * packaging-approval email. The console generates the email (so Laravel cannot
 * HMAC-sign the URL); instead it stores a random UUID in `approval_token` on the
 * session row and puts it in the link. We validate that token, then record the
 * decision on the row the console polls.
 *
 * Column ownership (agreed with the console side):
 *   - `factory_representative` — the inspector's MANUAL name entry. We NEVER write it.
 *   - `approval_signature`     — the digital signature stamp. WE own this.
 *   - `approval_status`        — 'approved' | 'rejected'. The console detects an
 *                                approval via approval_status='approved' + approval_signature.
 *
 * Everything targets the `qms` connection (separate QMS database).
 */
class QcApprovalController extends Controller
{
    /** The QMS table the Tauri console polls every ~8s. */
    private const TABLE = 'packaging_project_sessions';

    public function approve(Request $request, string $token)
    {
        // 1. Resolve the row by its unguessable bearer token.
        $row = $this->findByToken($token);

        if (! $row) {
            return view('qc.approval-result', [
                'state' => 'invalid',
                'message' => 'This approval link is invalid or has expired.',
            ]);
        }

        // 2. Already actioned? Idempotent — don't re-sign / don't flip a rejection.
        if (($row->approval_status ?? null) === 'approved' || ! empty($row->approval_signature)) {
            return view('qc.approval-result', [
                'state' => 'already',
                'message' => 'This inspection has already been approved.',
                'signature' => $row->approval_signature,
            ]);
        }
        if (($row->approval_status ?? null) === 'rejected') {
            return view('qc.approval-result', [
                'state' => 'rejected',
                'message' => 'This inspection has already been rejected and cannot be approved.',
            ]);
        }

        // 3. Identity comes from the row (recorded by the console at send time),
        //    NEVER from the URL. Fall back to a neutral label, not a query param.
        $signer = $row->approval_email ?: 'Factory Representative';
        $signature = 'Digitally Signed: '.$signer
            .' [UTC+07:00: '.now('Asia/Jakarta')->format('Y-m-d H:i:s').']';

        // 4. Atomic, idempotent write to `approval_signature` (NOT factory_representative).
        //    The status guard means a double click / poller race can't double-sign.
        $affected = DB::connection('qms')->table(self::TABLE)
            ->where('approval_token', $token)
            ->whereNull('approval_signature')
            ->where(function ($q) {
                $q->whereNull('approval_status')->orWhere('approval_status', '');
            })
            ->update([
                'approval_signature' => $signature,   // console contract (WE own this column)
                'approved_by' => $signer,             // structured audit
                'approved_at' => now(),               // UTC (timestamptz)
                'approval_source' => 'web_portal',
                'approval_status' => 'approved',      // the marker the console detects
            ]);

        if ($affected === 0) {
            // Lost the race — someone/something actioned it microseconds ago.
            return view('qc.approval-result', [
                'state' => 'already',
                'message' => 'This inspection has already been actioned.',
                'signature' => $signature,
            ]);
        }

        Log::info('QC packaging approval signed', [
            'token' => $token,
            'signer' => $signer,
        ]);

        // Chain: hand off to the HO approval stage. Never let an email failure
        // roll back or hide the vendor confirmation that already committed.
        $this->sendHoApprovalRequest($token, $row);

        return view('qc.approval-result', [
            'state' => 'success',
            'message' => 'Approval recorded. The QC Console will update automatically.',
            'signature' => $signature,
        ]);
    }

    /**
     * Records a rejection so the QC Console can detect it. Writes only
     * `approval_status = 'rejected'` (+ audit) — never `factory_representative`
     * (the inspector's manual name) nor `approval_signature` (the sign-off stamp).
     */
    public function reject(Request $request, string $token)
    {
        $row = $this->findByToken($token);

        if (! $row) {
            return view('qc.approval-result', [
                'state' => 'invalid',
                'message' => 'This approval link is invalid or has expired.',
            ]);
        }

        // Already approved & signed — too late to reject.
        if (($row->approval_status ?? null) === 'approved' || ! empty($row->approval_signature)) {
            return view('qc.approval-result', [
                'state' => 'already',
                'message' => 'This inspection has already been approved and cannot be rejected.',
                'signature' => $row->approval_signature,
            ]);
        }

        // Already rejected — idempotent.
        if (($row->approval_status ?? null) === 'rejected') {
            return view('qc.approval-result', [
                'state' => 'rejected',
                'message' => 'This inspection has already been rejected.',
            ]);
        }

        $signer = $row->approval_email ?: 'Factory Representative';

        // Atomic guard: only reject a row that is neither signed nor already
        // actioned, so a double click / race can't re-mark it.
        $affected = DB::connection('qms')->table(self::TABLE)
            ->where('approval_token', $token)
            ->whereNull('approval_signature')
            ->where(function ($q) {
                $q->whereNull('approval_status')->orWhere('approval_status', '');
            })
            ->update([
                'approved_by' => $signer,          // who actioned it
                'approved_at' => now(),            // when (UTC)
                'approval_source' => 'web_portal',
                'approval_status' => 'rejected',   // the marker the console reads
            ]);

        if ($affected === 0) {
            return view('qc.approval-result', [
                'state' => 'already',
                'message' => 'This inspection has already been actioned.',
            ]);
        }

        Log::info('QC packaging approval rejected', [
            'token' => $token,
            'signer' => $signer,
        ]);

        return view('qc.approval-result', [
            'state' => 'rejected',
            'message' => 'Rejection recorded. The QC Console will be notified.',
        ]);
    }

    /**
     * Fixed label written for the HO stage (approver identity is a role, not an
     * individual — per the agreed contract).
     */
    private const HO_SIGNER = 'MPG HO - MD Production';

    /**
     * Stage 2 — Head Office approval form. Reached from the emailed link that
     * fires after the vendor confirms (same `approval_token`). Prefills the
     * fabric-reconciliation values from the matching subcon order and the total
     * cutting quantity used to derive actual consumption.
     */
    public function hoApprovalForm(Request $request, string $token, SubconProductionService $production)
    {
        $row = $this->findByToken($token);

        if (! $row) {
            return view('qc.approval-result', [
                'state' => 'invalid',
                'message' => 'This approval link is invalid or has expired.',
            ]);
        }

        if (! empty($row->ho_approval_signature ?? null)) {
            $rejected = str_starts_with((string) $row->ho_approval_signature, 'Rejected:');

            return view('qc.approval-result', [
                'state' => $rejected ? 'rejected' : 'already',
                'message' => $rejected
                    ? 'This inspection has already been rejected by Head Office.'
                    : 'This inspection has already received Head Office approval.',
                'signature' => $row->ho_approval_signature,
            ]);
        }

        // HO gate is calculation+approval: re-expose the same consumption inputs
        // used at the cutting gate, prefilled from the cutting snapshot, so HO can
        // revise them before signing. Recompute + overwrite happens on submit.
        [$subcon, $totalCut, $productionGroup] = $this->subconContext($row);
        $fabricLines = $subcon ? $production->fabricLinesWithData($subcon) : [];

        // Yield / per-size production detail — best-effort (VSM may be down).
        $productionGroups = [];
        $cuttingReports = collect();
        if ($subcon) {
            try {
                $productionGroups = $production->forPo($subcon->order_number);
            } catch (\Throwable $e) {
                report($e);
            }
            $cuttingReports = \App\Models\SubconCuttingReport::where('order_id', $subcon->id)->get()->keyBy('prod_id');
        }

        return view('qc.ho-approval-form', compact('token', 'row', 'subcon', 'totalCut', 'productionGroup', 'fabricLines', 'productionGroups', 'cuttingReports'));
    }

    /**
     * Stage 2 commit. Sequence (per the integration contract):
     *   1. PUBLISH packaging_project_fabric_lines via SubconFabricLinePublisher —
     *      upsert keyed by production_group, ADOPTING the rows to this project_id.
     *      (These rows are also published earlier, at cutting approval, so the
     *      console can pull them before a project exists. This is a re-publish.)
     *   2. INSERT packaging_session_deduction_lines (session-level, 0+)
     *   3. UPDATE ho_approval_signature            (triggers PDF regen)
     * The publish runs before the deductions+signature transaction so the console
     * sees the fabric lines by the time the signature triggers PDF regen.
     *
     * Fabric consumption is editable here too (calculation+approval): HO's inputs
     * are run through the SAME SubconConsumptionService engine, which recomputes
     * cutt_plan / actual_consumption / overconsumption / deduction and OVERWRITES
     * the cutting-report snapshot before it is published.
     * The QMS objects are console-owned and may not exist yet, so every write is
     * guarded + wrapped — a missing table/column is logged, never fatal.
     */
    public function hoApprove(Request $request, string $token, SubconProductionService $production, SubconConsumptionService $consumption, SubconFabricLinePublisher $publisher)
    {
        $row = $this->findByToken($token);

        if (! $row) {
            return view('qc.approval-result', [
                'state' => 'invalid',
                'message' => 'This approval link is invalid or has expired.',
            ]);
        }

        if (! empty($row->ho_approval_signature ?? null)) {
            $rejected = str_starts_with((string) $row->ho_approval_signature, 'Rejected:');

            return view('qc.approval-result', [
                'state' => $rejected ? 'rejected' : 'already',
                'message' => $rejected
                    ? 'This inspection has already been rejected by Head Office and can no longer be approved.'
                    : 'This inspection has already received Head Office approval.',
                'signature' => $row->ho_approval_signature,
            ]);
        }

        $data = $request->validate([
            'deductions' => 'nullable|array',
            'deductions.*.description' => 'nullable|string|max:255',
            'deductions.*.amount' => 'nullable|numeric|min:0',
            // HO gate re-exposes the cutting consumption inputs (calculation+approval).
            'fabrics' => 'nullable|array',
            'fabrics.*.label' => 'required_with:fabrics|string|max:500',
            'fabrics.*.short_roll' => 'nullable|numeric|min:0',
            'fabrics.*.sisa_kain' => 'nullable|numeric|min:0',
            'fabrics.*.kepala_kain' => 'nullable|numeric|min:0',
            'fabrics.*.retur_kain' => 'nullable|numeric|min:0',
            'fabrics.*.fabric_sent' => 'nullable|numeric|min:0',
            'fabrics.*.consumption_plan' => 'nullable|numeric|min:0',
            'fabrics.*.fabric_price' => 'nullable|numeric|min:0',
        ]);

        [$subcon] = $this->subconContext($row);

        // HO gate = calculation+approval: recompute + OVERWRITE the cutting snapshot
        // from HO's (possibly revised) inputs using the same engine as the cutting
        // gate. Runs on the local DB (its own transaction) before the QMS push, so
        // the fabric lines pushed below reflect HO's final numbers. Same formulas —
        // see SubconConsumptionService.
        if ($subcon && ! empty($data['fabrics'])) {
            DB::transaction(function () use ($consumption, $subcon, $data) {
                $consumption->persist($subcon, $data['fabrics']);
            });
        }

        // Fabric lines: publish the just-recomputed snapshot to QMS via the shared
        // publisher (upsert keyed by production_group), ADOPTING the rows to this
        // project_id. Runs BEFORE the signature so the console sees the lines when
        // the signature triggers PDF regen. Best-effort + guarded — same rows the
        // console can also pull earlier (staged at cutting approval with no project).
        if ($subcon) {
            $publisher->publish($subcon, (string) $row->project_id);
            app(\App\Services\SubconRemarksPublisher::class)->publish($subcon);
        }
        $fabricLines = $subcon ? $production->fabricLinesWithData($subcon) : [];

        $signature = 'Digitally Signed: '.self::HO_SIGNER
            .' [UTC+07:00: '.now('Asia/Jakarta')->format('Y-m-d H:i:s').']';

        try {
            DB::connection('qms')->transaction(function () use ($row, $token, $data, $signature) {
                // Deduction lines (session-level, optional, 0+).
                if (Schema::connection('qms')->hasTable('packaging_session_deduction_lines')) {
                    foreach (($data['deductions'] ?? []) as $d) {
                        $desc = trim((string) ($d['description'] ?? ''));
                        $amt = $d['amount'] ?? null;
                        if ($desc === '' && ($amt === null || $amt === '')) {
                            continue; // skip empty rows
                        }
                        DB::connection('qms')->table('packaging_session_deduction_lines')->insert([
                            'session_id' => $row->session_id,
                            'description' => $desc,
                            'amount' => is_numeric($amt) ? (float) $amt : 0,
                            'created_by' => self::HO_SIGNER,
                        ]);
                    }
                } else {
                    Log::warning('QC HO: packaging_session_deduction_lines missing — deductions skipped', ['token' => $token]);
                }

                // HO signature — last, and idempotent (guards a double submit).
                if (Schema::connection('qms')->hasColumn(self::TABLE, 'ho_approval_signature')) {
                    DB::connection('qms')->table(self::TABLE)
                        ->where('approval_token', $token)
                        ->where(function ($q) {
                            $q->whereNull('ho_approval_signature')->orWhere('ho_approval_signature', '');
                        })
                        ->update(['ho_approval_signature' => $signature]);
                } else {
                    Log::warning('QC HO: ho_approval_signature column missing — signature not written', ['token' => $token]);
                }
            });
        } catch (\Throwable $e) {
            Log::error('QC HO approval write failed', ['token' => $token, 'error' => $e->getMessage()]);

            return view('qc.approval-result', [
                'state' => 'invalid',
                'message' => 'Could not record the Head Office approval. Please try again, or contact support if this persists.',
            ]);
        }

        Log::info('QC HO approval signed', [
            'token' => $token,
            'project_id' => $row->project_id,
            'fabric_lines' => count($fabricLines),
            'deductions' => count($data['deductions'] ?? []),
        ]);

        \App\Models\SubconApprovalLog::record([
            'order_id' => $subcon->id ?? null,
            'order_number' => $subcon->order_number ?? ($row->project_id ?? null),
            'vendor_name' => $subcon?->vendor?->name,
            'gate' => 'final',
            'decision' => 'approved',
            'actor' => self::HO_SIGNER,
            'source' => 'email',
            'note' => 'Head Office approval recorded ('.count($fabricLines).' fabric line(s), '.count($data['deductions'] ?? []).' deduction(s)).',
        ]);

        return view('qc.approval-result', [
            'state' => 'success',
            'message' => 'Head Office approval recorded. The packaging PDF will regenerate automatically.',
            'signature' => $signature,
        ]);
    }

    /**
     * HO-stage rejection. Per the QC Console contract a rejection is recorded by
     * writing the SAME `ho_approval_signature` column with a "Rejected: …" prefix
     * (instead of "Digitally Signed: …"); the console branches on that prefix.
     * Idempotent, column-guarded, and never fatal — mirrors the stage-1 reject.
     */
    public function hoDecline(Request $request, string $token)
    {
        $row = $this->findByToken($token);

        if (! $row) {
            return view('qc.approval-result', [
                'state' => 'invalid',
                'message' => 'This approval link is invalid or has expired.',
            ]);
        }

        // Already HO-actioned (approved or rejected) — idempotent.
        if (! empty($row->ho_approval_signature ?? null)) {
            $rejected = str_starts_with((string) $row->ho_approval_signature, 'Rejected:');

            return view('qc.approval-result', [
                'state' => $rejected ? 'rejected' : 'already',
                'message' => $rejected
                    ? 'This inspection has already been rejected by Head Office.'
                    : 'This inspection has already received Head Office approval and can no longer be rejected.',
                'signature' => $row->ho_approval_signature,
            ]);
        }

        if (! Schema::connection('qms')->hasColumn(self::TABLE, 'ho_approval_signature')) {
            Log::warning('QC HO reject: ho_approval_signature column missing — rejection not written', ['token' => $token]);

            return view('qc.approval-result', [
                'state' => 'invalid',
                'message' => 'Could not record the rejection right now. Please contact support.',
            ]);
        }

        $signature = 'Rejected: '.self::HO_SIGNER
            .' [UTC+07:00: '.now('Asia/Jakarta')->format('Y-m-d H:i:s').']';

        try {
            // Atomic guard: only write when no HO signature exists yet, so an
            // approve/reject race (or double click) can't overwrite the winner.
            $affected = DB::connection('qms')->table(self::TABLE)
                ->where('approval_token', $token)
                ->where(function ($q) {
                    $q->whereNull('ho_approval_signature')->orWhere('ho_approval_signature', '');
                })
                ->update(['ho_approval_signature' => $signature]);
        } catch (\Throwable $e) {
            Log::error('QC HO reject write failed', ['token' => $token, 'error' => $e->getMessage()]);

            return view('qc.approval-result', [
                'state' => 'invalid',
                'message' => 'Could not record the rejection. Please try again, or contact support if this persists.',
            ]);
        }

        if ($affected === 0) {
            return view('qc.approval-result', [
                'state' => 'already',
                'message' => 'This inspection has already been actioned by Head Office.',
            ]);
        }

        Log::info('QC HO approval rejected', ['token' => $token, 'project_id' => $row->project_id ?? null]);

        [$subcon] = $this->subconContext($row);
        \App\Models\SubconApprovalLog::record([
            'order_id' => $subcon->id ?? null,
            'order_number' => $subcon->order_number ?? ($row->project_id ?? null),
            'vendor_name' => $subcon?->vendor?->name,
            'gate' => 'final',
            'decision' => 'declined',
            'actor' => self::HO_SIGNER,
            'source' => 'email',
            'note' => 'Head Office rejection recorded.',
        ]);

        return view('qc.approval-result', [
            'state' => 'rejected',
            'message' => 'Head Office rejection recorded. The QC Console will be notified.',
            'signature' => $signature,
        ]);
    }

    /**
     * Look up a session by its bearer token. `approval_token` is a uuid column,
     * so a malformed token (bot / truncated link) would otherwise raise a 22P02
     * and 500 — validate the shape first and return null (→ friendly invalid view).
     */
    private function findByToken(string $token): ?object
    {
        if (! Str::isUuid($token)) {
            return null;
        }

        return DB::connection('qms')->table(self::TABLE)->where('approval_token', $token)->first();
    }

    /**
     * Resolve the subcon order behind a QMS session (for prefill + the cutting
     * total): project_id → packaging_projects.production_group → subcon_orders.
     *
     * @return array{0: ?object, 1: int, 2: ?string} [subcon order row, total cutting qty, production_group]
     */
    private function subconContext(object $row): array
    {
        $productionGroup = null;
        if (Schema::connection('qms')->hasTable('packaging_projects')) {
            $project = DB::connection('qms')->table('packaging_projects')
                ->where('project_id', $row->project_id)->first();
            $productionGroup = $project->production_group ?? null;
        }

        // Must be an Eloquent SubconOrder (not a stdClass): the consumption engine,
        // publisher, and fabricLinesWithData() all type-hint the model.
        $subcon = $productionGroup
            ? SubconOrder::where('production_group', $productionGroup)->first()
            : null;

        // Live production-group total (VSM-first, local fallback) so it can't drift
        // with stale/edited local cutting rows — matches the consumption engine.
        $totalCut = $subcon
            ? app(SubconProductionService::class)->totalCutForOrder($subcon)
            : 0;

        return [$subcon, $totalCut, $productionGroup];
    }

    /**
     * Chain step: email the HO approval link (same token) to our approver list
     * after the vendor confirms. Best-effort — failures are logged, never thrown.
     */
    private function sendHoApprovalRequest(string $token, object $row): void
    {
        try {
            // "Copy the list that's already there": fall back to the subcon
            // cutting-approver list (the one that's actually populated) when the
            // dedicated qc_ho_approver_email is unset OR saved blank. (getValue
            // only returns its default when the row is absent, so an empty saved
            // value must be handled explicitly here.)
            $to = Setting::getValue('qc_ho_approver_email');
            if (trim((string) $to) === '') {
                $to = Setting::getValue('subcon_cutting_approver_email');
            }
            $recipients = collect(preg_split('/[,;]+/', (string) $to))
                ->map(fn ($e) => trim($e))->filter()->values()->all();

            if (empty($recipients)) {
                Log::warning('QC HO approval email skipped: no recipient configured', ['token' => $token]);

                return;
            }

            [$subcon, , $productionGroup] = $this->subconContext($row);

            // Sender + subject mirror the subcon cutting/gramasi approval email
            // (SubconApprovalRequestMailable) so both approval emails are uniform:
            // same From name, and subject "Approval needed: <label> — <Style — PO — group>".
            $ref = collect([
                trim((string) ($subcon->title ?? '')),
                trim((string) ($subcon->order_number ?? '')),
                trim((string) ($productionGroup ?? '')),
            ])->filter()->implode(' — ');
            $subject = 'Approval needed: Final Approval'.($ref !== '' ? ' — '.$ref : '');

            // Queued (not inline) so the approval response isn't blocked. The
            // console writes `verified_doc` at Verify→Send, so it's already in the
            // DB by now; the job reads it and attaches it (same PDF as stage 1).
            \App\Jobs\SendFinalApprovalEmail::dispatch([
                'token' => $token,
                'recipients' => $recipients,
                'subject' => $subject,
                'sessionId' => $row->session_id ?? null,
                'projectId' => $row->project_id ?? null,
                'productionGroup' => $productionGroup,
                'orderNumber' => $subcon->order_number ?? null,
                'remarks' => $subcon->remarks ?? null,
            ]);

            Log::info('QC HO approval email queued', ['token' => $token, 'to' => $recipients]);
        } catch (\Throwable $e) {
            Log::error('QC HO approval email failed', ['token' => $token, 'error' => $e->getMessage()]);
        }
    }
}
