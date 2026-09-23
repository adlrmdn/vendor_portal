<?php

namespace App\Http\Controllers;

use App\Mail\SubconOfficialReportApprovalMailable;
use App\Mail\SubconOfficialReportDeclinedMailable;
use App\Models\Setting;
use App\Models\SubconOfficialReport;
use App\Services\SubconOfficialReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Official Report (Berita Acara) — send + HO/Director approve/decline, both
 * signed-email and in-app entry points. See
 * docs/superpowers/specs/2026-09-22-subcon-official-report-design.md.
 */
class SubconOfficialReportController extends Controller
{
    public function __construct(private readonly SubconOfficialReportService $reports) {}

    // --- Send (admin, in-app only — no email entry point for the first send) ---

    public function send(Request $request)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'production_group' => 'required|string',
            'operation' => 'required|string',
            'additional_deduction' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string|max:2000',
            'sales_price' => 'nullable|numeric|min:0',
        ]);

        try {
            $report = $this->reports->send(
                $data['production_group'],
                $data['operation'],
                (float) ($data['additional_deduction'] ?? 0),
                $data['remarks'] ?? null,
                isset($data['sales_price']) ? (float) $data['sales_price'] : null,
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->emailHo($report);

        return back()->with('success', "Official Report sent for {$data['operation']} — awaiting MD Production approval.");
    }

    // --- HO approve (signed + in-app) ---

    public function hoApproveSigned(string $report)
    {
        $model = SubconOfficialReport::findOrFail($report);

        if (! $model->isPendingHo()) {
            return view('approvals.result', ['success' => false, 'message' => 'This report is no longer awaiting MD Production approval.']);
        }

        return view('subcon.official-report-ho-confirm', [
            'report' => $model,
            'submitUrl' => url(URL::signedRoute('subcon.official-report.ho-approve.submit', ['report' => $model->id], absolute: false)),
        ]);
    }

    public function hoApproveSubmit(Request $request, string $report)
    {
        $model = SubconOfficialReport::findOrFail($report);
        $data = $request->validate([
            'additional_deduction' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string|max:2000',
            'approver' => 'required|string|max:255',
        ]);

        try {
            $this->reports->applyHoApproval($model, $data['approver'], isset($data['additional_deduction']) ? (float) $data['additional_deduction'] : null, $data['remarks'] ?? null);
        } catch (\RuntimeException $e) {
            return view('approvals.result', ['success' => false, 'message' => $e->getMessage()]);
        }
        $this->emailDirector($model->fresh());

        return view('approvals.result', ['success' => true, 'message' => 'Approved. Sent to the Director for authorization.']);
    }

    public function hoApproveInApp(Request $request, string $id)
    {
        $this->authorizeAdmin();
        $model = SubconOfficialReport::findOrFail($id);
        $data = $request->validate([
            'additional_deduction' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string|max:2000',
        ]);

        $approver = Auth::user()->name ?? Auth::user()->email;
        try {
            $this->reports->applyHoApproval($model, $approver, isset($data['additional_deduction']) ? (float) $data['additional_deduction'] : null, $data['remarks'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        $this->emailDirector($model->fresh());

        return back()->with('success', 'Approved — sent to the Director.');
    }

    // --- Director approve (signed + in-app) ---

    public function directorApproveSigned(string $report)
    {
        $model = SubconOfficialReport::findOrFail($report);

        if (! $model->isPendingDirector()) {
            return view('approvals.result', ['success' => false, 'message' => 'This report is no longer awaiting Director authorization.']);
        }

        return view('subcon.official-report-director-confirm', [
            'report' => $model,
            'submitUrl' => url(URL::signedRoute('subcon.official-report.director-approve.submit', ['report' => $model->id], absolute: false)),
            'declineUrl' => url(URL::signedRoute('subcon.official-report.director-decline', ['report' => $model->id], absolute: false)),
        ]);
    }

    public function directorApproveSubmit(Request $request, string $report)
    {
        $model = SubconOfficialReport::findOrFail($report);
        $data = $request->validate(['approver' => 'required|string|max:255']);

        try {
            $this->reports->applyDirectorApproval($model, $data['approver']);
        } catch (\RuntimeException $e) {
            return view('approvals.result', ['success' => false, 'message' => $e->getMessage()]);
        }

        return view('approvals.result', ['success' => true, 'message' => 'Authorized. Invoice/Deduction have been queued.']);
    }

    public function directorApproveInApp(Request $request, string $id)
    {
        $this->authorizeAdmin();
        $this->authorizeDirector();
        $model = SubconOfficialReport::findOrFail($id);
        $approver = Auth::user()->name ?? Auth::user()->email;

        try {
            $this->reports->applyDirectorApproval($model, $approver);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Authorized — Invoice/Deduction queued.');
    }

    // --- Director decline (signed + in-app) ---

    public function directorDeclineSigned(string $report)
    {
        $model = SubconOfficialReport::findOrFail($report);

        if (! $model->isPendingDirector()) {
            return view('approvals.result', ['success' => false, 'message' => 'This report is no longer awaiting Director authorization.']);
        }

        return view('subcon.official-report-decline-confirm', [
            'report' => $model,
            'submitUrl' => url(URL::signedRoute('subcon.official-report.director-decline.submit', ['report' => $model->id], absolute: false)),
        ]);
    }

    public function directorDeclineSubmit(Request $request, string $report)
    {
        $model = SubconOfficialReport::findOrFail($report);
        $data = $request->validate(['reason' => 'required|string|max:1000']);

        try {
            $this->reports->applyDirectorDecline($model, $data['reason']);
        } catch (\RuntimeException $e) {
            return view('approvals.result', ['success' => false, 'message' => $e->getMessage()]);
        }
        $this->emailHoDeclined($model->fresh());

        return view('approvals.result', ['success' => true, 'message' => 'Declined. MD Production has been notified to revise and resend.']);
    }

    public function directorDeclineInApp(Request $request, string $id)
    {
        $this->authorizeAdmin();
        $this->authorizeDirector();
        $model = SubconOfficialReport::findOrFail($id);
        $data = $request->validate(['reason' => 'required|string|max:1000']);

        try {
            $this->reports->applyDirectorDecline($model, $data['reason']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        $this->emailHoDeclined($model->fresh());

        return back()->with('success', 'Declined — MD Production notified.');
    }

    // --- Shared helpers ---

    private function authorizeAdmin(): void
    {
        if (! in_array(Auth::user()->role ?? '', ['admin', 'subcon_admin'], true)) {
            abort(403, 'Unauthorized access.');
        }
    }

    /**
     * Route-level defense-in-depth matching SubconAdminController's UI-level
     * gate: the Director Approvals tab only shows Authorize/Reject actions to
     * a configured Director (SubconAdminController::isDirectorUser()), but
     * without this check any subcon admin could still POST directly to these
     * in-app routes and sign as Director. See final-review Fix 4.
     */
    private function authorizeDirector(): void
    {
        if (! SubconAdminController::isDirectorUser(Auth::user())) {
            abort(403, 'Only the Director can authorize/reject this report.');
        }
    }

    private function emailHo(SubconOfficialReport $report): void
    {
        $to = Setting::getValue('qc_ho_approver_email');
        if (! $to) {
            Log::warning("Official Report {$report->id}: no qc_ho_approver_email configured, skipping HO email.");

            return;
        }
        try {
            Mail::to($to)->send(new SubconOfficialReportApprovalMailable($report, 'ho'));
        } catch (\Throwable $e) {
            Log::error("Official Report {$report->id}: HO email failed: ".$e->getMessage());
        }
    }

    private function emailDirector(SubconOfficialReport $report): void
    {
        $to = Setting::getValue('qc_director_approver_email');
        if (! $to) {
            Log::warning("Official Report {$report->id}: no qc_director_approver_email configured, skipping Director email.");

            return;
        }
        try {
            Mail::to($to)->send(new SubconOfficialReportApprovalMailable($report, 'director'));
        } catch (\Throwable $e) {
            Log::error("Official Report {$report->id}: Director email failed: ".$e->getMessage());
        }
    }

    private function emailHoDeclined(SubconOfficialReport $report): void
    {
        $to = Setting::getValue('qc_ho_approver_email');
        if (! $to) {
            return;
        }
        try {
            Mail::to($to)->send(new SubconOfficialReportDeclinedMailable($report));
        } catch (\Throwable $e) {
            Log::error("Official Report {$report->id}: decline-notification email failed: ".$e->getMessage());
        }
    }
}
