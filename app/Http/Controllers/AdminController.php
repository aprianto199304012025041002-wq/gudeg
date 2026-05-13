<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Events\AuditLogEvent;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        $users    = collect();
        $logs     = collect();
        $userLogs = collect();

        $sessions = DB::table('sessions')
            ->where('user_id', Auth::id())
            ->orderBy('last_activity', 'desc')
            ->get();

        $isMaintenance = \Illuminate\Support\Facades\Cache::get('maintenance_mode', false);

        if (Auth::user()->role === 'admin') {
            $search = $request->query('search');

            $users = User::query()
                ->when($search, function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'LIKE', "%{$search}%")
                          ->orWhere('email', 'LIKE', "%{$search}%");
                    });
                })
                ->orderByDesc('created_at')
                ->paginate(10)
                ->withQueryString();

            try {
                $logs = DB::table('admin_logs')
                    ->where('created_at', '>=', now()->subMonth())
                    ->orderByDesc('created_at')
                    ->paginate(10, ['*'], 'log_page')
                    ->withQueryString();

                $userLogs = DB::table('user_logs')
                    ->where('created_at', '>=', now()->subMonth())
                    ->orderByDesc('created_at')
                    ->paginate(10, ['*'], 'ulog_page')
                    ->withQueryString();

                DB::table('admin_logs')->where('created_at', '<', now()->subMonth())->delete();
                DB::table('user_logs')->where('created_at', '<', now()->subMonth())->delete();
            } catch (\Exception $e) {}
        }

        return view('settings', compact('users', 'logs', 'userLogs', 'sessions', 'isMaintenance'));
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'user_id'      => 'required|exists:users,id',
            'new_password' => 'required|string|min:8',
        ]);

        $target = User::findOrFail($request->user_id);

        if (strtolower($target->email) === 'admin@dummy.com' && Auth::id() !== $target->id) {
            return back()->withErrors(['reset' => 'Akses ditolak: Tidak dapat mereset password Administrator Utama.']);
        }

        DB::transaction(function () use ($target, $request) {
            $target->password = Hash::make($request->new_password);
            $target->password_changed_at = now();
            $target->save();

            AuditLogEvent::dispatch('admin', ['admin' => Auth::user(), 'action' => 'Reset Password', 'target_user' => $target->email]);
            AuditLogEvent::dispatch('user', ['user' => $target, 'activity' => 'Password Direset oleh Admin (' . Auth::user()->name . ')', 'ip' => request()->ip()]);
        });

        return back()->with('success_user', 'Password berhasil di-reset!');
    }

    public function updateRole(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role'    => 'required|in:user,guest',
        ]);

        $target = User::findOrFail($request->user_id);

        if (strtolower($target->email) === 'admin@dummy.com' || $target->id === Auth::id()) {
            return back()->withErrors(['role' => 'Akses ditolak untuk mengubah role akun utama/sendiri.']);
        }

        $target->role = $request->role;
        $target->save();

        AuditLogEvent::dispatch('admin', ['admin' => Auth::user(), 'action' => 'Ubah Role ke ' . strtoupper($request->role), 'target_user' => $target->email]);

        return back()->with('success_user', 'Role berhasil diperbarui!');
    }

    public function toggleActive(Request $request)
    {
        $request->validate([
            'user_id'   => 'required|exists:users,id',
            'is_active' => 'required|boolean',
        ]);

        $target = User::findOrFail($request->user_id);

        if (strtolower($target->email) === 'admin@dummy.com' && !$request->is_active) {
            return back()->withErrors(['active' => 'Akses ditolak: Administrator Utama tidak dapat dinonaktifkan.']);
        }

        if ($target->id === Auth::id()) {
            return back()->withErrors(['active' => 'Tidak dapat menonaktifkan akun sendiri.']);
        }

        $target->is_active = $request->is_active;
        $target->save();

        $statusStr = $request->is_active ? 'MENGAKTIFKAN' : 'MENONAKTIFKAN';
        AuditLogEvent::dispatch('admin', ['admin' => Auth::user(), 'action' => $statusStr . ' Akun Login', 'target_user' => $target->email]);

        return back()->with('success_user', "Status login {$target->name} berhasil diperbarui!");
    }

    public function destroy(Request $request)
    {
        $request->validate(['user_id' => 'required|exists:users,id']);
        $target = User::findOrFail($request->user_id);

        if (strtolower($target->email) === 'admin@dummy.com' || $target->id === Auth::id()) {
            return back()->withErrors(['delete' => 'Akses ditolak untuk menghapus akun utama/sendiri.']);
        }

        $name  = $target->name;
        $email = $target->email;
        $target->delete();

        AuditLogEvent::dispatch('admin', ['admin' => Auth::user(), 'action' => 'Hapus Akun', 'target_user' => $email]);

        return back()->with('success_user', "Akun {$name} berhasil dihapus!");
    }

    public function toggleMaintenance(Request $request)
    {
        if (strtolower(Auth::user()->email) !== 'admin@dummy.com') {
            return back()->withErrors(['maintenance' => 'Hanya Administrator Utama yang dapat mengubah mode perbaikan.']);
        }

        $status = \Illuminate\Support\Facades\Cache::get('maintenance_mode', false);

        if ($status) {
            \Illuminate\Support\Facades\Cache::put('maintenance_mode', false);
            $msg    = 'Mode Perbaikan DINONAKTIFKAN. Portal kembali online untuk semua pengguna.';
            $action = 'Menonaktifkan Mode Perbaikan';
        } else {
            \Illuminate\Support\Facades\Cache::put('maintenance_mode', true);
            $msg    = 'Mode Perbaikan DIAKTIFKAN. Saat ini hanya Anda yang dapat mengakses portal.';
            $action = 'Mengaktifkan Mode Perbaikan Global';
        }

        AuditLogEvent::dispatch('admin', ['admin' => Auth::user(), 'action' => $action, 'target_user' => 'SISTEM GLOBAL']);

        return back()->with('success', $msg);
    }

    // =========================================================================
    // FITUR MANAJEMEN APLIKASI SSO
    // Logo disimpan langsung ke /htdocs/logos/ (tanpa symlink)
    // =========================================================================

    public function appsIndex()
    {
        $apps = DB::table('sso_applications')->orderByDesc('created_at')->get();
        return view('apps', compact('apps'));
    }

    public function appsStore(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'redirect'    => 'required|url',
            'description' => 'nullable|string|max:255',
            'logo'        => 'nullable|image|mimes:jpeg,png,jpg,webp,svg|max:2048',
        ]);

        $logoPath = null;
        if ($request->hasFile('logo')) {
            $file     = $request->file('logo');
            $filename = time() . '_' . \Illuminate\Support\Str::slug($request->name) . '.' . $file->getClientOriginalExtension();

            // Simpan langsung ke /htdocs/logos/ (public root InfinityFree)
            $logosDir = public_path('logos');
            if (!file_exists($logosDir)) {
                mkdir($logosDir, 0755, true);
            }
            $file->move($logosDir, $filename);
            $logoPath = 'logos/' . $filename;
        }

        DB::table('sso_applications')->insert([
            'name'          => $request->name,
            'redirect'      => $request->redirect,
            'description'   => $request->description,
            'logo'          => $logoPath,
            'is_active'     => true,
            'client_id'     => \Illuminate\Support\Str::uuid(),
            'client_secret' => \Illuminate\Support\Str::random(40),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        AuditLogEvent::dispatch('admin', ['admin' => Auth::user(), 'action' => 'Registrasi Aplikasi SSO: ' . $request->name, 'target_user' => '-']);

        return back()->with('success_store', 'Aplikasi berhasil didaftarkan!');
    }

    public function appsToggle(Request $request)
    {
        $request->validate([
            'id'        => 'required|exists:sso_applications,id',
            'is_active' => 'required|boolean',
        ]);

        DB::table('sso_applications')->where('id', $request->id)->update([
            'is_active'  => $request->is_active,
            'updated_at' => now(),
        ]);

        $app       = DB::table('sso_applications')->where('id', $request->id)->first();
        $statusStr = $request->is_active ? 'Mengaktifkan' : 'Menonaktifkan';

        AuditLogEvent::dispatch('admin', ['admin' => Auth::user(), 'action' => $statusStr . ' Aplikasi SSO: ' . $app->name, 'target_user' => '-']);

        return back()->with('success_toggle', "Status Aplikasi {$app->name} berhasil diperbarui!");
    }

    public function appsDestroy(Request $request)
    {
        $request->validate(['id' => 'required|exists:sso_applications,id']);

        $app = DB::table('sso_applications')->where('id', $request->id)->first();

        // Hapus file logo dari /htdocs/logos/
        if ($app->logo) {
            $logoFullPath = public_path($app->logo);
            if (file_exists($logoFullPath)) {
                unlink($logoFullPath);
            }
        }

        DB::table('sso_applications')->where('id', $request->id)->delete();

        AuditLogEvent::dispatch('admin', ['admin' => Auth::user(), 'action' => 'Hapus Aplikasi SSO: ' . $app->name, 'target_user' => '-']);

        return back()->with('success_delete', 'Aplikasi berhasil dihapus!');
    }

    public function appsPing(Request $request)
    {
        $request->validate(['id' => 'required|exists:sso_applications,id']);
        $app = DB::table('sso_applications')->where('id', $request->id)->first();

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(5)
                ->withoutVerifying()
                ->get($app->redirect);

            $status = $response->status();

            if ($response->successful() || $response->isRedirect() || $response->isClientError()) {
                return response()->json(['success' => true, 'message' => "Koneksi Berhasil terhubung ke server client (HTTP {$status})."]);
            }

            return response()->json(['success' => false, 'message' => "Server merespons dengan error internal (HTTP {$status})."]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => "RTO / Gagal Terkoneksi: " . $e->getMessage()]);
        }
    }

    // =========================================================================
    // FITUR CUSTOM HAK AKSES APLIKASI PER USER
    // =========================================================================

    public function getUserApps($id)
    {
        if (Auth::user()->role !== 'admin') return response()->json(['success' => false]);

        $allApps  = DB::table('sso_applications')->where('is_active', true)->select('id', 'name')->get();
        $userApps = DB::table('user_applications')->where('user_id', $id)->pluck('application_id')->toArray();

        return response()->json([
            'success'   => true,
            'all_apps'  => $allApps,
            'user_apps' => $userApps,
        ]);
    }

    public function syncUserApps(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'apps'    => 'array',
            'apps.*'  => 'exists:sso_applications,id',
        ]);

        $targetUser = User::findOrFail($request->user_id);

        DB::transaction(function () use ($request) {
            DB::table('user_applications')->where('user_id', $request->user_id)->delete();

            if ($request->has('apps') && is_array($request->apps)) {
                $inserts = [];
                foreach ($request->apps as $appId) {
                    $inserts[] = [
                        'user_id'        => $request->user_id,
                        'application_id' => $appId,
                    ];
                }
                DB::table('user_applications')->insert($inserts);
            }
        });

        AuditLogEvent::dispatch('admin', ['admin' => Auth::user(), 'action' => 'Update Akses Aplikasi', 'target_user' => $targetUser->email]);

        return back()->with('success_user', "Hak akses aplikasi untuk {$targetUser->name} berhasil diperbarui!");
    }
}
