<?php

namespace App\Http\Controllers;

use App\Jobs\SyncSubconReportToD365;
use App\Mail\SubconStageStatusMailable;
use App\Models\SubconOrder;
use App\Models\User;
use App\Notifications\SubconStageDecision;
use App\Services\SubconConsumptionService;
use App\Services\SubconFabricLinePublisher;
use App\Services\SubconProductionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

class SubconApprovalController extends Controller
{
    /**
     * Split a Setting value that may hold one or many comma/semicolon-separated
     * addresses (e.g. "a@x.com, b@x.com;c@x.com") into a clean list of valid
     * emails. Returns [] when nothing usable — callers must skip sending.
     *
     * @return array<int, string>
     */
    public static function parseRecipients(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $emails = preg_split('/[,;]+/', $raw) ?: [];
        $emails = array_filter(
            array_map('trim', $emails),
            fn ($e) => $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)
        );

        return array_values(array_unique($emails));
    }

    /**
     * Resolve where vendor-facing status emails go: the vendor's own contact
     * email, then any of its portal users. Returns [] when the vendor has no
     * usable address — notifications are optional, so callers skip sending.
     *
     * @return array<int, string>
     */
    public static function vendorRecipient(SubconOrder $order): array
    {
        $order->loadMissing('vendor');
        if ($order->vendor) {
            $contactInfo = $order->vendor->contact_info;
            if (! empty($contactInfo['email'])) {
                return self::parseRecipients($contactInfo['email']);
            }
            if (! empty($order->vendor->email)) {
                return self::parseRecipients($order->vendor->email);
            }
        }

        // Fallback to the vendor's portal users' emails.
        $userEmails = User::where('vendor_id', $order->vendor_id)
            ->where('role', 'subcon_vendor')
            ->pluck('email')
            ->all();

        return self::parseRecipients(implode(',', $userEmails));
    }

    /**
     * Resolve where approval-request emails go.
     *
     * @return array<int, string>
     */
    public static function approverRecipient(): array
    {
        return self::parseRecipients(\App\Models\Setting::getValue('subcon_approver_email', ''));
    }

    /** @return array<int, string> */
    public static function cuttingApproverRecipient(): array
    {
        return self::parseRecipients(\App\Models\Setting::getValue('subcon_cutting_approver_email', ''));
    }

    /** @return array<int, string> */
    public static function gramasiApproverRecipient(): array
    {
        return self::parseRecipients(\App\Models\Setting::getValue('subcon_gramasi_approver_email', ''));
    }

    /** @return array<int, string> */
    public static function labelGeneratorRecipient(): array
    {
        return self::parseRecipients(\App\Models\Setting::getValue('subcon_label_generator_email', ''));
    }

    // --- Signed-link entry points (from approval email, no login) ---------

    public function approveSigned(string $order, string $gate, SubconProductionService $production)
    {
        $model = SubconOrder::with('vendor')->findOrFail($order);

        // Cutting is now "calculate + approve": the approver enters fabric
        // consumption before approving, so the signed link opens a no-login form
        // (mirroring the old HO consumption screen) instead of one-click approving.
        if ($gate === 'cutting') {
            if ($model->workflow_stage !== SubconOrder::STAGE_CUTTING_REVIEW) {
                return view('approvals.result', [
                    'success' => false,
                    'message' => 'The cutting report for '.$model->order_number.' is no longer awaiting approval (current stage: '.$model->stageLabel().').',
                ]);
            }

            $fabricLines = [];
            try {
                $fabricLines = $production->fabricLinesWithData($model);
            } catch (\Throwable $e) {
                report($e);
            }

            // Yield / per-size production detail — best-effort (VSM may be down).
            $productionGroups = [];
            try {
                $productionGroups = $production->forPo($model->order_number);
            } catch (\Throwable $e) {
                report($e);
            }
            $cuttingReports = \App\Models\SubconCuttingReport::where('order_id', $model->id)->get()->keyBy('prod_id');

            return view('subcon.cutting-approval-form', [
                'order' => $model,
                'fabricLines' => $fabricLines,
                'productionGroups' => $productionGroups,
                'cuttingReports' => $cuttingReports,
                'totalCut' => $production->totalCutForOrder($model),
                'submitUrl' => url(URL::signedRoute('subcon.approve.cutting.submit', ['order' => $model->id], absolute: false)),
                'declineUrl' => url(URL::signedRoute('subcon.decline', ['order' => $model->id, 'gate' => 'cutting'], absolute: false)),
            ]);
        }

        $result = $this->doApprove($model, $gate, 'Email approval', 'email');

        return view('approvals.result', ['success' => $result['ok'], 'message' => $result['message']]);
    }

    /**
     * No-login submit of the cutting approval form: persists the fabric
     * consumption AND approves the cutting gate as one atomic action.
     */
    public function approveCuttingSubmit(Request $request, string $order)
    {
        $model = SubconOrder::with('vendor')->findOrFail($order);

        $data = $request->validate([
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

        $result = $this->applyCuttingApproval($model, $data['fabrics'] ?? [], 'Email approval', 'email');

        return view('approvals.result', ['success' => $result['ok'], 'message' => $result['message']]);
    }

    /**
     * No-login decline link from the approval email: shows a confirm page
     * asking for a reason (mirrors the QC console's director/HO decline
     * flow), rather than declining immediately — so scanners prefetching
     * the emailed GET link can't trigger a rejection, and the vendor gets
     * something more useful than a bare "declined" notice.
     */
    public function declineSigned(string $order, string $gate)
    {
        $model = SubconOrder::with('vendor')->findOrFail($order);

        if (! in_array($gate, ['cutting', 'gramasi'], true)) {
            return view('approvals.result', [
                'success' => false,
                'message' => 'Unknown approval stage.',
            ]);
        }

        $expected = $gate === 'cutting' ? SubconOrder::STAGE_CUTTING_REVIEW : SubconOrder::STAGE_GRAMASI_REVIEW;
        if ($model->workflow_stage !== $expected) {
            return view('approvals.result', [
                'success' => false,
                'message' => 'This request is no longer awaiting approval (current stage: '.$model->stageLabel().').',
            ]);
        }

        return view('subcon.decline-confirm', [
            'order' => $model,
            'gate' => $gate,
            'submitUrl' => url(URL::signedRoute('subcon.decline.submit', ['order' => $model->id, 'gate' => $gate], absolute: false)),
        ]);
    }

    /** No-login submit of the decline-confirm form — records the reason and declines. */
    public function declineSubmit(Request $request, string $order, string $gate)
    {
        $model = SubconOrder::with('vendor')->findOrFail($order);
        $data = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $result = $this->doDecline($model, $gate, 'Email approval', 'email', $data['reason']);

        return view('approvals.result', ['success' => $result['ok'], 'message' => $result['message']]);
    }

    public function generateLabelsSigned(string $order)
    {
        $model = SubconOrder::with('vendor')->findOrFail($order);

        if ($model->workflow_stage !== SubconOrder::STAGE_WAITING_DISTRIBUTION) {
            if (in_array($model->workflow_stage, [SubconOrder::STAGE_LABELS, SubconOrder::STAGE_COMPLETED], true)) {
                return view('approvals.result', [
                    'success' => true,
                    'message' => 'Packing labels have already been generated for work order '.$model->order_number.'. You can print them in the portal.',
                ]);
            }

            return view('approvals.result', [
                'success' => false,
                'message' => 'This work order is not ready for label generation (current stage: '.$model->stageLabel().').',
            ]);
        }

        // Don't stack a second run while one is already in flight.
        if ($model->isGeneratingLabels()) {
            return view('approvals.result', [
                'success' => true,
                'message' => 'Label generation is already running for work order '.$model->order_number.'. It will unlock printing in the portal once finished.',
            ]);
        }

        // Resolution + DTT call (up to 30s) run off the request cycle so the
        // signed link returns immediately; the worker advances the order to
        // "labels", which unlocks printing in the portal.
        $model->markLabelGenStarted();
        \App\Jobs\GenerateSubconLabels::dispatch($model->id);

        return view('approvals.result', [
            'success' => true,
            'message' => 'Label generation has started for work order '.$model->order_number.'. It is processing in the background — printing will unlock in the portal once the labels are ready.',
        ]);
    }

    // --- In-app entry points (subcon admin) -------------------------------

    public function approveInApp(Request $request, string $id)
    {
        $this->authorizeAdmin();
        $gate = $request->input('gate');
        $model = SubconOrder::with('vendor')->findOrFail($id);
        $approver = Auth::user()->name ?? Auth::user()->email;

        // Cutting Approve carries the consumption entered on the same panel.
        if ($gate === 'cutting') {
            $data = $request->validate([
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
            $result = $this->applyCuttingApproval($model, $data['fabrics'] ?? [], $approver, 'in_app');

            return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
        }

        $result = $this->doApprove($model, $gate, $approver, 'in_app');

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function declineInApp(Request $request, string $id)
    {
        $this->authorizeAdmin();
        $gate = $request->input('gate');
        $data = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);
        $model = SubconOrder::with('vendor')->findOrFail($id);
        $result = $this->doDecline($model, $gate, Auth::user()->name ?? Auth::user()->email, 'in_app', $data['reason']);

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    private function authorizeAdmin(): void
    {
        if (! in_array(Auth::user()->role ?? '', ['admin', 'subcon_admin'], true)) {
            abort(403, 'Unauthorized access.');
        }
    }

    // --- Shared transition logic ------------------------------------------

    /**
     * Cutting gate = calculate + approve. Persists the per-fabric consumption
     * (deriving cutt_plan / actual_consumption) and advances the stage in ONE
     * transaction, then fires the background D365 sync + vendor notification.
     * Used by both the in-app admin panel and the no-login signed form.
     *
     * @param  array<int, array<string, mixed>>  $fabrics
     * @return array{ok: bool, message: string}
     */
    private function applyCuttingApproval(SubconOrder $order, array $fabrics, string $approver, string $source = 'in_app'): array
    {
        if ($order->workflow_stage !== SubconOrder::STAGE_CUTTING_REVIEW) {
            return ['ok' => false, 'message' => 'This request is no longer awaiting approval (current stage: '.$order->stageLabel().').'];
        }

        // A PARTIAL cutting report goes back to the entry stage on approval so
        // the vendor can keep submitting the remaining quantities; only a final
        // (non-partial) report advances the order to gramasi.
        $isPartial = (bool) $order->cutting_partial;

        DB::transaction(function () use ($order, $fabrics, $approver, $isPartial) {
            app(SubconConsumptionService::class)->persist($order, $fabrics);
            $order->workflow_stage = $isPartial ? SubconOrder::STAGE_CUTTING : SubconOrder::STAGE_GRAMASI;
            $order->cutting_approved_at = now();
            $order->cutting_approved_by = $approver;
            $order->save();
        });

        // Push the approved cutting figures to D365 in the background (never blocks).
        SyncSubconReportToD365::dispatch($order->id, 'cutting');

        // Publish the consumption to the QMS fabric-lines table (keyed by
        // production_group) so the QC Console can PULL it even before its
        // packaging project exists. Staged with a NULL project_id — the console
        // adopts it later. Best-effort + guarded; never blocks the approval.
        app(SubconFabricLinePublisher::class)->publish($order);
        app(\App\Services\SubconRemarksPublisher::class)->publish($order);

        $this->notifyVendor($order, 'cutting', 'approved');

        $message = $isPartial
            ? 'Partial cutting report approved for '.$order->order_number.'. Consumption saved. The order stays at cutting-report entry so the vendor can submit the remaining quantities. Values are syncing to D365 in the background.'
            : 'Cutting report approved for '.$order->order_number.'. Consumption saved. The vendor may now enter gramasi & blister capacity. Values are syncing to D365 in the background.';
        $this->logDecision($order, 'cutting', 'approved', $approver, $source, $message);

        return ['ok' => true, 'message' => $message];
    }

    private function doApprove(SubconOrder $order, ?string $gate, string $approver, string $source = 'in_app'): array
    {
        if (! in_array($gate, ['cutting', 'gramasi'], true)) {
            return ['ok' => false, 'message' => 'Unknown approval stage.'];
        }

        $expected = $gate === 'cutting' ? SubconOrder::STAGE_CUTTING_REVIEW : SubconOrder::STAGE_GRAMASI_REVIEW;
        if ($order->workflow_stage !== $expected) {
            return ['ok' => false, 'message' => 'This request is no longer awaiting approval (current stage: '.$order->stageLabel().').'];
        }

        if ($gate === 'cutting') {
            // Partial reports return to entry on approval (see applyCuttingApproval).
            $order->workflow_stage = $order->cutting_partial ? SubconOrder::STAGE_CUTTING : SubconOrder::STAGE_GRAMASI;
            $order->cutting_approved_at = now();
            $order->cutting_approved_by = $approver;
            $order->save();
        } else {
            $order->workflow_stage = SubconOrder::STAGE_WAITING_DISTRIBUTION;
            $order->gramasi_approved_at = now();
            $order->gramasi_approved_by = $approver;
            $order->save();
        }

        // Approval is now committed locally. Push the approved values to D365 in
        // the background — the "original mechanism" — so this never blocks the
        // Approve action. Same shape for both gates (see SyncSubconReportToD365).
        SyncSubconReportToD365::dispatch($order->id, $gate);

        $this->notifyVendor($order, $gate, 'approved');

        $label = $gate === 'gramasi' ? 'Gramasi & blister capacity' : 'Cutting report';
        $next = $gate === 'gramasi'
            ? ' An email has been sent to the vendor to generate packing labels.'
            : ($order->cutting_partial
                ? ' Approved as partial — the vendor can submit the remaining quantities.'
                : ' The vendor may now enter gramasi & blister capacity.');

        $message = $label.' approved for '.$order->order_number.'.'.$next.' Values are syncing to D365 in the background.';
        $this->logDecision($order, $gate, 'approved', $approver, $source, $message);

        return ['ok' => true, 'message' => $message];
    }

    private function doDecline(SubconOrder $order, ?string $gate, string $approver, string $source = 'in_app', ?string $reason = null): array
    {
        if (! in_array($gate, ['cutting', 'gramasi'], true)) {
            return ['ok' => false, 'message' => 'Unknown approval stage.'];
        }

        $expected = $gate === 'cutting' ? SubconOrder::STAGE_CUTTING_REVIEW : SubconOrder::STAGE_GRAMASI_REVIEW;
        if ($order->workflow_stage !== $expected) {
            return ['ok' => false, 'message' => 'This request is no longer awaiting approval (current stage: '.$order->stageLabel().').'];
        }

        $reason = trim((string) $reason);

        // Send back to the corresponding entry stage for correction & resubmit.
        // The reason is kept on the order until the vendor resubmits this same
        // gate (see SubconVendorController::submitCuttingReport/submitGramasi),
        // which clears it.
        $order->workflow_stage = $gate === 'cutting' ? SubconOrder::STAGE_CUTTING : SubconOrder::STAGE_GRAMASI;
        $order->reject_gate = $gate;
        $order->reject_reason = $reason !== '' ? $reason : null;
        $order->rejected_at = now();
        $order->save();

        $this->notifyVendor($order, $gate, 'declined');

        $label = $gate === 'gramasi' ? 'Gramasi & blister capacity' : 'Cutting report';

        $message = $label.' for '.$order->order_number.' was returned to the vendor for changes.'
            .($reason !== '' ? ' Reason: '.$reason : '');
        $this->logDecision($order, $gate, 'declined', $approver, $source, $message);

        return ['ok' => true, 'message' => $message];
    }

    /**
     * Append one immutable audit row for a cutting/gramasi decision. Best-effort:
     * a logging failure must never break an approval that already committed.
     */
    private function logDecision(SubconOrder $order, string $gate, string $decision, string $actor, string $source, ?string $note = null): void
    {
        \App\Models\SubconApprovalLog::record([
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'vendor_name' => $order->vendor?->name,
            'gate' => $gate,
            'decision' => $decision,
            'actor' => $actor,
            'source' => $source,
            'note' => $note,
        ]);
    }

    private function notifyVendor(SubconOrder $order, string $gate, string $outcome): void
    {
        // Email — best-effort. Notifications are optional: skip silently when no
        // recipient is configured/resolvable rather than failing.
        try {
            $recipients = ($gate === 'gramasi' && $outcome === 'approved')
                ? self::labelGeneratorRecipient()
                : self::vendorRecipient($order);

            if (! empty($recipients)) {
                Mail::to($recipients)->send(new SubconStageStatusMailable($order, $gate, $outcome));
            }
        } catch (\Throwable $e) {
            Log::error('Subcon stage status email failed: '.$e->getMessage());
        }

        // In-app notification to the vendor's portal users — best-effort.
        try {
            $vendorUsers = User::where('vendor_id', $order->vendor_id)
                ->where('role', 'subcon_vendor')
                ->get();
            if ($vendorUsers->isNotEmpty()) {
                Notification::send($vendorUsers, new SubconStageDecision($order, $gate, $outcome));
            }
        } catch (\Throwable $e) {
            Log::error('Subcon stage decision notification failed: '.$e->getMessage());
        }
    }
}
