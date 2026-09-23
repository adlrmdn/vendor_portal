<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request: defaults vendors to Bahasa Indonesia ('id')
     * while keeping English ('en') standard for admins and system, respecting
     * any user selection stored in session('locale').
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasSession() && $request->session()->has('locale')) {
            $locale = $request->session()->get('locale');
            if (in_array($locale, ['en', 'id'], true)) {
                app()->setLocale($locale);

                return $next($request);
            }
        }

        $user = $request->user();
        if ($user && (
            (method_exists($user, 'isSubconVendor') && $user->isSubconVendor()) ||
            (method_exists($user, 'isFabricVendor') && $user->isFabricVendor()) ||
            $user->role === 'subcon_vendor' ||
            $user->role === 'fabric_vendor' ||
            $user->role === 'vendor'
        )) {
            app()->setLocale('id');
        } else {
            app()->setLocale('en');
        }

        return $next($request);
    }
}
