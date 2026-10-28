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
            // Whoever is clicking Send IS MD Production for this purpose
            // (confirmed live: every qc_ho_approver_email recipient already
            // has a subcon_admin account) — passing their name auto-approves
            // the HO stage in the same action, see send()'s docblock. Goes
            // straight to pending_director; emailHo() would have nothing to
            // do since there's no longer a separate HO approval step to
            // notify about.
            $report = $this->reports->send(
                $data['production_group'],
                $data['operation'],
                (float) ($data['additional_deduction'] ?? 0),
                $data['remarks'] ?? null,
                isset($data['sales_price']) ? (float) $data['sales_price'] : null,
                $this->actorLabel(),
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->emailDirector($report);

        return back()->with('success', "Official Report sent for {$data['operation']} — awaiting Director authorization.");
    }

    /**
     * In-app "Report" preview — admin-authenticated equivalent of the QC
     * workflow's qc.document route (QcApprovalController::document()):
     * rendered fresh from the report's current data on every open, same as
     * renderPdfDataUri()'s own docblock describes, so it works at any
     * status (pending_ho, pending_director, or completed) and never
     * mutates anything.
     */
    public function document(string $id)
    {
        $this->authorizeAdmin();

        $report = SubconOfficialReport::findOrFail($id);
        $dataUri = $this->reports->renderPdfDataUri($report);

        if ($dataUri === null) {
            return back()->with('error', 'Could not render the Official Report PDF.');
        }

        $bytes = base64_decode(substr($dataUri, strpos($dataUri, ',') + 1));
        $name = str_replace(['/', '\\'], '-', $report->production_group.'-'.$report->operation);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="official-report-'.$name.'.pdf"',
        ]);
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
            'style' => \App\Models\SubconOrder::cmtOnly()->where('production_group', $model->production_group)
                ->value('title'),
            'yield' => $this->reports->yieldMatrix($model->production_group, $model->operation),
            'submitUrl' => url(URL::signedRoute('subcon.official-report.ho-approve.submit', ['report' => $model->id], absolute: false)),
            // Not null for the common case — these links are opened by an
            // already-logged-in subcon_admin, either from the Report
            // Validation tab or because they're logged into the portal in
            // the same browser the email landed in (every HO/Director
            // approver has a portal account, see send()'s docblock). Only
            // a true cold, logged-out email open has no session to read,
            // so the blade still carries a manual-name fallback for that.
            'autoApprover' => $this->optionalActorLabel(),
        ]);
    }

    public function hoApproveSubmit(Request $request, string $report)
    {
        $model = SubconOfficialReport::findOrFail($report);
        $data = $request->validate([
            'additional_deduction' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string|max:2000',
            'approver' => 'nullable|string|max:255',
        ]);

        // Trust the session's email over a free-typed name whenever one
        // exists — the manual field is only a fallback for a logged-out
        // email open (see hoApproveSigned()'s docblock).
        $approver = $this->optionalActorLabel() ?? ($data['approver'] ?? null);
        if (! $approver) {
            return view('approvals.result', ['success' => false, 'message' => 'Please enter your name.']);
        }

        try {
            $this->reports->applyHoApproval($model, $approver, isset($data['additional_deduction']) ? (float) $data['additional_deduction'] : null, $data['remarks'] ?? null);
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

        $approver = $this->actorLabel();
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
            'style' => \App\Models\SubconOrder::cmtOnly()->where('production_group', $model->production_group)
                ->value('title'),
            'yield' => $this->reports->yieldMatrix($model->production_group, $model->operation),
            'submitUrl' => url(URL::signedRoute('subcon.official-report.director-approve.submit', ['report' => $model->id], absolute: false)),
            'declineUrl' => url(URL::signedRoute('subcon.official-report.director-decline', ['report' => $model->id], absolute: false)),
            'autoApprover' => $this->optionalActorLabel(),
        ]);
    }

    public function directorApproveSubmit(Request $request, string $report)
    {
        $model = SubconOfficialReport::findOrFail($report);
        $data = $request->validate(['approver' => 'nullable|string|max:255']);

        $approver = $this->optionalActorLabel() ?? ($data['approver'] ?? null);
        if (! $approver) {
            return view('approvals.result', ['success' => false, 'message' => 'Please enter your name.']);
        }

        try {
            $this->reports->applyDirectorApproval($model, $approver);
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
        $approver = $this->actorLabel();

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

    /**
     * Quiet undo of a send — open to any subcon admin (not Director-gated,
     * since it's pulling a report BACK to Report Validation, not acting
     * as the Director). Mirrors SubconAdminController::
     * recallToReportValidation()'s QC-session button.
     */
    public function recall(Request $request, string $id)
    {
        $this->authorizeAdmin();
        $model = SubconOfficialReport::findOrFail($id);

        try {
            $this->reports->recallToHo($model, $this->actorLabel());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $model->operation.' recalled — back in Report Validation.');
    }

    // --- Shared helpers ---

    /**
     * "Name <email>" — same identity format as
     * QcApprovalController::actorLabel(), so an in-app Official Report
     * signature reads the same way as a QC/HO/Director one everywhere it's
     * displayed (Report Validation/Director Approvals tabs, the PDF).
     */
    private function actorLabel(): string
    {
        $user = Auth::user();
        $email = trim((string) $user->email);
        $name = trim((string) ($user->name ?? ''));

        if ($name === '') {
            return $email;
        }

        return $email === '' ? $name : $name.' <'.strtolower($email).'>';
    }

    /** actorLabel(), but null when there's no logged-in session to read. */
    private function optionalActorLabel(): ?string
    {
        return Auth::check() ? $this->actorLabel() : null;
    }

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
