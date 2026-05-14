<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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

        // Buat payload berisi data user + timestamp
        $payload = [
            'id'        => Auth::user()->id,
            'name'      => Auth::user()->name,
            'email'     => Auth::user()->email,
            'role'      => Auth::user()->role,
            'timestamp' => now()->timestamp,
        ];

        // Enkripsi payload pakai shared secret
        $secret     = config('app.sso_secret', env('SSO_SECRET'));
        $json       = json_encode($payload);
        $encrypted  = base64_encode($json);
        $signature  = hash_hmac('sha256', $encrypted, $secret);
        $token      = $encrypted . '.' . $signature;

        AuditLogEvent::dispatch('user', [
            'user'     => Auth::user(),
            'activity' => 'Akses SSO ke ' . parse_url($redirectUrl, PHP_URL_HOST),
            'ip'       => $request->ip()
        ]);

        return redirect($redirectUrl . '?token=' . urlencode($token));
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
