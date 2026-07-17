<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\SubconOrder;
use App\Services\QcReportPdfService;
use App\Services\RpaQueueService;
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

        // Standard PDF function: refresh verified_doc server-side so the chained
        // Final email attaches a document carrying this signature even when the
        // console app is closed. Best-effort — the console's own copy remains.
        $this->refreshVerifiedDoc($row);

        // Chain: hand off to the HO approval stage. Never let an email failure
        // roll back or hide the vendor confirmation that already committed.
        $this->sendHoApprovalRequest($token, $row);

        // Tell the QC inspector their inspection moved forward.
        $this->sendStageProgressNotification(
            $row,
            'Factory Representative',
            $signer,
            'It is now awaiting MD Production approval.',
            [$row->inspector_email ?? ''],
        );

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

        // Workflow state changed → push a re-render into verified_doc so every
        // consumer (console preview, portal tab, emails) sees the same document.
        $this->refreshVerifiedDoc($row);

        // Back to QC: tell the inspector (device-registered email) so they can
        // revise and resend without waiting to notice it in the console.
        $this->notifyInspectorOfRejection($row, 'Factory Representative', $signer);

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
     * Resolve who is actually taking the action, in precedence order:
     *   1. the logged-in portal account (Director tab, or any admin clicking
     *      while authenticated),
     *   2. the `as` recipient marker carried by the per-recipient email links
     *      (each approver gets their own link, so the click identifies them),
     *   3. the fixed role label (legacy links / stripped query strings).
     * Returns 'Name <email>' or the fallback. Only the name part of the
     * signature is enriched — the 'Digitally Signed:'/'Rejected:' prefix and
     * '[UTC+07:00: …]' suffix contract the console parses is unchanged.
     */
    private function actorLabel(Request $request, string $fallback): string
    {
        $user = $request->user();
        if ($user && trim((string) $user->email) !== '') {
            return trim((string) $user->name).' <'.strtolower(trim((string) $user->email)).'>';
        }

        $email = strtolower(trim((string) $request->input('as', '')));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $fallback;
        }

        $name = null;
        try {
            $name = \App\Models\User::where('email', $email)->value('name');
        } catch (\Throwable $e) {
            // users table unavailable — fall back to the address-derived name
        }
        $name = $name ?: Str::title(str_replace(['.', '_', '-'], ' ', Str::before($email, '@')));

        return $name.' <'.$email.'>';
    }

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

        $hoSig = trim((string) ($row->ho_approval_signature ?? ''));
        $validateMode = false;
        if ($hoSig !== '') {
            $rejected = str_starts_with($hoSig, 'Rejected:');
            $sent = trim((string) ($row->ho_validation_signature ?? '')) !== ''
                || trim((string) ($row->director_approval_signature ?? '')) !== '';

            if ($rejected || $sent) {
                return view('qc.approval-result', [
                    'state' => $rejected ? 'rejected' : 'already',
                    'message' => $rejected
                        ? 'This inspection has already been rejected by Head Office.'
                        : 'This inspection has already received Head Office approval and been sent to the Director.',
                    'signature' => $row->ho_approval_signature,
                ]);
            }

            // Approved but not yet sent — step 2 of the MD gate: the same page
            // renders read-only with the RAF run status and a single
            // "Validate & Send Approval" button (hoSendApproval).
            $validateMode = true;
        }

        // HO gate is calculation+approval: re-expose the same consumption inputs
        // used at the cutting gate, prefilled from the cutting snapshot, so HO can
        // revise them before signing. Recompute + overwrite happens on submit.
        [$subcon, $totalCut, $productionGroup] = $this->subconContext($row);
        $fabricLines = $subcon ? $production->fabricLinesWithData($subcon) : [];

        // QC-measured Retur Kain from the console's Production Status card
        // overrides the vendor-entered prefill on the main (first) fabric line —
        // but only when a positive value was entered: NULL means "not measured"
        // and 0 is treated the same, keeping the stored snapshot value. HO can
        // still revise it before signing; their submitted value is what persists.
        $qcReturKain = (float) ($row->retur_kain ?? 0);
        if (! $validateMode && $qcReturKain > 0 && ! empty($fabricLines)) {
            $fabricLines[0]['retur_kain'] = $qcReturKain;
            $fabricLines[0]['retur_kain_source'] = 'qc_console';
        }

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

        // Step-2 extras: live RAF run status (best-effort, remote RPA DB).
        $rafStatus = $validateMode ? $this->rafJobStatus((string) $row->project_id) : null;

        return view('qc.ho-approval-form', compact('token', 'row', 'subcon', 'totalCut', 'productionGroup', 'fabricLines', 'productionGroups', 'cuttingReports', 'validateMode', 'rafStatus'));
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

        $hoActor = $this->actorLabel($request, self::HO_SIGNER);
        $signature = 'Digitally Signed: '.$hoActor
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
                    $update = ['ho_approval_signature' => $signature];
                    // A rerun after a Director rejection: wipe the stale
                    // 'Rejected:' director stamp so the Director link is live again.
                    if (Schema::connection('qms')->hasColumn(self::TABLE, 'director_approval_signature')) {
                        $update['director_approval_signature'] = null;
                    }
                    // Every (re-)approval starts a fresh validate-and-send cycle.
                    if (Schema::connection('qms')->hasColumn(self::TABLE, 'ho_validation_signature')) {
                        $update['ho_validation_signature'] = null;
                    }
                    DB::connection('qms')->table(self::TABLE)
                        ->where('approval_token', $token)
                        ->where(function ($q) {
                            $q->whereNull('ho_approval_signature')->orWhere('ho_approval_signature', '');
                        })
                        ->update($update);
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
            'actor' => $hoActor,
            'source' => $request->user() ? 'portal' : 'email',
            'note' => 'Head Office approval recorded ('.count($fabricLines).' fabric line(s), '.count($data['deductions'] ?? []).' deduction(s)).',
        ]);

        // MD Production approved → refresh the signed PDF (now carrying both
        // signatures) and queue the RAF RPA job. The Director is NOT notified
        // here any more: MD Production reviews the numbers once RAF has run and
        // presses "Validate & Send Approval" (hoSendApproval) to open stage 3.
        // All best-effort: the approval above has already committed.
        $this->refreshVerifiedDoc($row);
        app(RpaQueueService::class)->queueJobTransRaf((string) $row->project_id);

        // Email the Validate & Send link to the MD Production list so step 2
        // is reachable from the inbox too (not just the redirect below and the
        // Report Validation tab).
        $this->sendValidateSendRequest($token, $row, $hoActor);

        // Back to the same form, which now renders the validate-and-send step.
        return redirect()->route('qc.ho-approve', array_filter([
            'token' => $token,
            'as' => $request->input('as'),
        ]))->with('qc_ho_approved', true);
    }

    /**
     * Stage 2b — "Validate & Send Approval". Review & Approve (hoApprove) signs
     * and queues the RAF production run but no longer notifies the Director;
     * this second, explicit send is MD Production vouching for the numbers
     * after the RAF run. Idempotent via `ho_validation_signature` (same
     * 'Digitally Signed:' contract as the other signature columns).
     */
    public function hoSendApproval(Request $request, string $token)
    {
        $row = $this->findByToken($token);

        if (! $row) {
            return view('qc.approval-result', [
                'state' => 'invalid',
                'message' => 'This approval link is invalid or has expired.',
            ]);
        }

        $hoSig = (string) ($row->ho_approval_signature ?? '');
        if (str_starts_with($hoSig, 'Rejected:')) {
            return view('qc.approval-result', [
                'state' => 'rejected',
                'message' => 'This inspection has already been rejected by Head Office and can no longer be sent.',
                'signature' => $hoSig,
            ]);
        }
        if (! str_contains($hoSig, 'Digitally Signed:')) {
            return view('qc.approval-result', [
                'state' => 'invalid',
                'message' => 'This inspection has not been approved by MD Production yet — Review & Approve it first, then Validate & Send.',
            ]);
        }
        if (trim((string) ($row->director_approval_signature ?? '')) !== '') {
            return view('qc.approval-result', [
                'state' => 'already',
                'message' => 'This inspection has already been actioned by the Director.',
                'signature' => $row->director_approval_signature,
            ]);
        }

        $actor = $this->actorLabel($request, self::HO_SIGNER);
        $signature = 'Digitally Signed: '.$actor
            .' [UTC+07:00: '.now('Asia/Jakarta')->format('Y-m-d H:i:s').']';

        if (Schema::connection('qms')->hasColumn(self::TABLE, 'ho_validation_signature')) {
            try {
                $affected = DB::connection('qms')->table(self::TABLE)
                    ->where('approval_token', $token)
                    ->where(function ($q) {
                        $q->whereNull('ho_validation_signature')->orWhere('ho_validation_signature', '');
                    })
                    ->update(['ho_validation_signature' => $signature]);
            } catch (\Throwable $e) {
                Log::error('QC HO send write failed', ['token' => $token, 'error' => $e->getMessage()]);

                return view('qc.approval-result', [
                    'state' => 'invalid',
                    'message' => 'Could not record the send. Please try again, or contact support if this persists.',
                ]);
            }

            if ($affected === 0) {
                return view('qc.approval-result', [
                    'state' => 'already',
                    'message' => 'This approval has already been sent to the Director.',
                    'signature' => $row->ho_validation_signature,
                ]);
            }
        } else {
            // Column missing (console DDL race) — send anyway, but without the
            // idempotency marker a double click could re-email the Director.
            Log::warning('QC HO send: ho_validation_signature column missing — send not idempotent', ['token' => $token]);
        }

        Log::info('QC HO validation signed — sending to Director', ['token' => $token, 'project_id' => $row->project_id]);

        [$subcon, , $productionGroup] = $this->subconContext($row);
        \App\Models\SubconApprovalLog::record([
            'order_id' => $subcon->id ?? null,
            'order_number' => $subcon->order_number ?? ($row->project_id ?? null),
            'vendor_name' => $subcon?->vendor?->name,
            'gate' => 'final',
            'decision' => 'approved',
            'actor' => $actor,
            'source' => $request->user() ? 'portal' : 'email',
            'note' => 'Numbers validated — approval sent to the Director for authorization.',
        ]);

        // Re-render the document at SEND time so the Director's attachment
        // carries the numbers as they stand now (post-RAF), not as of the
        // approve click. Best-effort — the emails fall back to the stored copy.
        $this->refreshVerifiedDoc($row);

        $this->sendDirectorApprovalRequest($token, $row);

        // Tell the earlier participants (QC inspector + factory representative)
        // that MD Production approved and the Director stage is underway.
        $this->sendStageProgressNotification(
            $row,
            'MD Production',
            $actor,
            'It is now awaiting Director authorization.',
            [$row->inspector_email ?? '', $row->approval_email ?? ''],
        );

        return view('qc.approval-result', [
            'state' => 'success',
            'message' => 'Approval sent — the Director has been notified for final authorization.',
            'signature' => $signature,
        ]);
    }

    /**
     * Confirmation interstitial for the HO rejection link (GET). Renders a page
     * with a button that POSTs to hoDecline — it performs NO write itself.
     *
     * Why this exists: the HO approval email lands in corporate mailboxes whose
     * security scanners (Microsoft Safe Links / antivirus) prefetch every link
     * via GET to check for malware. When the decline link was a mutating GET,
     * that prefetch auto-rejected the inspection within ~1s of the email being
     * sent — no human involved. Moving the write behind a POST that only a real
     * click submits neutralises the prefetch.
     */
    public function hoDeclineForm(Request $request, string $token)
    {
        $row = $this->findByToken($token);

        if (! $row) {
            return view('qc.approval-result', [
                'state' => 'invalid',
                'message' => 'This approval link is invalid or has expired.',
            ]);
        }

        // Already HO-actioned — short-circuit to the same result views hoDecline uses.
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

        [$subcon, , $productionGroup] = $this->subconContext($row);

        return view('qc.ho-decline-confirm', compact('token', 'row', 'subcon', 'productionGroup'));
    }

    /**
     * HO-stage rejection. Per the QC Console contract a rejection is recorded by
     * writing the SAME `ho_approval_signature` column with a "Rejected: …" prefix
     * (instead of "Digitally Signed: …"); the console branches on that prefix.
     * Idempotent, column-guarded, and never fatal — mirrors the stage-1 reject.
     * Reached only via POST (from hoDeclineForm) so email link scanners that
     * prefetch the GET link can't trigger a rejection.
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

        $hoActor = $this->actorLabel($request, self::HO_SIGNER);
        $signature = 'Rejected: '.$hoActor
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

        // Workflow state changed → re-render the shared document.
        $this->refreshVerifiedDoc($row);

        [$subcon] = $this->subconContext($row);
        \App\Models\SubconApprovalLog::record([
            'order_id' => $subcon->id ?? null,
            'order_number' => $subcon->order_number ?? ($row->project_id ?? null),
            'vendor_name' => $subcon?->vendor?->name,
            'gate' => 'final',
            'decision' => 'declined',
            'actor' => $hoActor,
            'source' => $request->user() ? 'portal' : 'email',
            'note' => 'Head Office rejection recorded.',
        ]);

        // MD Production rejected → back to QC (full restart): notify the inspector.
        $this->notifyInspectorOfRejection($row, 'MD Production', self::HO_SIGNER);

        return view('qc.approval-result', [
            'state' => 'rejected',
            'message' => 'Head Office rejection recorded. The QC Console will be notified.',
            'signature' => $signature,
        ]);
    }

    /**
     * Fixed label written for the Director stage (a role, not an individual —
     * same convention as HO_SIGNER).
     */
    private const DIRECTOR_SIGNER = 'MPG Director';

    /**
     * Stage 3 — Director authorization form. Read-only: the Director reviews
     * the full inspection summary (same data the PDF shows) with both prior
     * signatures, then Approves or Rejects — no editing.
     */
    public function directorApprovalForm(Request $request, string $token, QcReportPdfService $reportPdf)
    {
        $row = $this->findByToken($token);

        if (! $row) {
            return view('qc.approval-result', [
                'state' => 'invalid',
                'message' => 'This approval link is invalid or has expired.',
            ]);
        }

        if ($guard = $this->directorStageGuard($row)) {
            return $guard;
        }

        [$subcon, , $productionGroup] = $this->subconContext($row);
        $report = $reportPdf->context((string) $row->project_id, (string) $row->session_id);

        return view('qc.director-approval-form', compact('token', 'row', 'subcon', 'productionGroup', 'report'));
    }

    /**
     * Serve the signed inspection PDF for a session, token-gated like every
     * other link in the chain. DYNAMIC: always rendered fresh from the current
     * QMS workflow state (whatever signatures exist right now), and the render
     * is pushed back into packaging_projects.verified_doc so the console
     * preview, the emails, and this endpoint all show the same document. The
     * stored copy is only a fallback for when the renderer fails.
     */
    public function document(Request $request, string $token, QcReportPdfService $reportPdf)
    {
        $row = $this->findByToken($token);

        if (! $row) {
            return view('qc.approval-result', [
                'state' => 'invalid',
                'message' => 'This document link is invalid or has expired.',
            ]);
        }

        $projectId = (string) $row->project_id;

        $fresh = $reportPdf->renderDataUri($projectId, (string) $row->session_id);
        $bytes = $this->dataUriToPdfBytes((string) $fresh);

        if ($bytes !== null) {
            $reportPdf->writeVerifiedDoc($projectId, (string) $fresh);
        } else {
            // Renderer failed — serve the last stored document instead.
            try {
                $stored = DB::connection('qms')->table('packaging_projects')
                    ->where('project_id', $projectId)->value('verified_doc');
                $bytes = $this->dataUriToPdfBytes((string) $stored);
            } catch (\Throwable $e) {
                Log::warning('QC document: verified_doc fetch failed', ['project' => $projectId, 'error' => $e->getMessage()]);
            }
        }

        if ($bytes === null) {
            return view('qc.approval-result', [
                'state' => 'invalid',
                'message' => 'No inspection document is available for this session yet.',
            ]);
        }

        [$subcon] = $this->subconContext($row);
        $name = str_replace(['/', '\\'], '-', (string) ($subcon->order_number ?? $projectId));

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="inspection-report-'.$name.'.pdf"',
        ]);
    }

    /**
     * Generate the inspection PDF from QMS data and serve it inline as a PDF file,
     * without token-gating (for QC console print fallback).
     *
     * GET /qc/print/{projectId}/{sessionId}
     */
    public function printDraft(Request $request, string $projectId, string $sessionId, QcReportPdfService $reportPdf)
    {
        $fresh = $reportPdf->renderDataUri($projectId, $sessionId);

        if (! $fresh) {
            abort(404, 'Could not render inspection report PDF.');
        }

        $bytes = $this->dataUriToPdfBytes((string) $fresh);

        if ($bytes === null) {
            abort(404, 'Inspection document bytes are unavailable.');
        }

        $name = str_replace(['/', '\\'], '-', $projectId.'-'.$sessionId);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="inspection-report-'.$name.'.pdf"',
        ]);
    }

    /** Decode a `data:application/pdf;base64,…` URI to raw PDF bytes, or null. */
    private function dataUriToPdfBytes(string $dataUri): ?string
    {
        if (! str_starts_with($dataUri, 'data:') || ! str_contains($dataUri, 'application/pdf')) {
            return null;
        }
        $comma = strpos($dataUri, ',');
        if ($comma === false) {
            return null;
        }
        $bytes = base64_decode(substr($dataUri, $comma + 1), true);

        return ($bytes === false || $bytes === '') ? null : $bytes;
    }

    /**
     * Stage 3 commit — the end of the chain. Sequence:
     *   1. Write director_approval_signature (atomic, idempotent).
     *   2. Regenerate the fully-signed PDF → verified_doc (all 3 signatures).
     *   3. Queue the Invoice + Deduction RPA jobs (deduction only when the
     *      portal-computed total > 0), signed_doc = the final PDF.
     *   4. Mark packaging_projects.status = 'completed' (replaces the console's
     *      manual "Complete & Sync").
     *   5. Send the completion notification with the signed PDF to QC inspector,
     *      Factory Rep, MD Production, and Director.
     * Steps 2–5 are best-effort — the signature (step 1) is the source of truth.
     */
    public function directorApprove(Request $request, string $token, QcReportPdfService $reportPdf, RpaQueueService $rpa)
    {
        $row = $this->findByToken($token);

        if (! $row) {
            return view('qc.approval-result', [
                'state' => 'invalid',
                'message' => 'This approval link is invalid or has expired.',
            ]);
        }

        if ($guard = $this->directorStageGuard($row)) {
            return $guard;
        }

        $directorActor = $this->actorLabel($request, self::DIRECTOR_SIGNER);
        $signature = 'Digitally Signed: '.$directorActor
            .' [UTC+07:00: '.now('Asia/Jakarta')->format('Y-m-d H:i:s').']';

        try {
            $affected = DB::connection('qms')->table(self::TABLE)
                ->where('approval_token', $token)
                ->where(function ($q) {
                    $q->whereNull('director_approval_signature')->orWhere('director_approval_signature', '');
                })
                ->update(['director_approval_signature' => $signature]);
        } catch (\Throwable $e) {
            Log::error('QC director approval write failed', ['token' => $token, 'error' => $e->getMessage()]);

            return view('qc.approval-result', [
                'state' => 'invalid',
                'message' => 'Could not record the Director authorization. Please try again, or contact support if this persists.',
            ]);
        }

        if ($affected === 0) {
            return view('qc.approval-result', [
                'state' => 'already',
                'message' => 'This inspection has already been actioned by the Director.',
            ]);
        }

        Log::info('QC director authorization signed', ['token' => $token, 'project_id' => $row->project_id]);

        // Final signed document — regenerated with all three signatures.
        $projectId = (string) $row->project_id;
        $signedDoc = $reportPdf->renderDataUri($projectId, (string) $row->session_id);
        if ($signedDoc !== null) {
            $reportPdf->writeVerifiedDoc($projectId, $signedDoc);
        } else {
            // Renderer failed — fall back to the stored document for the RPA payloads.
            try {
                $signedDoc = (string) DB::connection('qms')->table('packaging_projects')
                    ->where('project_id', $projectId)->value('verified_doc');
            } catch (\Throwable $e) {
                $signedDoc = '';
            }
        }

        // Invoice + Deduction RPA, then mark complete.
        $project = null;
        try {
            $project = DB::connection('qms')->table('packaging_projects')->where('project_id', $projectId)->first();
        } catch (\Throwable $e) {
            Log::error('QC director: project fetch failed', ['project' => $projectId, 'error' => $e->getMessage()]);
        }

        $deductionTotal = 0.0;
        if ($project) {
            $latestVersion = $this->latestSessionVersion($projectId);
            $deductionTotal = $rpa->deductionTotal($projectId, (string) $row->session_id);
            $rpa->queueInvoice($project, $latestVersion, (string) $signedDoc);
            $rpa->queueDeduction($project, $latestVersion, (string) $signedDoc, $deductionTotal);

            try {
                DB::connection('qms')->table('packaging_projects')
                    ->where('project_id', $projectId)
                    ->update([
                        'status' => 'completed',
                        'has_deduction' => $deductionTotal > 0,
                        'deduction_amount' => $deductionTotal,
                        'updated_at' => now(),
                    ]);
            } catch (\Throwable $e) {
                Log::error('QC director: complete transition failed', ['project' => $projectId, 'error' => $e->getMessage()]);
            }
        }

        [$subcon, , $productionGroup] = $this->subconContext($row);
        \App\Models\SubconApprovalLog::record([
            'order_id' => $subcon->id ?? null,
            'order_number' => $subcon->order_number ?? ($row->project_id ?? null),
            'vendor_name' => $subcon?->vendor?->name,
            'gate' => 'director',
            'decision' => 'approved',
            'actor' => $directorActor,
            'source' => $request->user() ? 'portal' : 'email',
            'note' => 'Director authorization recorded; Invoice'.($deductionTotal > 0 ? ' + Deduction (Rp '.number_format($deductionTotal, 0, ',', '.').')' : '').' RPA queued; project completed.',
        ]);

        $this->sendCompletionNotification($row, $subcon, $productionGroup);

        return view('qc.approval-result', [
            'state' => 'success',
            'message' => 'Director authorization recorded. The project is now complete and all parties have been notified.',
            'signature' => $signature,
        ]);
    }

    /**
     * Confirmation interstitial for the Director rejection link (GET, no write)
     * — same Safe-Links-prefetch protection as hoDeclineForm, plus an optional
     * reason field that is relayed to MD Production.
     */
    public function directorDeclineForm(Request $request, string $token)
    {
        $row = $this->findByToken($token);

        if (! $row) {
            return view('qc.approval-result', [
                'state' => 'invalid',
                'message' => 'This approval link is invalid or has expired.',
            ]);
        }

        if ($guard = $this->directorStageGuard($row)) {
            return $guard;
        }

        [$subcon, , $productionGroup] = $this->subconContext($row);

        return view('qc.director-decline-confirm', compact('token', 'row', 'subcon', 'productionGroup'));
    }

    /**
     * Director rejection → back to MD Production (NOT back to QC): the director
     * stamp is written with the 'Rejected:' prefix AND the HO signature is
     * cleared in the same atomic update, re-opening stage 2. The Factory Rep
     * signature is untouched. MD Production is re-emailed the Final Approval
     * link (same token) with the Director's reason.
     */
    public function directorDecline(Request $request, string $token)
    {
        $row = $this->findByToken($token);

        if (! $row) {
            return view('qc.approval-result', [
                'state' => 'invalid',
                'message' => 'This approval link is invalid or has expired.',
            ]);
        }

        if ($guard = $this->directorStageGuard($row)) {
            return $guard;
        }

        $request->validate(['reason' => 'nullable|string|max:1000']);
        $reason = trim((string) $request->input('reason', ''));

        $directorActor = $this->actorLabel($request, self::DIRECTOR_SIGNER);
        $signature = 'Rejected: '.$directorActor
            .' [UTC+07:00: '.now('Asia/Jakarta')->format('Y-m-d H:i:s').']';

        $reset = [
            'director_approval_signature' => $signature,
            'ho_approval_signature' => null, // back to MD Production
        ];
        // Back to MD Production means redoing BOTH steps of the gate.
        if (Schema::connection('qms')->hasColumn(self::TABLE, 'ho_validation_signature')) {
            $reset['ho_validation_signature'] = null;
        }

        try {
            $affected = DB::connection('qms')->table(self::TABLE)
                ->where('approval_token', $token)
                ->where(function ($q) {
                    $q->whereNull('director_approval_signature')->orWhere('director_approval_signature', '');
                })
                ->update($reset);
        } catch (\Throwable $e) {
            Log::error('QC director reject write failed', ['token' => $token, 'error' => $e->getMessage()]);

            return view('qc.approval-result', [
                'state' => 'invalid',
                'message' => 'Could not record the rejection. Please try again, or contact support if this persists.',
            ]);
        }

        if ($affected === 0) {
            return view('qc.approval-result', [
                'state' => 'already',
                'message' => 'This inspection has already been actioned by the Director.',
            ]);
        }

        Log::info('QC director authorization rejected', ['token' => $token, 'project_id' => $row->project_id ?? null, 'reason' => $reason]);

        // Workflow state changed (HO signature cleared + director stamp) →
        // re-render the shared document so nothing keeps showing the old HO sig.
        $this->refreshVerifiedDoc($row);

        [$subcon, , $productionGroup] = $this->subconContext($row);
        \App\Models\SubconApprovalLog::record([
            'order_id' => $subcon->id ?? null,
            'order_number' => $subcon->order_number ?? ($row->project_id ?? null),
            'vendor_name' => $subcon?->vendor?->name,
            'gate' => 'director',
            'decision' => 'declined',
            'actor' => $directorActor,
            'source' => $request->user() ? 'portal' : 'email',
            'note' => 'Director rejection recorded — back to MD Production.'.($reason !== '' ? ' Reason: '.$reason : ''),
        ]);

        // Re-open stage 2: send MD Production the Final Approval link again,
        // carrying the Director's reason.
        $this->sendHoApprovalRequest($token, $row, $reason !== ''
            ? 'Rejected by the Director — reason: '.$reason
            : 'Rejected by the Director. Please review and re-approve.');

        return view('qc.approval-result', [
            'state' => 'rejected',
            'message' => 'Director rejection recorded. MD Production has been asked to review again.',
            'signature' => $signature,
        ]);
    }

    /**
     * Shared entry guard for all Director endpoints: stage 2 must be signed
     * (Digitally Signed) and the Director must not have actioned it yet.
     * Returns a response to short-circuit with, or null to proceed.
     */
    private function directorStageGuard(object $row)
    {
        $directorSig = (string) ($row->director_approval_signature ?? '');
        if ($directorSig !== '') {
            $rejected = str_starts_with($directorSig, 'Rejected:');

            return view('qc.approval-result', [
                'state' => $rejected ? 'rejected' : 'already',
                'message' => $rejected
                    ? 'This inspection has been rejected by the Director and sent back to MD Production.'
                    : 'This inspection has already received Director authorization.',
                'signature' => $directorSig,
            ]);
        }

        $hoSig = (string) ($row->ho_approval_signature ?? '');
        if (! str_contains($hoSig, 'Digitally Signed:')) {
            return view('qc.approval-result', [
                'state' => 'invalid',
                'message' => 'This inspection is not ready for Director authorization — it has not (or no longer) been approved by MD Production.',
            ]);
        }

        // Two-step MD gate: approval alone no longer opens the Director stage —
        // MD Production must also press "Validate & Send Approval" after the
        // RAF production run. (Rows signed under the single-step flow were
        // backfilled as validated by the 2026-07-17 migration.)
        try {
            if (Schema::connection('qms')->hasColumn(self::TABLE, 'ho_validation_signature')
                && trim((string) ($row->ho_validation_signature ?? '')) === '') {
                return view('qc.approval-result', [
                    'state' => 'invalid',
                    'message' => 'This inspection is not ready for Director authorization — MD Production has approved it but has not validated & sent it yet.',
                ]);
            }
        } catch (\Throwable $e) {
            // Column check unreadable — fall through; the signature guard above
            // and the idempotent director write still protect the stage.
        }

        // Legacy safety net: projects completed under the old two-stage flow
        // (console "Complete & Sync") have an HO signature but no director
        // stamp. Authorizing one would re-queue a REAL invoice RPA job for an
        // already-invoiced project — treat completed as already actioned.
        try {
            $status = DB::connection('qms')->table('packaging_projects')
                ->where('project_id', (string) ($row->project_id ?? ''))
                ->value('status');
            if ($status === 'completed') {
                return view('qc.approval-result', [
                    'state' => 'already',
                    'message' => 'This project is already completed — no Director authorization is needed.',
                ]);
            }
        } catch (\Throwable $e) {
            // Status unreadable — fall through; the idempotent signature write
            // still protects against double-signing.
        }

        return null;
    }

    /**
     * Live status of the queued RAF production job for the validate-and-send
     * page ('pending'|'processing'|'completed'|'failed'|null). Best-effort —
     * the RPA DB is remote and unreachable must never break the page.
     */
    private function rafJobStatus(?string $projectId): ?string
    {
        if (! $projectId) {
            return null;
        }
        try {
            $status = DB::connection('rpa')->table('rpa_queues')
                ->where('entity_id', $projectId)
                ->where('rpa_type', 'job_trans_raf')
                ->orderByDesc('id')
                ->value('status');

            return $status !== null ? (string) $status : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Latest session version label for the RPA payloads (console-compatible). */
    private function latestSessionVersion(string $projectId): string
    {
        try {
            $v = DB::connection('qms')->table(self::TABLE)
                ->where('project_id', $projectId)
                ->orderByDesc('cycle_number')
                ->value('version');

            return $v ?: 'v1.0';
        } catch (\Throwable $e) {
            return 'v1.0';
        }
    }

    /**
     * Regenerate the inspection PDF from QMS data and store it as verified_doc.
     * Called after every signature milestone so the document always carries the
     * signatures collected so far — the console no longer needs to be open.
     */
    private function refreshVerifiedDoc(object $row): void
    {
        try {
            $svc = app(QcReportPdfService::class);
            $doc = $svc->renderDataUri((string) $row->project_id, (string) $row->session_id);
            if ($doc !== null) {
                $svc->writeVerifiedDoc((string) $row->project_id, $doc);
            }
        } catch (\Throwable $e) {
            Log::warning('QC verified_doc refresh failed', ['project' => $row->project_id ?? null, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Best-effort reject notification to the QC inspector's device-registered
     * email (packaging_project_sessions.inspector_email, written by the
     * console's startup identity popup). Silently skipped when absent.
     */
    private function notifyInspectorOfRejection(object $row, string $stage, string $actor): void
    {
        try {
            $to = trim((string) ($row->inspector_email ?? ''));
            if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
                Log::info('QC reject notification skipped: no inspector email on session', ['session' => $row->session_id ?? null]);

                return;
            }

            [$subcon, , $productionGroup] = $this->subconContext($row);

            \Illuminate\Support\Facades\Mail::send('emails.qc-reject-notification', [
                'stage' => $stage,
                'actor' => $actor,
                'inspector' => $row->inspector ?? null,
                'orderNumber' => $subcon->order_number ?? null,
                'productionGroup' => $productionGroup,
                'projectId' => $row->project_id ?? null,
                'sessionId' => $row->session_id ?? null,
            ], function ($m) use ($to, $subcon, $stage) {
                $ref = trim((string) ($subcon->order_number ?? ''));
                $m->to($to)
                    ->subject('Inspection rejected by '.$stage.($ref !== '' ? ' — '.$ref : ''));
            });

            Log::info('QC reject notification sent to inspector', ['to' => $to, 'stage' => $stage]);
        } catch (\Throwable $e) {
            Log::warning('QC reject notification failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Chain step after MD Production's Review & Approve: email the Validate &
     * Send link (same token — the ho-approve form renders the validate step)
     * to the MD Production list, so step 2 of the gate is reachable from the
     * inbox. Same recipient resolution as sendHoApprovalRequest; attaches the
     * just-refreshed verified_doc.
     */
    private function sendValidateSendRequest(string $token, object $row, string $approvedBy): void
    {
        try {
            $to = Setting::getValue('qc_ho_approver_email');
            if (trim((string) $to) === '') {
                $to = Setting::getValue('subcon_cutting_approver_email');
            }
            $recipients = collect(preg_split('/[,;]+/', (string) $to))
                ->map(fn ($e) => trim($e))->filter()->values()->all();

            if (empty($recipients)) {
                Log::warning('QC validate-send email skipped: no recipient configured', ['token' => $token]);

                return;
            }

            [$subcon, , $productionGroup] = $this->subconContext($row);

            $ref = collect([
                trim((string) ($subcon->title ?? '')),
                trim((string) ($subcon->order_number ?? '')),
                trim((string) ($productionGroup ?? '')),
            ])->filter()->implode(' — ');

            // One job per recipient: each link carries the recipient's address
            // (`as`) so the send is attributed to the person who clicked.
            foreach ($recipients as $recipient) {
                \App\Jobs\SendQcNotificationEmail::dispatch([
                    'view' => 'emails.qc-validate-send',
                    'recipients' => [$recipient],
                    'subject' => 'Validation needed: Validate & Send Approval'.($ref !== '' ? ' — '.$ref : ''),
                    'projectId' => $row->project_id ?? null,
                    'attachmentName' => 'packaging-inspection-'.($subcon->order_number ?? 'inspection'),
                    'viewData' => [
                        'url' => route('qc.ho-approve', ['token' => $token, 'as' => $recipient]),
                        'sessionId' => $row->session_id ?? null,
                        'projectId' => $row->project_id ?? null,
                        'productionGroup' => $productionGroup,
                        'orderNumber' => $subcon->order_number ?? null,
                        'remarks' => $subcon->remarks ?? null,
                        'approvedBy' => $approvedBy,
                    ],
                ]);
            }

            Log::info('QC validate-send email queued', ['token' => $token, 'to' => $recipients]);
        } catch (\Throwable $e) {
            Log::error('QC validate-send email failed', ['token' => $token, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Chain step after MD Production approves: email the Director authorization
     * link (same token). Recipients from `qc_director_approver_email`, empty-aware
     * fallback to the Final (HO) list. Attaches the just-refreshed verified_doc.
     */
    private function sendDirectorApprovalRequest(string $token, object $row): void
    {
        try {
            $to = Setting::getValue('qc_director_approver_email');
            if (trim((string) $to) === '') {
                $to = Setting::getValue('qc_ho_approver_email');
            }
            if (trim((string) $to) === '') {
                $to = Setting::getValue('subcon_cutting_approver_email');
            }
            $recipients = collect(preg_split('/[,;]+/', (string) $to))
                ->map(fn ($e) => trim($e))->filter()->values()->all();

            if (empty($recipients)) {
                Log::warning('QC director approval email skipped: no recipient configured', ['token' => $token]);

                return;
            }

            [$subcon, , $productionGroup] = $this->subconContext($row);

            $ref = collect([
                trim((string) ($subcon->title ?? '')),
                trim((string) ($subcon->order_number ?? '')),
                trim((string) ($productionGroup ?? '')),
            ])->filter()->implode(' — ');

            // One job per recipient: each link carries the recipient's address
            // (`as`) so the authorization is attributed to the person who clicked.
            foreach ($recipients as $recipient) {
                \App\Jobs\SendQcNotificationEmail::dispatch([
                    'view' => 'emails.qc-director-approval',
                    'recipients' => [$recipient],
                    'subject' => 'Authorization needed: Director Approval'.($ref !== '' ? ' — '.$ref : ''),
                    'projectId' => $row->project_id ?? null,
                    'attachmentName' => 'packaging-inspection-'.($subcon->order_number ?? 'inspection'),
                    'viewData' => [
                        'url' => route('qc.director-approve', ['token' => $token, 'as' => $recipient]),
                        'declineUrl' => route('qc.director-decline', ['token' => $token, 'as' => $recipient]),
                        'sessionId' => $row->session_id ?? null,
                        'projectId' => $row->project_id ?? null,
                        'productionGroup' => $productionGroup,
                        'orderNumber' => $subcon->order_number ?? null,
                        'remarks' => $subcon->remarks ?? null,
                    ],
                ]);
            }

            Log::info('QC director approval email queued', ['token' => $token, 'to' => $recipients]);
        } catch (\Throwable $e) {
            Log::error('QC director approval email failed', ['token' => $token, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Post-completion broadcast: the fully-signed PDF goes to everyone in the
     * chain — QC inspector, Factory Representative, MD Production list, and
     * Director list — deduplicated.
     */
    /**
     * Progress notification to the EARLIER participants when a later stage
     * approves: the QC inspector when the factory representative confirms, the
     * inspector + factory representative when MD Production approves. (Director
     * authorization already notifies everyone via sendCompletionNotification.)
     * Best-effort — never blocks the approval that already committed.
     *
     * @param  list<string>  $recipients
     */
    private function sendStageProgressNotification(object $row, string $stage, string $actor, string $nextStep, array $recipients): void
    {
        try {
            $recipients = collect($recipients)
                ->map(fn ($e) => trim((string) $e))
                ->filter(fn ($e) => $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL))
                ->unique(fn ($e) => strtolower($e))
                ->values()->all();

            if (empty($recipients)) {
                return;
            }

            [$subcon, , $productionGroup] = $this->subconContext($row);

            $ref = collect([
                trim((string) ($subcon->order_number ?? '')),
                trim((string) ($productionGroup ?? '')),
            ])->filter()->implode(' — ');

            \App\Jobs\SendQcNotificationEmail::dispatch([
                'view' => 'emails.qc-stage-update',
                'recipients' => $recipients,
                'subject' => 'Inspection approved by '.$stage.($ref !== '' ? ' — '.$ref : ''),
                'projectId' => $row->project_id ?? null,
                'attachmentName' => 'packaging-inspection-'.($subcon->order_number ?? 'inspection'),
                'viewData' => [
                    'stage' => $stage,
                    'actor' => $actor,
                    'nextStep' => $nextStep,
                    'orderNumber' => $subcon->order_number ?? null,
                    'productionGroup' => $productionGroup,
                    'projectId' => $row->project_id ?? null,
                    'sessionId' => $row->session_id ?? null,
                ],
            ]);

            Log::info('QC stage progress notification queued', ['stage' => $stage, 'to' => $recipients]);
        } catch (\Throwable $e) {
            Log::error('QC stage progress notification failed', ['stage' => $stage, 'error' => $e->getMessage()]);
        }
    }

    private function sendCompletionNotification(object $row, ?object $subcon, ?string $productionGroup): void
    {
        try {
            $recipients = collect();

            $recipients->push(trim((string) ($row->inspector_email ?? '')));   // QC
            $recipients->push(trim((string) ($row->approval_email ?? '')));    // Factory Rep

            $ho = Setting::getValue('qc_ho_approver_email');                    // MD Production
            if (trim((string) $ho) === '') {
                $ho = Setting::getValue('subcon_cutting_approver_email');
            }
            $director = Setting::getValue('qc_director_approver_email');       // Director
            if (trim((string) $director) === '') {
                $director = $ho;
            }
            foreach ([$ho, $director] as $list) {
                foreach (preg_split('/[,;]+/', (string) $list) as $e) {
                    $recipients->push(trim($e));
                }
            }

            $recipients = $recipients
                ->filter(fn ($e) => $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL))
                ->unique(fn ($e) => strtolower($e))
                ->values()->all();

            if (empty($recipients)) {
                Log::warning('QC completion notification skipped: no recipients', ['project' => $row->project_id ?? null]);

                return;
            }

            $ref = collect([
                trim((string) ($subcon->title ?? '')),
                trim((string) ($subcon->order_number ?? '')),
                trim((string) ($productionGroup ?? '')),
            ])->filter()->implode(' — ');

            \App\Jobs\SendQcNotificationEmail::dispatch([
                'view' => 'emails.qc-completion-notification',
                'recipients' => $recipients,
                'subject' => 'Inspection completed & fully signed'.($ref !== '' ? ' — '.$ref : ''),
                'projectId' => $row->project_id ?? null,
                'attachmentName' => 'packaging-inspection-signed-'.($subcon->order_number ?? 'inspection'),
                'viewData' => [
                    'orderNumber' => $subcon->order_number ?? null,
                    'productionGroup' => $productionGroup,
                    'projectId' => $row->project_id ?? null,
                    'sessionId' => $row->session_id ?? null,
                ],
            ]);

            Log::info('QC completion notification queued', ['project' => $row->project_id ?? null, 'to' => $recipients]);
        } catch (\Throwable $e) {
            Log::error('QC completion notification failed', ['error' => $e->getMessage()]);
        }
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
     * after the vendor confirms — or again after a Director rejection ($note
     * carries the reason). Best-effort — failures are logged, never thrown.
     */
    private function sendHoApprovalRequest(string $token, object $row, ?string $note = null): void
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
            $subject = ($note !== null ? 'Re-approval needed: Final Approval' : 'Approval needed: Final Approval').($ref !== '' ? ' — '.$ref : '');

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
                'note' => $note,
            ]);

            Log::info('QC HO approval email queued', ['token' => $token, 'to' => $recipients]);
        } catch (\Throwable $e) {
            Log::error('QC HO approval email failed', ['token' => $token, 'error' => $e->getMessage()]);
        }
    }
}
