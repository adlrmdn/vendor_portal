<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     */
    public function index()
    {
        // Redirect based on user role
        $user = Auth::user();
        
        return match($user->role) {
            'admin', 'fabric_admin' => redirect()->route('admin.dashboard'),
            'fabric_vendor'         => redirect()->route('vendor.dashboard'),
            'subcon_admin'          => redirect()->route('subcon.admin.dashboard'),
            'subcon_vendor'         => redirect()->route('subcon.vendor.dashboard'),
            default                 => view('home'),
        };
    }
}