<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PcAdminController;
use App\Models\PcAuditLog;
use App\Models\User;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    /**
     * Where to redirect users after login. PC has no dedicated role or
     * account-creation flow — it's absorbed into the existing fabric admin
     * area, so an admin/fabric_admin login just goes where it always did.
     */
    protected function authenticated(Request $request, $user)
    {
        if (PcAdminController::isPcApproverUser($user)) {
            PcAuditLog::record([
                'actor' => $user->email,
                'actor_role' => $user->role,
                'event_type' => 'login_success',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return redirect()->to($this->redirectForRole($user->role));
    }

    /**
     * Logs a failed attempt only when the submitted email belongs to a
     * configured PC Approver — keeps this audit table from being flooded
     * by every fabric/subcon login typo that has nothing to do with PC.
     */
    protected function sendFailedLoginResponse(Request $request)
    {
        $email = $request->input($this->username());
        $user = $email ? User::where('email', $email)->first() : null;

        if ($user && PcAdminController::isPcApproverUser($user)) {
            PcAuditLog::record([
                'actor' => $user->email,
                'actor_role' => $user->role,
                'event_type' => 'login_failed',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return parent::sendFailedLoginResponse($request);
    }

    private function redirectForRole(string $role): string
    {
        return match ($role) {
            'admin', 'fabric_admin' => route('admin.dashboard'),
            'fabric_vendor' => route('vendor.dashboard'),
            'subcon_admin' => route('subcon.admin.dashboard'),
            'subcon_vendor' => route('subcon.vendor.dashboard'),
            default => url('/home'),
        };
    }

    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }
}
