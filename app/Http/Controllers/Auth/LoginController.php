<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     */
    protected function authenticated(Request $request, $user)
    {
        return redirect()->to($this->redirectForRole($user->role));
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
