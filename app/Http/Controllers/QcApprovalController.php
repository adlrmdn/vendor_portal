<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\SubconProductionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

        // Consumption is now entered by the subcon admin at cutting-report
        // approval (stored locally); the HO form only signs off + records
        // deductions, so no fabric inputs here.
        [$subcon, $totalCut, $productionGroup] = $this->subconContext($row);

        return view('qc.ho-approval-form', compact('token', 'row', 'subcon', 'totalCut', 'productionGroup'));
    }

    /**
     * Stage 2 commit. Sequence (per the integration contract):
     *   1. INSERT packaging_project_fabric_lines  (project-level)
     *   2. INSERT packaging_session_deduction_lines (session-level, 0+)
     *   3. UPDATE ho_approval_signature            (triggers PDF regen)
     *
     * Fabric consumption (cutt_plan / actual_consumption / overconsumption) was
     * already derived + snapshotted at cutting-report approval — see
     * SubconConsumptionService for the formulas — so this stage only reads them.
     * The QMS objects are console-owned and may not exist yet, so every write is
     * guarded + wrapped — a missing table/column is logged, never fatal
     * (overconsumption is pushed only when the QMS column exists).
     */
    public function hoApprove(Request $request, string $token, SubconProductionService $production)
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
        ]);

        [$subcon] = $this->subconContext($row);

        // Fabric lines are sourced from the consumption entered at cutting-report
        // approval (stored locally per fabric) — not from this form. cutt_plan and
        // actual_consumption were already derived and snapshotted at save time.
        $fabricLines = $subcon ? $production->fabricLinesWithData($subcon) : [];

        $signature = 'Digitally Signed: '.self::HO_SIGNER
            .' [UTC+07:00: '.now('Asia/Jakarta')->format('Y-m-d H:i:s').']';

        try {
            DB::connection('qms')->transaction(function () use ($row, $token, $data, $signature, $fabricLines) {
                // 1. Fabric lines (project-level, one row per fabric) — pushed from
                //    the locally-stored consumption + reconciliation (filled at
                //    cutting-report approval).
                if (Schema::connection('qms')->hasTable('packaging_project_fabric_lines')) {
                    // overconsumption is a newer column — only push it if the
                    // console-owned schema actually has it, so an older QMS DB
                    // still accepts the insert.
                    $hasOver = Schema::connection('qms')->hasColumn('packaging_project_fabric_lines', 'overconsumption');
                    foreach ($fabricLines as $f) {
                        $rowData = [
                            'project_id' => $row->project_id,
                            'label' => $f['label'], // fabric description + unit
                            'fabric_sent' => round((float) ($f['fabric_sent'] ?? 0), 2),
                            'consumption_plan' => round((float) ($f['consumption_plan'] ?? 0), 4),
                            'cutt_plan' => (int) ($f['cutt_plan'] ?? 0),
                            'actual_consumption' => round((float) ($f['actual_consumption'] ?? 0), 4),
                            'short_roll' => round((float) ($f['short_roll'] ?? 0), 2),
                            'sisa_kain' => round((float) ($f['sisa_kain'] ?? 0), 2),
                            'kepala_kain' => round((float) ($f['kepala_kain'] ?? 0), 2),
                            'return_kain' => round((float) ($f['retur_kain'] ?? 0), 2), // ours: retur_kain → QMS return_kain
                            'created_by' => self::HO_SIGNER,
                        ];
                        if ($hasOver) {
                            $rowData['overconsumption'] = round((float) ($f['overconsumption'] ?? 0), 4);
                        }
                        DB::connection('qms')->table('packaging_project_fabric_lines')->insert($rowData);
                    }
                } else {
                    Log::warning('QC HO: packaging_project_fabric_lines missing — fabric lines skipped', ['token' => $token]);
                }

                // 2. Deduction lines (session-level, optional, 0+).
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

                // 3. HO signature — last, and idempotent (guards a double submit).
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

        $subcon = $productionGroup
            ? DB::table('subcon_orders')->where('production_group', $productionGroup)->first()
            : null;

        $totalCut = $subcon
            ? (int) DB::table('subcon_cutting_reports')->where('order_id', $subcon->id)->sum('cutting_qty')
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

            Mail::send('emails.qc-ho-approval', [
                'url' => route('qc.ho-approve', ['token' => $token]),
                'sessionId' => $row->session_id ?? null,
                'projectId' => $row->project_id ?? null,
                'productionGroup' => $productionGroup,
                'orderNumber' => $subcon->order_number ?? null,
            ], function ($m) use ($recipients) {
                $m->to($recipients)->subject('HO Approval Required — Packaging Inspection');
            });

            Log::info('QC HO approval email sent', ['token' => $token, 'to' => $recipients]);
        } catch (\Throwable $e) {
            Log::error('QC HO approval email failed', ['token' => $token, 'error' => $e->getMessage()]);
        }
    }
}
