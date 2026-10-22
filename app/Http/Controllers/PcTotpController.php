<?php

namespace App\Http\Controllers;

use App\Models\PcAuditLog;
use App\Models\User;
use App\Services\TotpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PcTotpController extends Controller
{
    // PC is absorbed into the existing fabric admin area as a single tabbed
    // page (PcController::index()) — access is enforced by the same 'auth'
    // + 'role:admin,fabric_admin' middleware that already gates the rest of
    // /admin/* (routes/web.php). Every fabric_admin passes that gate, but
    // only an actual configured Approver (or root admin) may proceed past
    // ensureIsApprover() below: defense-in-depth, same shape as
    // authorizeAdmin()/isDirectorUser() elsewhere in this app. The QR/setup
    // secret generation itself happens in PcController::index() (so the
    // "My Authenticator Code" sub-tab has something to render); this
    // controller only confirms a code or resets someone else's.

    public function __construct(private TotpService $totp) {}

    private function ensureIsApprover(): void
    {
        $user = Auth::user();
        if ($user->isAdmin() || PcAdminController::isPcApproverUser($user)) {
            return;
        }

        abort(403, 'You are not a configured PC Approver.');
    }

    public function confirmSetup(Request $request)
    {
        $this->ensureIsApprover();

        $data = $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = Auth::user();

        if (! $user->totp_secret) {
            return redirect(route('admin.pc.data.index').'#approver-settings')->with('error', 'Generate a setup code first.');
        }

        if (! $this->totp->verifyTotpCode($user->totp_secret, $data['code'])) {
            return redirect(route('admin.pc.data.index').'#approver-settings')->with('error', 'That code did not match. Check your authenticator app and try again.');
        }

        $user->totp_confirmed_at = now();
        $user->save();

        PcAuditLog::record([
            'actor' => $user->email,
            'actor_role' => $user->role,
            'event_type' => 'totp_setup',
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'entity_label' => $user->name,
        ]);

        return redirect(route('admin.pc.data.index').'#approver-settings')->with('success', 'Authenticator code confirmed.');
    }

    /** Resets someone ELSE's authenticator code — root admin only, not any configured Approver. */
    public function disable(string $userId)
    {
        $actor = Auth::user();
        if (! $actor->isAdmin()) {
            abort(403);
        }

        $user = User::findOrFail($userId);

        $user->totp_secret = null;
        $user->totp_confirmed_at = null;
        $user->save();

        PcAuditLog::record([
            'actor' => $actor->email,
            'actor_role' => $actor->role,
            'event_type' => 'totp_disable',
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'entity_label' => $user->name,
            'note' => "Reset by {$actor->email}",
        ]);

        return redirect(route('admin.pc.data.index').'#approver-settings')->with('success', "Authenticator code reset for \"{$user->name}\" — they'll need to set it up again.");
    }
}
