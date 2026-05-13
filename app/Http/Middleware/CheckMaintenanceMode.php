<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        // Cek apakah sakelar mode perbaikan sedang menyala
        if (Cache::get('maintenance_mode', false)) {
            
            // Biarkan halaman login dan logout tetap bisa diakses agar Admin bisa masuk
            if ($request->is('/') || $request->is('login') || $request->is('logout')) {
                return $next($request);
            }

            // Jika yang login adalah Super Admin (admin@dummy.com), biarkan lewat
            if (Auth::check() && strtolower(Auth::user()->email) === 'admin@dummy.com') {
                return $next($request);
            }

            // Jika ada request API (validasi SSO server-to-server), kembalikan error JSON
            if ($request->is('sso/validate') || $request->is('sso/me')) {
                return response()->json(['success' => false, 'message' => 'Sistem GUDEG JOGJA sedang dalam perbaikan rutin.'], 503);
            }

            // Lempar user lain ke halaman maintenance
            return response()->view('maintenance', [], 503);
        }

        return $next($request);
    }
}
