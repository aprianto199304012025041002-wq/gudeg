<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        $users = User::when($request->search, function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%');
            })
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        // Log admin — 1 bulan terakhir, 10 per halaman
        $logs = DB::table('admin_logs')
            ->where('created_at', '>=', now()->subMonth())
            ->orderBy('created_at', 'desc')
            ->paginate(10, ['*'], 'log_page')
            ->withQueryString();

        // Log pengguna — 1 bulan terakhir, 10 per halaman
        $userLogs = DB::table('user_logs')
            ->where('created_at', '>=', now()->subMonth())
            ->orderBy('created_at', 'desc')
            ->paginate(10, ['*'], 'ulog_page')
            ->withQueryString();

        // Auto-delete log lebih dari 1 bulan
        DB::table('admin_logs')
            ->where('created_at', '<', now()->subMonth())
            ->delete();

        DB::table('user_logs')
            ->where('created_at', '<', now()->subMonth())
            ->delete();

        return view('settings', compact('users', 'logs', 'userLogs'));
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|min:8|confirmed',
        ]);

        if (!Hash::check($request->current_password, Auth::user()->password)) {
            return back()->withErrors([
                'current_password' => 'Password lama salah.'
            ]);
        }

        Auth::user()->update([
            'password' => Hash::make($request->password),
        ]);

        // Catat ke user_logs
        DB::table('user_logs')->insert([
            'user_id'    => Auth::id(),
            'user_name'  => Auth::user()->name,
            'user_email' => Auth::user()->email,
            'activity'   => 'Reset Password',
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        return back()->with('success', 'Password berhasil diubah.');
    }

    public function updateRole(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role'    => 'required|in:user,admin',
        ]);

        $target = User::findOrFail($request->user_id);
        $target->update(['role' => $request->role]);

        // Catat ke admin_logs
        DB::table('admin_logs')->insert([
            'admin_id'    => Auth::id(),
            'admin_name'  => Auth::user()->name,
            'action'      => 'Ubah Role ke ' . strtoupper($request->role),
            'target_user' => $target->email,
            'created_at'  => now(),
        ]);

        return back()->with('success_user', 'Role berhasil diubah.');
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'user_id'      => 'required|exists:users,id',
            'new_password' => 'required|min:8',
        ]);

        $target = User::findOrFail($request->user_id);
        $target->update([
            'password' => Hash::make($request->new_password),
        ]);

        // Catat ke admin_logs
        DB::table('admin_logs')->insert([
            'admin_id'    => Auth::id(),
            'admin_name'  => Auth::user()->name,
            'action'      => 'Reset Password',
            'target_user' => $target->email,
            'created_at'  => now(),
        ]);

        // Catat ke user_logs juga
        DB::table('user_logs')->insert([
            'user_id'    => $target->id,
            'user_name'  => $target->name,
            'user_email' => $target->email,
            'activity'   => 'Password Direset oleh Admin',
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        return back()->with('success_user', 'Password berhasil direset.');
    }

    public function deleteUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        if ($request->user_id == Auth::id()) {
            return back()->withErrors(['user_id' => 'Tidak bisa menghapus akun sendiri.']);
        }

        $target = User::findOrFail($request->user_id);
        $email  = $target->email;
        $name   = $target->name;

        $target->delete();

        // Catat ke admin_logs
        DB::table('admin_logs')->insert([
            'admin_id'    => Auth::id(),
            'admin_name'  => Auth::user()->name,
            'action'      => 'Hapus Akun',
            'target_user' => $email,
            'created_at'  => now(),
        ]);

        return back()->with('success_user', "Akun {$name} berhasil dihapus.");
    }

    public function ssoRegister(Request $request)
    {
        $request->validate([
            'app_name'     => 'required|string|max:255',
            'redirect_url' => 'required|url',
        ]);

        DB::table('sso_applications')->insert([
            'name'         => $request->app_name,
            'redirect'     => $request->redirect_url,
            'client_id'    => \Illuminate\Support\Str::uuid(),
            'client_secret'=> \Illuminate\Support\Str::random(40),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        // Catat ke admin_logs
        DB::table('admin_logs')->insert([
            'admin_id'    => Auth::id(),
            'admin_name'  => Auth::user()->name,
            'action'      => 'Register Aplikasi SSO: ' . $request->app_name,
            'target_user' => '-',
            'created_at'  => now(),
        ]);

        return back()->with('success_sso', 'Aplikasi berhasil didaftarkan.');
    }
}
