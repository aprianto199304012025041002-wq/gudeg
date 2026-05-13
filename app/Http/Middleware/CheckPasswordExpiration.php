<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckPasswordExpiration
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();
            $passwordChangedAt = $user->password_changed_at;
            
            // Jika belum pernah diset atau umur password sudah >= 90 hari
            if (!$passwordChangedAt || now()->diffInDays($passwordChangedAt) >= 90) {
                return redirect('/settings')->withErrors([
                    'password_expired' => 'Masa berlaku password Anda telah habis (90 hari). Sesuai standar ISMS, Anda WAJIB mengubah password sebelum dapat menggunakan aplikasi.'
                ]);
            }
        }

        return $next($request);
    }
}
