<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Pastikan user sudah login DAN rolenya adalah 'admin'
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            return abort(403, 'AKSES DITOLAK: Halaman ini khusus Administrator.');
        }

        return $next($request);
    }
}
