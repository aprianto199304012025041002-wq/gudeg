<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Events\AuditLogEvent;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user && isset($user->is_active) && !$user->is_active) {
            return back()->withErrors(['email' => 'Akun Anda telah dinonaktifkan. Silakan hubungi Administrator.']);
        }

        if (!Auth::attempt(['email' => $request->email, 'password' => $request->password], $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Email atau password salah.']);
        }

        $request->session()->regenerate();
        AuditLogEvent::dispatch('user', ['user' => Auth::user(), 'activity' => 'Login', 'ip' => $request->ip()]);

        return redirect()->intended('/dashboard');
    }

    public function register(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if (strtolower($request->email) === 'admin@dummy.com') {
            return back()->withErrors(['email' => 'Email ini dicadangkan dan tidak dapat didaftarkan.']);
        }

        $user = User::create([
            'name'                => $request->name,
            'email'               => $request->email,
            'password'            => Hash::make($request->password),
            'role'                => 'guest',
            'is_active'           => true,
            'password_changed_at' => now(), // Catat waktu ganti password
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        AuditLogEvent::dispatch('user', ['user' => $user, 'activity' => 'Register Akun Baru (Guest)', 'ip' => $request->ip()]);

        return redirect('/dashboard');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|current_password',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        $user = Auth::user();

        if (strtolower($user->email) === 'admin@dummy.com' && Auth::id() !== $user->id) {
            return abort(403, 'Akses ilegal terhadap akun utama.');
        }

        $user->password = Hash::make($request->password);
        $user->password_changed_at = now(); // Update timer 90 hari
        $user->save();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Auth::login($user);
        $request->session()->regenerate();

        AuditLogEvent::dispatch('user', ['user' => $user, 'activity' => 'Ubah Password Sendiri', 'ip' => $request->ip()]);

        return back()->with('success', 'Password berhasil diperbarui! Masa aktif diperpanjang 90 hari.');
    }

    public function destroySessions(Request $request)
    {
        $request->validate(['password' => 'required|current_password']);

        DB::table('sessions')
            ->where('user_id', Auth::id())
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        AuditLogEvent::dispatch('user', ['user' => Auth::user(), 'activity' => 'Logout dari Perangkat Lain', 'ip' => $request->ip()]);

        return back()->with('success', 'Berhasil logout dari semua perangkat lain.');
    }

    public function logout(Request $request)
    {
        AuditLogEvent::dispatch('user', ['user' => Auth::user(), 'activity' => 'Logout', 'ip' => $request->ip()]);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return redirect('/');
    }
}
