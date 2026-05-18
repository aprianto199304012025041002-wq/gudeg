<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Events\AuditLogEvent;

class SSOController extends Controller
{
    public function authorizeSSO(Request $request)
    {
        if (Auth::user()->role === 'guest') {
            return abort(403, 'Akun Anda berstatus GUEST. Silakan hubungi Administrator untuk mendapatkan akses aplikasi.');
        }

        if (isset(Auth::user()->is_active) && !Auth::user()->is_active) {
            return abort(403, 'Akun Anda sedang dinonaktifkan.');
        }

        $redirectUrl = $request->query('redirect');
        if (!$redirectUrl) return abort(400, 'URL Redirect tidak disertakan.');

        // Cek apakah aplikasi terdaftar & mode token-nya
        $redirectDomain = parse_url($redirectUrl, PHP_URL_HOST);
        $app = DB::table('sso_applications')
            ->where(function ($q) use ($redirectUrl, $redirectDomain) {
                $q->where('redirect', $redirectUrl)
                  ->orWhere('redirect', 'like', '%' . $redirectDomain . '%');
            })
            ->first();

        // ========================================================
        // [BARU] GEMBOK AKSES: Cek Izin User ke Aplikasi Ini
        // ========================================================
        if ($app && Auth::user()->role !== 'admin') {
            $hasAccess = DB::table('user_applications')
                ->where('user_id', Auth::id())
                ->where('application_id', $app->id)
                ->exists();

            if (!$hasAccess) {
                // Catat aktivitas ilegal di Audit Log
                AuditLogEvent::dispatch('user', [
                    'user'     => Auth::user(),
                    'activity' => 'Akses DITOLAK (Tidak ada izin) ke ' . $redirectDomain,
                    'ip'       => $request->ip()
                ]);
                
                // Pantulkan kembali ke klien dengan pesan error
                return redirect($redirectUrl . '?error=access_denied');
            }
        }

        AuditLogEvent::dispatch('user', [
            'user'     => Auth::user(),
            'activity' => 'Akses SSO ke ' . $redirectDomain,
            'ip'       => $request->ip()
        ]);

        // Mode SIGNED TOKEN — untuk aplikasi yang tidak bisa server-to-server
        if ($app && $app->use_signed_token) {
            $payload = [
                'id'        => Auth::user()->id,
                'name'      => Auth::user()->name,
                'email'     => Auth::user()->email,
                'role'      => Auth::user()->role,
                'timestamp' => now()->timestamp,
            ];

            $secret    = env('SSO_SECRET');
            $encrypted = base64_encode(json_encode($payload));
            $signature = hash_hmac('sha256', $encrypted, $secret);
            $token     = $encrypted . '.' . $signature;

            return redirect($redirectUrl . '?token=' . urlencode($token));
        }

        // Mode CACHE TOKEN — default, untuk aplikasi yang bisa server-to-server
        $token = hash('sha256', Auth::id() . Auth::user()->email . config('app.key') . now()->timestamp);
        Cache::put('sso_token_' . $token, Auth::id(), now()->addMinutes(2));

        return redirect($redirectUrl . '?token=' . $token);
    }

    public function validateToken(Request $request)
    {
        $token = $request->query('token');
        if (!$token) return response()->json(['success' => false, 'message' => 'Token tidak ditemukan.'], 401);

        $userId = Cache::get('sso_token_' . $token);
        if (!$userId) return response()->json(['success' => false, 'message' => 'Token tidak valid atau kedaluwarsa.'], 401);

        $user = User::find($userId);
        Cache::forget('sso_token_' . $token);

        if ($user && isset($user->is_active) && !$user->is_active) {
            return response()->json(['success' => false, 'message' => 'Akun dinonaktifkan.'], 403);
        }

        return response()->json(['success' => true, 'user' => [
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'role'  => $user->role,
        ]]);
    }

    public function me()
    {
        return response()->json(['authenticated' => true, 'user' => [
            'id'    => Auth::user()->id,
            'name'  => Auth::user()->name,
            'email' => Auth::user()->email,
            'role'  => Auth::user()->role,
        ]]);
    }
}
