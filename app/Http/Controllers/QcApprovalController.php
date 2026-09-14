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

    /**
     * Tag prepended to every WhatsApp notification this controller sends, so
     * a recipient can tell it apart from other bots/systems that may share
     * the same WhatsApp number (the channel bot is a shared internal service,
     * not dedicated to this portal).
     */
    private const WA_CHANNEL_TAG = '[Subcon Vendor Portal]';

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

        // Don't render an approve form for a project that's already finished — see
        // the matching guard in hoApprove() for why (a stray approval here would
        // re-queue job_trans_raf and fail as a false negative against a closed job).
        try {
            $project = DB::connection('qms')->table('packaging_projects')
                ->where('project_id', (string) ($row->project_id ?? ''))
                ->first(['status', 'production_group']);
            $status = (string) ($project->status ?? '');
            if (in_array($status, SubconOrder::QMS_INACTIVE_PROJECT_STATUSES, true)) {
                return view('qc.approval-result', [
                    'state' => $status === 'completed' ? 'already' : 'invalid',
                    'message' => $status === 'completed'
                        ? 'This project is already completed — nothing left to approve.'
                        : 'This project has been removed in the QC console — nothing left to approve.',
                ]);
            }
            if ($this->isFinishedInProductionVsm($project->production_group ?? null)) {
                return view('qc.approval-result', [
                    'state' => 'invalid',
                    'message' => 'This style is already reported as finished in production (VSM/D365) — approving here would fail the RAF automation against a closed job, so it has been blocked.',
                ]);
            }
        } catch (\Throwable $e) {
            // Status unreadable — fall through; hoApprove()'s own guard still applies on submit.
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
            // renders the RAF run status plus the same editable consumption +
            // deduction inputs, and a "Validate & Send Approval" submit
            // (hoSendApproval) that recalculates any revision before sending.
            $validateMode = true;
        }

        // HO gate is calculation+approval: re-expose the same consumption inputs
        // used at the cutting gate, prefilled from the cutting snapshot, so HO can
        // revise them before signing. Recompute + overwrite happens on submit.
        [$subcon, $totalCut, $productionGroup] = $this->subconContext($row);
        $fabricLines = $subcon ? $production->fabricLinesWithData($subcon) : [];

        // A row lands back on this page either because it was never sent to the
        // Director yet, or because the Director just rejected it (which resets
        // ho_validation_signature/director_approval_signature to null — see
        // directorDecline() below). Surface the reason so HO/MD Production
        // knows what to fix before resubmitting.
        $directorRejectReason = null;
        $orderNumberForLog = $subcon?->order_number ?? ($row->project_id ?? null);
        if ($orderNumberForLog) {
            $lastDirectorLog = \App\Models\SubconApprovalLog::where('gate', 'director')
                ->where('decision', 'declined')
                ->where('order_number', $orderNumberForLog)
                ->orderByDesc('created_at')
                ->first(['note']);
            $directorRejectReason = \App\Models\SubconApprovalLog::extractReason($lastDirectorLog->note ?? null);
        }

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

        // Richer fallback for production-detail.blade.php's per-size table when
        // the VSM/PLM chain is broken (forPo() above came back empty) but this
        // order has already been through QC inspection: the console's own
        // report-line snapshot (session_id IS NULL base line) already carries
        // Order Qty per size — the exact same source the signed inspection
        // report itself reads it from (QcReportPdfService::context()) — so
        // "not tied to PLM" doesn't have to mean the per-size table is empty.
        // Best-effort: an unreachable `qms` connection just leaves it [].
        $qcSizeOrderQty = [];
        if (empty($productionGroups)) {
            try {
                if (Schema::connection('qms')->hasTable('packaging_project_reports')) {
                    $qcSizeOrderQty = DB::connection('qms')->table('packaging_project_reports')
                        ->where('project_id', (string) $row->project_id)
                        ->whereNull('session_id')
                        ->pluck('qty_order', 'size_val')
                        ->map(fn ($v) => (int) $v)
                        ->all();
                }
            } catch (\Throwable $e) {
                // Leave empty — the partial just falls further back.
            }
        }

        // Step-2 extras: live RAF run status (best-effort, remote RPA DB).
        $rafStatus = $validateMode ? $this->rafJobStatus((string) $row->project_id) : null;

        // Existing deduction rows — prefill the editable rows in both modes
        // (step 1 sees them again after a Director rejection, step 2 can revise
        // them before sending). Best-effort: the table is console-owned.
        $deductionLines = [];
        try {
            if (Schema::connection('qms')->hasTable('packaging_session_deduction_lines')) {
                $deductionLines = DB::connection('qms')->table('packaging_session_deduction_lines')
                    ->where('session_id', $row->session_id)
                    ->orderBy('id')
                    ->get()
                    ->map(fn ($d) => ['description' => (string) $d->description, 'amount' => (float) $d->amount])
                    ->all();
            }
        } catch (\Throwable $e) {
            // Unreadable — start with no prefilled rows.
        }

        // Material Flow — attach/dispatch is available here too (Final Approval
        // and Report Validation are exactly the two SubconOrder::
        // materialReturnAdminWindowOpen() stages this form renders for).
        $materialReturnService = app(\App\Services\MaterialReturnService::class);
        $materialReturns = $subcon ? $materialReturnService->attachmentsFor($subcon) : collect();
        $materialReturnTask = $subcon ? $materialReturnService->activeTaskFor($subcon) : null;
        // Drives the "Checked by Material Flow" badge — must agree with the
        // ACTUAL send-gate (hoApprove()'s isReturnCheckPending() check), not
        // just the latest task's own status, otherwise the badge can say
        // "Checked" while new undispatched lines still block the send. See
        // MaterialReturnService::isReturnCheckPending()'s docblock.
        $materialReturnPending = $subcon ? $materialReturnService->isReturnCheckPending($subcon) : false;
        // True only when the badge above reads clear SOLELY because of the
        // admin auto-approve override, not a real check — the badge must say
        // so distinctly (see MaterialReturnService::isAutoApproved()).
        $materialReturnAutoApproved = $subcon ? $materialReturnService->isAutoApproved($subcon) : false;

        // Material Reconciliation — Accessory group only, nested as a sub-section of
        // consumption-input.blade.php's "Material Reconciliation & Consumption" card
        // below (via its $showAccessory flag → subcon.partials.material-recon-accessory):
        // fabric waste/consumption is already shown, editable, in that same card's
        // Fabric sub-section, so this stays the Accessory counterpart rather than a
        // separate duplicate card. Editable here too: MD Production may revise it
        // through Report Validation, same as it can revise consumption — persisted in
        // hoApprove()/hoSendApproval() via MaterialReturnService::persistReconciliation().
        $materialAccessoryLines = $subcon ? $production->accessoryLinesForPo($subcon->order_number) : [];
        $materialAccessoryRecon = $subcon
            ? $materialReturnService->linesFor($subcon)->where('item_type', 'accessory')->keyBy('label')
            : collect();
        // Goods Receive (D365 packing-slip receipts) for accessories — the same
        // resolveGoodsReceipts() lookup fabric already gets on this form, scoped
        // to each accessory's own source PO(s) via accessoryLinesForPo()'s
        // po_numbers/item_numbers (never the CMT subcon PO itself).
        $materialAccessoryGoodsReceive = $subcon ? $production->resolveGoodsReceipts($materialAccessoryLines) : [];
        // D365 Material Issue posting (vsm.material_issue_lines), keyed by
        // ItemNumber — same source the PDF report already uses as its Mats
        // Sent fallback (proposal) when no admin qty_sent is saved. Threaded
        // through as a live-form prefill/placeholder for the same field.
        $materialAccessoryIssue = $subcon ? $production->materialIssueForOrder((string) $subcon->production_group) : [];

        return view('qc.ho-approval-form', compact('token', 'row', 'subcon', 'totalCut', 'productionGroup', 'fabricLines', 'productionGroups', 'cuttingReports', 'qcSizeOrderQty', 'validateMode', 'rafStatus', 'deductionLines', 'directorRejectReason', 'materialReturns', 'materialReturnTask', 'materialReturnPending', 'materialReturnAutoApproved', 'materialAccessoryLines', 'materialAccessoryRecon', 'materialAccessoryGoodsReceive', 'materialAccessoryIssue'));
    }

    /**
     * Material Flow attach, from this same token-gated form (Final Approval
     * or Report Validation — both share hoApprovalForm/this page). No
     * session auth here — the `approval_token` itself is the credential,
     * same as every other action on this page.
     */
    public function uploadMaterialReturnSigned(Request $request, string $token, \App\Services\MaterialReturnService $materialReturns)
    {
        $row = $this->findByToken($token);
        if (! $row) {
            return view('qc.approval-result', ['state' => 'invalid', 'message' => 'This approval link is invalid or has expired.']);
        }

        [$subcon] = $this->subconContext($row);
        if (! $subcon || ! $subcon->materialReturnAdminWindowOpen()) {
            return back()->with('error', 'A material-return note can only be attached between Final Approval and Report Validation.');
        }

        $data = $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'note' => 'nullable|string|max:1000',
        ]);

        $materialReturns->upload($subcon, $data['file'], $data['note'] ?? null, \App\Models\MaterialReturnAttachment::ROLE_ADMIN, self::HO_SIGNER);

        return back()->with('success', 'Material-return delivery note attached.');
    }

    /**
     * Removes an admin-uploaded delivery note, from this same token-gated
     * form. Same window as uploadMaterialReturnSigned() above; scoped to
     * admin's own uploads — see MaterialReturnService::deleteAttachment()'s
     * docblock. Mirrors SubconAdminController::deleteMaterialReturn() for
     * the in-app entry point.
     */
    public function deleteMaterialReturnSigned(string $token, string $attachment, \App\Services\MaterialReturnService $materialReturns)
    {
        $row = $this->findByToken($token);
        if (! $row) {
            return view('qc.approval-result', ['state' => 'invalid', 'message' => 'This approval link is invalid or has expired.']);
        }

        [$subcon] = $this->subconContext($row);
        if (! $subcon || ! $subcon->materialReturnAdminWindowOpen()) {
            return back()->with('error', 'A material-return note can only be removed between Final Approval and Report Validation.');
        }

        $error = $materialReturns->deleteAttachment($subcon, $attachment, \App\Models\MaterialReturnAttachment::ROLE_ADMIN);

        return $error
            ? back()->with('error', $error)
            : back()->with('success', 'Delivery note removed.');
    }

    /**
     * "Send to Material Flow" from this same token-gated form. See
     * SubconAdminController::dispatchMaterialReturnTask for the in-app
     * counterpart — both funnel through MaterialReturnService::dispatchTask().
     */
    public function dispatchMaterialReturnTaskSigned(string $token, \App\Services\MaterialReturnService $materialReturns)
    {
        $row = $this->findByToken($token);
        if (! $row) {
            return view('qc.approval-result', ['state' => 'invalid', 'message' => 'This approval link is invalid or has expired.']);
        }

        [$subcon] = $this->subconContext($row);
        if (! $subcon || ! $subcon->materialReturnAdminWindowOpen()) {
            return back()->with('error', 'Material Flow can only be dispatched between Final Approval and Report Validation.');
        }

        $materialReturns->dispatchTask($subcon, self::HO_SIGNER);

        return back()->with('success', 'Sent to Material Flow — inventory will check the returned material.');
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

        // Don't let a stale/duplicate approval re-queue job_trans_raf for a project
        // that's already finished — either QC-side (completed, or removed
        // post-completion in the console; same status set as hoSendApproval and
        // directorStageGuard, see SubconOrder::QMS_INACTIVE_PROJECT_STATUSES), or
        // VSM/D365-side (the style already reported finished in production, per
        // isFinishedInProductionVsm() — that job is closed in D365 regardless of
        // where QC's own approval chain is, so RAF would fail against it). Either
        // way the RPA would fail and show up as a false failure. A plain 'removed'
        // status is just a device-side archive/hide, not "finished", so it is not
        // guarded here.
        try {
            $project = DB::connection('qms')->table('packaging_projects')
                ->where('project_id', (string) ($row->project_id ?? ''))
                ->first(['status', 'production_group']);
            $status = (string) ($project->status ?? '');
            if (in_array($status, SubconOrder::QMS_INACTIVE_PROJECT_STATUSES, true)) {
                return view('qc.approval-result', [
                    'state' => $status === 'completed' ? 'already' : 'invalid',
                    'message' => $status === 'completed'
                        ? 'This project is already completed — nothing left to approve.'
                        : 'This project has been removed in the QC console — nothing left to approve.',
                ]);
            }
            if ($this->isFinishedInProductionVsm($project->production_group ?? null)) {
                return view('qc.approval-result', [
                    'state' => 'invalid',
                    'message' => 'This style is already reported as finished in production (VSM/D365) — approving here would fail the RAF automation against a closed job, so it has been blocked.',
                ]);
            }
        } catch (\Throwable $e) {
            // Status unreadable — fall through; approving is still safe, it just
            // won't be blocked by this particular guard.
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
            // Material Reconciliation — Accessory group, same shape as the vendor's
            // saveMaterialReconciliation() (subcon.partials.material-recon-accessory).
            'accessories_recon' => 'nullable|array',
            'accessories_recon.*.label' => 'required_with:accessories_recon|string|max:500',
            'accessories_recon.*.qty' => 'nullable|numeric|min:0',
            'accessories_recon.*.mats_sent' => 'nullable|numeric|min:0',
            'accessories_recon.*.price' => 'nullable|numeric|min:0',
            'accessories_recon.*.unit' => 'nullable|string|max:20',
            // MD Production's own remarks — distinct from the vendor's/QC's.
            'ho_remarks' => 'required|string|max:2000',
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
        if ($subcon) {
            app(\App\Services\MaterialReturnService::class)->persistReconciliation(
                $subcon,
                [],
                $data['accessories_recon'] ?? [],
                \App\Models\MaterialReturnAttachment::ROLE_ADMIN,
                $this->actorLabel($request, self::HO_SIGNER),
                lockReturKain: false
            );
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
            $replaceDeductions = $request->has('deductions_present');
            DB::connection('qms')->transaction(function () use ($row, $token, $data, $signature, $replaceDeductions) {
                // Deduction lines (session-level, optional, 0+). The form
                // re-submits the FULL list (marked by `deductions_present`), so
                // a re-approval after a Director rejection replaces the previous
                // rows instead of accumulating duplicates.
                if (Schema::connection('qms')->hasTable('packaging_session_deduction_lines')) {
                    if ($replaceDeductions) {
                        DB::connection('qms')->table('packaging_session_deduction_lines')
                            ->where('session_id', $row->session_id)
                            ->delete();
                    }
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
                    // Defensive: directorDecline() already clears this stamp itself
                    // (a Director rejection re-opens Validate & Send, not this Review
                    // & Approve step, so a re-run here shouldn't normally see it set).
                    // Kept as a safety net for any row left over from before that
                    // change, so a stale stamp can never block the Director link.
                    if (Schema::connection('qms')->hasColumn(self::TABLE, 'director_approval_signature')) {
                        $update['director_approval_signature'] = null;
                    }
                    // Every (re-)approval starts a fresh validate-and-send cycle.
                    if (Schema::connection('qms')->hasColumn(self::TABLE, 'ho_validation_signature')) {
                        $update['ho_validation_signature'] = null;
                    }
                    if (Schema::connection('qms')->hasColumn(self::TABLE, 'ho_remarks')) {
                        $update['ho_remarks'] = $data['ho_remarks'] ?? null;
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
     * after the RAF run. It is also the LAST edit point: the validate form
     * carries the same editable consumption + deduction inputs as step 1, so
     * any revision is recalculated through SubconConsumptionService, the
     * fabric lines re-published to QMS, and the deduction rows replaced —
     * before the Director sees anything. Idempotent via
     * `ho_validation_signature` (same 'Digitally Signed:' contract as the
     * other signature columns); only the request that wins the signature
     * write may rewrite the deduction rows.
     */
    public function hoSendApproval(Request $request, string $token, SubconConsumptionService $consumption, SubconFabricLinePublisher $publisher)
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

        // Idempotency guard, same position as hoApprove's ho_approval_signature
        // check above: a resubmit (double click, resend) of an already-sent
        // approval must stop here, BEFORE any consumption mutation — not after.
        // Whichever stage is furthest along wins; nothing below this point may
        // rewrite figures the Director could already be reviewing.
        if (trim((string) ($row->ho_validation_signature ?? '')) !== '') {
            return view('qc.approval-result', [
                'state' => 'already',
                'message' => 'This approval has already been sent to the Director.',
                'signature' => $row->ho_validation_signature,
            ]);
        }

        // Don't ask the Director about projects that need no action any more
        // (completed under the legacy flow, or archived post-completion in
        // the QC console). A plain 'removed' status is just a device-side
        // archive/hide and does NOT mean the workflow is done — see
        // SubconOrder::QMS_INACTIVE_PROJECT_STATUSES — so it is not guarded
        // here; the project should still be sendable to the Director.
        try {
            $status = (string) DB::connection('qms')->table('packaging_projects')
                ->where('project_id', (string) ($row->project_id ?? ''))
                ->value('status');
            if (in_array($status, SubconOrder::QMS_INACTIVE_PROJECT_STATUSES, true)) {
                return view('qc.approval-result', [
                    'state' => $status === 'completed' ? 'already' : 'invalid',
                    'message' => $status === 'completed'
                        ? 'This project is already completed — nothing to send to the Director.'
                        : 'This project has been removed in the QC console — nothing to send to the Director.',
                ]);
            }
        } catch (\Throwable $e) {
            // Status unreadable — fall through; the Director-side guard still
            // refuses inactive projects.
        }

        $data = $request->validate([
            'deductions' => 'nullable|array',
            'deductions.*.description' => 'nullable|string|max:255',
            'deductions.*.amount' => 'nullable|numeric|min:0',
            // The validate step re-exposes the consumption inputs — same shape
            // as hoApprove, recalculated by the same engine.
            'fabrics' => 'nullable|array',
            'fabrics.*.label' => 'required_with:fabrics|string|max:500',
            'fabrics.*.short_roll' => 'nullable|numeric|min:0',
            'fabrics.*.sisa_kain' => 'nullable|numeric|min:0',
            'fabrics.*.kepala_kain' => 'nullable|numeric|min:0',
            'fabrics.*.retur_kain' => 'nullable|numeric|min:0',
            'fabrics.*.fabric_sent' => 'nullable|numeric|min:0',
            'fabrics.*.consumption_plan' => 'nullable|numeric|min:0',
            'fabrics.*.fabric_price' => 'nullable|numeric|min:0',
            // Material Reconciliation — Accessory group, same shape as the vendor's
            // saveMaterialReconciliation() (subcon.partials.material-recon-accessory).
            'accessories_recon' => 'nullable|array',
            'accessories_recon.*.label' => 'required_with:accessories_recon|string|max:500',
            'accessories_recon.*.qty' => 'nullable|numeric|min:0',
            'accessories_recon.*.mats_sent' => 'nullable|numeric|min:0',
            'accessories_recon.*.price' => 'nullable|numeric|min:0',
            'accessories_recon.*.unit' => 'nullable|string|max:20',
            // MD Production's own remarks — distinct from the vendor's/QC's.
            'ho_remarks' => 'required|string|max:2000',
        ]);

        [$subcon, , $productionGroup] = $this->subconContext($row);
        $actor = $this->actorLabel($request, self::HO_SIGNER);

        // Persist whatever MD Production entered BEFORE the Material Flow gate
        // below (and before the send-claim) — a submit must never discard
        // typed figures just because sending is blocked. Previously this save
        // only ran after the gate passed, so a pending Material Flow check
        // silently threw away every revision MD Production made while
        // waiting, and the only way to notice the block was a permanently
        // disabled submit button with no save path — see ho-approval-form.blade.php.
        if ($subcon && ! empty($data['fabrics'])) {
            DB::transaction(function () use ($consumption, $subcon, $data) {
                $consumption->persist($subcon, $data['fabrics']);
            });
        }
        if ($subcon) {
            app(\App\Services\MaterialReturnService::class)->persistReconciliation(
                $subcon,
                [],
                $data['accessories_recon'] ?? [],
                \App\Models\MaterialReturnAttachment::ROLE_ADMIN,
                $actor,
                lockReturKain: false
            );
        }
        if (Schema::connection('qms')->hasColumn(self::TABLE, 'ho_remarks')) {
            DB::connection('qms')->table(self::TABLE)
                ->where('approval_token', $token)
                ->update(['ho_remarks' => $data['ho_remarks'] ?? null]);
        }

        // Don't ask the Director about projects that need no action any more
        // (completed under the legacy flow, or archived post-completion in
        // the QC console). A plain 'removed' status is just a device-side
        // archive/hide and does NOT mean the workflow is done — see
        // SubconOrder::QMS_INACTIVE_PROJECT_STATUSES — so it is not guarded
        // here; the project should still be sendable to the Director.
        try {
            $status = (string) DB::connection('qms')->table('packaging_projects')
                ->where('project_id', (string) ($row->project_id ?? ''))
                ->value('status');
            if (in_array($status, SubconOrder::QMS_INACTIVE_PROJECT_STATUSES, true)) {
                return view('qc.approval-result', [
                    'state' => $status === 'completed' ? 'already' : 'invalid',
                    'message' => $status === 'completed'
                        ? 'This project is already completed — nothing to send to the Director.'
                        : 'This project has been removed in the QC console — nothing to send to the Director.',
                ]);
            }
        } catch (\Throwable $e) {
            // Status unreadable — fall through; the Director-side guard still
            // refuses inactive projects.
        }

        // Material Flow gate: if MD Production dispatched a returned-material
        // check (SubconAdminController::dispatchMaterialReturnTask), value_stream_ops's
        // inventory staff must confirm it before this can go to the Director.
        // A no-op for orders that never had a task dispatched. Blocked here
        // stays ON this same form (redirect back, not a dead-end result page)
        // — the form already shows this exact gate inline (the "Waiting on
        // Material Flow" banner). The submit button is deliberately clickable
        // even while blocked (no `disabled` attribute) so this check runs
        // AFTER the persist above, not instead of it — see ho-approval-form.blade.php.
        $materialReturnService = app(\App\Services\MaterialReturnService::class);
        // Captured BEFORE the gate check below — isAutoApproved() only
        // reads true while a real check is genuinely outstanding, so this
        // must be read while that's still the case, not after. See
        // MaterialReturnService::isAutoApproved()'s docblock and the
        // SubconApprovalLog note appended below ("keep note").
        $materialReturnAutoApproved = $subcon && $materialReturnService->isAutoApproved($subcon);
        if ($subcon && $materialReturnService->isReturnCheckPending($subcon)) {
            return redirect()->route('qc.ho-approve', ['token' => $token])
                ->with('error', "Your changes were saved. Report Validation can't be sent to the Director yet — Material Flow still needs to check the returned material.");
        }

        $signature = 'Digitally Signed: '.$actor
            .' [UTC+07:00: '.now('Asia/Jakarta')->format('Y-m-d H:i:s').']';

        // Claim the send FIRST, atomically, before anything else is mutated:
        // only the request that wins this update may persist consumption,
        // publish fabric lines, or replace deduction rows below. Claiming
        // before mutating (rather than after) is what makes a genuinely
        // concurrent double-submit safe too, on top of the front guard above
        // that already catches the sequential case.
        $already = false;
        try {
            DB::connection('qms')->transaction(function () use ($token, $signature, &$already) {
                if (Schema::connection('qms')->hasColumn(self::TABLE, 'ho_validation_signature')) {
                    $affected = DB::connection('qms')->table(self::TABLE)
                        ->where('approval_token', $token)
                        ->where(function ($q) {
                            $q->whereNull('ho_validation_signature')->orWhere('ho_validation_signature', '');
                        })
                        ->update(['ho_validation_signature' => $signature]);

                    if ($affected === 0) {
                        $already = true;
                    }
                } else {
                    // Column missing (console DDL race) — proceed anyway, but without
                    // the idempotency marker a double click could re-email the Director.
                    Log::warning('QC HO send: ho_validation_signature column missing — send not idempotent', ['token' => $token]);
                }
            });
        } catch (\Throwable $e) {
            Log::error('QC HO send write failed', ['token' => $token, 'error' => $e->getMessage()]);

            return view('qc.approval-result', [
                'state' => 'invalid',
                'message' => 'Could not record the send. Please try again, or contact support if this persists.',
            ]);
        }

        if ($already) {
            return view('qc.approval-result', [
                'state' => 'already',
                'message' => 'This approval has already been sent to the Director.',
                'signature' => $row->ho_validation_signature,
            ]);
        }

        // Won the send — the revised consumption/reconciliation/remarks were
        // already persisted above (before the Material Flow gate), so all
        // that's left is re-publishing the fabric lines to QMS so the
        // Director's document and the deduction RPA total carry the final
        // numbers.
        if ($subcon) {
            $publisher->publish($subcon, (string) $row->project_id);
            app(\App\Services\SubconRemarksPublisher::class)->publish($subcon);
        }

        // Deduction rows: the validate form re-submits the FULL list (marked
        // by `deductions_present`) — replace this session's rows so edits and
        // removals stick. Safe here — this request already won the claim above.
        if ($request->has('deductions_present') && Schema::connection('qms')->hasTable('packaging_session_deduction_lines')) {
            DB::connection('qms')->transaction(function () use ($row, $data) {
                DB::connection('qms')->table('packaging_session_deduction_lines')
                    ->where('session_id', $row->session_id)
                    ->delete();
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
            });
        }

        Log::info('QC HO validation signed — sending to Director', ['token' => $token, 'project_id' => $row->project_id]);
        \App\Models\SubconApprovalLog::record([
            'order_id' => $subcon->id ?? null,
            'order_number' => $subcon->order_number ?? ($row->project_id ?? null),
            'vendor_name' => $subcon?->vendor?->name,
            'gate' => 'final',
            'decision' => 'approved',
            'actor' => $actor,
            'source' => $request->user() ? 'portal' : 'email',
            'note' => 'Numbers validated — approval sent to the Director for authorization.'
                .($materialReturnAutoApproved
                    ? ' [Material Flow check was auto-approved via the admin override — not verified by inventory.]'
                    : ''),
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
    public function directorApprovalForm(Request $request, string $token, QcReportPdfService $reportPdf, SubconProductionService $production)
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

        [$subcon, $totalCut, $productionGroup] = $this->subconContext($row);
        $report = $reportPdf->context((string) $row->project_id, (string) $row->session_id);

        // Same per-size cutting/production + fabric consumption detail the HO
        // gate shows — read-only here (no editing at the Director stage).
        // Best-effort: VSM may be unreachable.
        $fabricLines = $subcon ? $production->fabricLinesWithData($subcon) : [];
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

        // Richer fallback for production-detail.blade.php's per-size table when
        // the VSM/PLM chain is broken — see the identical fetch/comment in
        // hoApprovalForm() above.
        $qcSizeOrderQty = [];
        if (empty($productionGroups)) {
            try {
                if (Schema::connection('qms')->hasTable('packaging_project_reports')) {
                    $qcSizeOrderQty = DB::connection('qms')->table('packaging_project_reports')
                        ->where('project_id', (string) $row->project_id)
                        ->whereNull('session_id')
                        ->pluck('qty_order', 'size_val')
                        ->map(fn ($v) => (int) $v)
                        ->all();
                }
            } catch (\Throwable $e) {
                // Leave empty — the partial just falls further back.
            }
        }

        return view('qc.director-approval-form', compact(
            'token', 'row', 'subcon', 'productionGroup', 'report',
            'totalCut', 'fabricLines', 'productionGroups', 'cuttingReports', 'qcSizeOrderQty'
        ));
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

            // Deduction gets its own document — the debit-note page (Document
            // No/Invoice Date blank, filled in later by the debit_note RPA bot)
            // prepended ahead of the same inspection report — not the plain
            // $signedDoc used for the invoice job.
            $deductionDoc = (string) $signedDoc;
            if ($deductionTotal > 0) {
                $deductionDoc = $reportPdf->renderDeductionDataUri(
                    $projectId,
                    (string) $row->session_id,
                    (string) ($project->po_info ?? ''),
                    (string) ($project->po_vendor ?? ''),
                    $deductionTotal,
                ) ?? $deductionDoc;
            }
            $rpa->queueDeduction($project, $latestVersion, $deductionDoc, $deductionTotal, originalDoc: (string) $signedDoc);

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
     * Director rejection → back to Report Validation (NOT back to a full MD
     * Production redo): only `ho_validation_signature` is cleared, re-opening
     * step 2b (Validate & Send). `ho_approval_signature` — MD Production's
     * consumption entry + sign-off from step 2a — is left INTACT, so MD
     * Production does not have to re-enter Fabric Sent/Cons. Plan; they just
     * re-review the (possibly RAF-updated) numbers and send again. The
     * Factory Rep signature is untouched either way.
     *
     * `director_approval_signature` is cleared back to NULL in the same
     * update rather than left as a lingering 'Rejected: …' stamp: both
     * pendingValidateSends() (Report Validation tab) and directorStageGuard()
     * treat a non-empty value as "already actioned", which would hide the row
     * from the tab and block the re-opened Validate & Send link. The
     * rejection itself is still permanently recorded in SubconApprovalLog
     * below (the Director tab's decision history reads that, not this
     * column) and the confirmation page shown to the Director still displays
     * a locally-built signature string.
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
            'director_approval_signature' => null, // re-opened, not a terminal stamp — see docblock
        ];
        if (Schema::connection('qms')->hasColumn(self::TABLE, 'ho_validation_signature')) {
            $reset['ho_validation_signature'] = null; // back to Validate & Send only
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

        // Workflow state changed (validation cleared) → re-render the shared
        // document so nothing keeps showing the stale validation state.
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
            'note' => 'Director rejection recorded — back to Report Validation.'.($reason !== '' ? ' Reason: '.$reason : ''),
        ]);

        // Re-open step 2b only: send MD Production the Validate & Send link
        // again (NOT the Review & Approve form), carrying the Director's reason.
        $this->sendValidateSendRequest($token, $row, $directorActor, $reason !== ''
            ? 'Rejected by the Director — reason: '.$reason
            : 'Rejected by the Director. Please re-validate and send again.');

        return view('qc.approval-result', [
            'state' => 'rejected',
            'message' => 'Director rejection recorded. MD Production has been asked to re-validate and send again.',
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
                    ? 'This inspection has been rejected by the Director and sent back to Report Validation.'
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
        // Projects archived post-completion (removed_completed) need no
        // authorization either. A plain 'removed' status is just a
        // device-side archive/hide (see SubconOrder::QMS_INACTIVE_PROJECT_STATUSES)
        // and must NOT be guarded here — the Director may still need to act.
        try {
            $status = (string) DB::connection('qms')->table('packaging_projects')
                ->where('project_id', (string) ($row->project_id ?? ''))
                ->value('status');
            if ($status === 'completed') {
                return view('qc.approval-result', [
                    'state' => 'already',
                    'message' => 'This project is already completed — no Director authorization is needed.',
                ]);
            }
            if ($status === 'removed_completed') {
                return view('qc.approval-result', [
                    'state' => 'invalid',
                    'message' => 'This project has been removed in the QC console — no Director authorization is needed.',
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
     * Chain step after MD Production's Review & Approve — OR again after a
     * Director rejection ($note set) — email the Validate & Send link (same
     * token — the ho-approve form renders the validate step) to the MD
     * Production list, so step 2 of the gate is reachable from the inbox.
     * Same recipient resolution as sendHoApprovalRequest; attaches the
     * just-refreshed verified_doc.
     */
    private function sendValidateSendRequest(string $token, object $row, string $approvedBy, ?string $note = null): void
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
            $subject = ($note !== null ? 'Re-validation needed: Validate & Send Approval' : 'Validation needed: Validate & Send Approval').($ref !== '' ? ' — '.$ref : '');

            // One job per recipient: each link carries the recipient's address
            // (`as`) so the send is attributed to the person who clicked.
            foreach ($recipients as $recipient) {
                \App\Jobs\SendQcNotificationEmail::dispatch([
                    'view' => 'emails.qc-validate-send',
                    'recipients' => [$recipient],
                    'subject' => $subject,
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
                        'note' => $note,
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

            [$subcon, $totalCut, $productionGroup] = $this->subconContext($row);

            // Same per-size cutting/production + fabric consumption detail shown
            // on the review page, condensed into the email itself so the
            // Director sees the full picture without opening the link.
            // Best-effort: VSM may be unreachable.
            $production = app(SubconProductionService::class);
            $fabricLines = $subcon ? $production->fabricLinesWithData($subcon) : [];
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

            $ref = collect([
                trim((string) ($subcon->title ?? '')),
                trim((string) ($subcon->order_number ?? '')),
                trim((string) ($productionGroup ?? '')),
            ])->filter()->implode(' — ');

            // Same figure the Invoice/Deduction RPA will actually queue on
            // authorization — lets the Director see upfront whether this
            // inspection carries a deduction before opening the form.
            $deductionTotal = app(RpaQueueService::class)->deductionTotal(
                (string) ($row->project_id ?? ''),
                $row->session_id ?? null
            );

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
                        'vendorRemarks' => $subcon->remarks ?? null,
                        'qcRemarks' => $row->remarks ?? null,
                        'hoRemarks' => $row->ho_remarks ?? null,
                        'deductionTotal' => $deductionTotal,
                        'totalCut' => $totalCut,
                        'fabricLines' => $fabricLines,
                        'productionGroups' => $productionGroups,
                        'cuttingReports' => $cuttingReports,
                    ],
                ]);
            }

            Log::info('QC director approval email queued', ['token' => $token, 'to' => $recipients]);

            // WhatsApp mirror of the same request — on top of the email, never
            // instead of it. Paired by index with the email recipients so the
            // common one-phone-per-director case still attributes the click
            // (`as`) correctly; an unpaired phone (more phones configured than
            // emails) falls back to the first configured director email rather
            // than going unattributed — a phone-link approval must still record
            // a real email as the signature reference, never the phone number
            // itself or a generic role label when a real email is known.
            $phones = \App\Services\WhatsAppNotificationService::parsePhoneList(
                (string) Setting::getValue('qc_director_approver_phone', '')
            );
            if (! empty($phones)) {
                $subject = 'Director Authorization Needed'.($ref !== '' ? ' — '.$ref : '');
                foreach ($phones as $i => $phone) {
                    $recipient = $recipients[$i] ?? ($recipients[0] ?? null);
                    // ONE link — the review page itself carries both Authorize
                    // & Sign and Reject actions (qc/director-approval-form.blade.php),
                    // so there is no separate decline URL to send here.
                    $reviewUrl = route('qc.director-approve', array_filter(['token' => $token, 'as' => $recipient]));
                    $dedLine = $deductionTotal > 0
                        ? 'Deduction: Rp '.number_format($deductionTotal, 0, ',', '.')
                        : 'Deduction: None';
                    $cutLine = 'Cutting Qty: '.number_format($totalCut).' pcs';
                    $hasRemarks = trim((string) ($subcon->remarks ?? '')) !== ''
                        || trim((string) ($row->remarks ?? '')) !== ''
                        || trim((string) ($row->ho_remarks ?? '')) !== '';
                    $remarksLine = 'Remarks: '.($hasRemarks ? 'Yes — see review page' : 'None');

                    $message = self::WA_CHANNEL_TAG."\n"
                        ."*{$subject}*\n"
                        ."This Final inspection official report needs your final authorization.\n"
                        .$cutLine."\n"
                        .$dedLine."\n"
                        .$remarksLine."\n\n"
                        ."Review & Authorize: {$reviewUrl}\n"
                        .'(Full cutting-per-size, consumption breakdown and remarks are on that page.)';

                    \App\Jobs\SendWhatsAppNotification::dispatch($phone, $message);
                }
                Log::info('QC director approval WhatsApp queued', ['token' => $token, 'to' => $phones]);
            }
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
            $qcHead = Setting::getValue('qc_head_notification_email');        // QC Head — notification only
            foreach ([$ho, $director, $qcHead] as $list) {
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
     * True when VSM has shadowed this production group as finished on the
     * factory floor — ActualStatus = 'Finished in Production' in
     * plm_activity_shadowing, set once all of the group's D365
     * ProdTableBiEntities are ReportedFinished/Completed. This is distinct
     * from packaging_projects.status: a group can be VSM-finished before QC
     * has approved anything at all, or well after. Queuing job_trans_raf
     * against a VSM-finished group fails in D365 (the job is already
     * closed there) and shows up as a false RPA failure, not a real one.
     */
    private function isFinishedInProductionVsm(?string $productionGroup): bool
    {
        if (! $productionGroup) {
            return false;
        }

        try {
            return DB::connection('vsm')->table('plm_activity_shadowing')
                ->where('ProductionGroup', $productionGroup)
                ->where('ActualStatus', 'Finished in Production')
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
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
