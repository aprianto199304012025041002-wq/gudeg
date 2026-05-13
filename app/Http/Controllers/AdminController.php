<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use App\Events\AuditLogEvent;

class AdminController extends Controller
{
    // --------------------------------------------------------
    // HALAMAN PENGATURAN UMUM / ADMIN (/settings)
    // --------------------------------------------------------
    public function index(Request $request)
    {
        // 1. Ambil data sesi user yang sedang login (untuk tabel Perangkat & Sesi Aktif)
        // Pastikan driver session di .env adalah SESSION_DRIVER=database
        $sessions = [];
        try {
            $sessions = DB::table('sessions')->where('user_id', Auth::id())->orderBy('last_activity', 'desc')->get();
        } catch (\Exception $e) {
            // Abaikan jika tabel sessions tidak ada / driver bukan database
            $sessions = collect([]); 
        }

        // Variabel default khusus admin agar tidak undefined di Blade
        $logs = collect([]);
        $users = collect([]);
        $userLogs = collect([]);
        $isMaintenance = false;

        // 2. Jika yang login adalah Admin, ambil data tambahan
        if (Auth::user()->role === 'admin') {
            
            // Log Admin
            try {
                $logs = DB::table('admin_logs')->orderBy('created_at', 'desc')->paginate(5, ['*'], 'log_page');
            } catch (\Exception $e) { /* Tabel admin_logs mungkin belum dibuat */ }

            // Log User
            try {
                $userLogs = DB::table('user_logs')->orderBy('created_at', 'desc')->paginate(5, ['*'], 'ulog_page');
            } catch (\Exception $e) { /* Tabel user_logs mungkin belum dibuat */ }

            // Manajemen Pengguna (Dengan Pencarian)
            $query = DB::table('users');
            if ($request->has('search') && $request->search != '') {
                $query->where('name', 'like', '%' . $request->search . '%')
                      ->orWhere('email', 'like', '%' . $request->search . '%');
            }
            $users = $query->paginate(10, ['*'], 'page');

            // Cek Status Maintenance
            try {
                $maintenance = DB::table('settings')->where('key', 'maintenance_mode')->first();
                $isMaintenance = $maintenance ? (bool)$maintenance->value : false;
            } catch (\Exception $e) { /* Tabel settings belum dibuat */ }
        }

        return view('settings', compact('sessions', 'logs', 'users', 'userLogs', 'isMaintenance'));
    }

    // --------------------------------------------------------
    // KUMPULAN METHOD UNTUK MANAJEMEN APLIKASI SSO (/apps)
    // --------------------------------------------------------
    public function appsIndex()
    {
        $apps = DB::table('sso_applications')->get();
        return view('apps', compact('apps'));
    }

    public function appsStore(Request $request)
    {
        $request->validate([
            'name'     => 'required|unique:sso_applications,name',
            'redirect' => 'required|url',
            'logo'     => 'nullable|image|mimes:jpg,jpeg,png|max:2048'
        ]);

        $data = [
            'name'          => $request->name,
            'redirect'      => $request->redirect,
            'client_id'     => bin2hex(random_bytes(16)),
            'client_secret' => bin2hex(random_bytes(32)),
        ];

        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $logoName = time() . '_' . $file->getClientOriginalName();
            
            $destinationPath = base_path('logos'); 
            
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }
            
            $file->move($destinationPath, $logoName);
            $data['logo'] = 'logos/' . $logoName;
        }

        DB::table('sso_applications')->insert($data);

        try {
            AuditLogEvent::dispatch('admin', [
                'admin'       => Auth::user(),
                'action'      => 'Registrasi Aplikasi Baru',
                'target_user' => $request->name
            ]);
        } catch (\Exception $e) {}

        return back()->with('success_store', 'Aplikasi berhasil didaftarkan!');
    }

    public function appsToggle(Request $request)
    {
        $app = DB::table('sso_applications')->where('id', $request->id)->first();
        if ($app) {
            $newState = !$app->is_active;
            DB::table('sso_applications')->where('id', $request->id)->update(['is_active' => $newState]);

            try {
                AuditLogEvent::dispatch('admin', [
                    'admin'       => Auth::user(),
                    'action'      => 'Update Status Aplikasi',
                    'target_user' => $app->name . ' (' . ($newState ? 'Aktif' : 'Deaktif') . ')'
                ]);
            } catch (\Exception $e) {}

            return back()->with('success_toggle', 'Status aplikasi berhasil diperbarui!');
        }
        return back()->with('error', 'Aplikasi tidak ditemukan.');
    }

    public function appsDestroy(Request $request)
    {
        $app = DB::table('sso_applications')->where('id', $request->id)->first();
        
        if ($app) {
            if ($app->logo && file_exists(base_path($app->logo))) {
                unlink(base_path($app->logo));
            }

            $appName = $app->name;
            DB::table('sso_applications')->where('id', $request->id)->delete();

            try {
                AuditLogEvent::dispatch('admin', [
                    'admin'       => Auth::user(),
                    'action'      => 'Hapus Aplikasi',
                    'target_user' => $appName
                ]);
            } catch (\Exception $e) {}

            return back()->with('success_delete', 'Aplikasi berhasil dihapus!');
        }

        return back()->with('error', 'Aplikasi tidak ditemukan.');
    }

    public function appsPing(Request $request)
    {
        $app = DB::table('sso_applications')->where('id', $request->id)->first();
        if (!$app) {
            return response()->json(['status' => 'error', 'message' => 'Aplikasi tidak ditemukan.']);
        }

        try {
            $response = Http::timeout(5)->get($app->redirect);
            $status = $response->successful() ? 'ok' : 'unreachable';
            return response()->json(['status' => $status, 'code' => $response->status()]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'offline', 'message' => $e->getMessage()]);
        }
    }

    // --------------------------------------------------------
    // METHOD MANAJEMEN USER & ROLE
    // --------------------------------------------------------        
    public function resetPassword(Request $request) 
    { 
        $request->validate([
            'user_id' => 'required',
            'new_password' => 'required|min:8'
        ]);

        $user = DB::table('users')->where('id', $request->user_id)->first();
        if ($user) {
            DB::table('users')->where('id', $request->user_id)->update([
                'password' => Hash::make($request->new_password)
            ]);

            try {
                AuditLogEvent::dispatch('admin', [
                    'admin' => Auth::user(), 
                    'action' => 'Reset Password User', 
                    'target_user' => $user->name
                ]);
            } catch (\Exception $e) {}

            return back()->with('success_user', "Password {$user->name} berhasil direset.");
        }

        return back()->withErrors(['Error' => 'User tidak ditemukan.']);
    }

    public function updateRole(Request $request) 
    { 
        $user = DB::table('users')->where('id', $request->user_id)->first();
        if ($user) {
            DB::table('users')->where('id', $request->user_id)->update(['role' => $request->role]);

            try {
                AuditLogEvent::dispatch('admin', [
                    'admin' => Auth::user(), 
                    'action' => 'Ubah Role menjadi ' . strtoupper($request->role), 
                    'target_user' => $user->name
                ]);
            } catch (\Exception $e) {}

            return back()->with('success_user', "Role {$user->name} berhasil diubah.");
        }
        return back();
    }

    public function toggleActive(Request $request) 
    { 
        $user = DB::table('users')->where('id', $request->user_id)->first();
        if ($user) {
            $isActive = $request->is_active ? 1 : 0;
            DB::table('users')->where('id', $request->user_id)->update(['is_active' => $isActive]);

            $statusText = $isActive ? 'Diaktifkan' : 'Dinonaktifkan';
            try {
                AuditLogEvent::dispatch('admin', [
                    'admin' => Auth::user(), 
                    'action' => "Status Akun {$statusText}", 
                    'target_user' => $user->name
                ]);
            } catch (\Exception $e) {}

            return back()->with('success_user', "Akun {$user->name} berhasil {$statusText}.");
        }
        return back();
    }

    public function destroy(Request $request) 
    { 
        $user = DB::table('users')->where('id', $request->user_id)->first();
        if ($user) {
            // Hapus akses aplikasi user terlebih dahulu (jika ada foreign key)
            DB::table('user_applications')->where('user_id', $user->id)->delete();
            DB::table('users')->where('id', $request->user_id)->delete();

            try {
                AuditLogEvent::dispatch('admin', [
                    'admin' => Auth::user(), 
                    'action' => 'Hapus User', 
                    'target_user' => $user->name
                ]);
            } catch (\Exception $e) {}

            return back()->with('success_user', "Pengguna {$user->name} berhasil dihapus.");
        }
        return back();
    }

    public function toggleMaintenance(Request $request) 
    { 
        $setting = DB::table('settings')->where('key', 'maintenance_mode')->first();
        $newState = ($setting && $setting->value == '1') ? '0' : '1';

        if ($setting) {
            DB::table('settings')->where('key', 'maintenance_mode')->update(['value' => $newState]);
        } else {
            DB::table('settings')->insert(['key' => 'maintenance_mode', 'value' => $newState]);
        }

        $statusText = $newState == '1' ? 'AKTIF' : 'NONAKTIF';
        try {
            AuditLogEvent::dispatch('admin', [
                'admin' => Auth::user(), 
                'action' => "Toggle Mode Maintenance ({$statusText})", 
                'target_user' => '-'
            ]);
        } catch (\Exception $e) {}

        return back()->with('success', "Mode Maintenance berhasil {$statusText}.");
    }

    public function getUserApps($id) 
    { 
        // Mengembalikan JSON untuk pop-up SweetAlert di settings.blade.php
        $all_apps = DB::table('sso_applications')->select('id', 'name')->get();
        $user_apps = DB::table('user_applications')->where('user_id', $id)->pluck('application_id')->toArray();
        
        return response()->json([
            'success' => true,
            'all_apps' => $all_apps,
            'user_apps' => $user_apps
        ]);
    }

    public function syncUserApps(Request $request) 
    { 
        $userId = $request->user_id;
        $appIds = $request->apps ?? []; // Jika tidak ada checkbox yang dicentang, nilainya null/kosong

        // Reset semua akses aplikasi user ini
        DB::table('user_applications')->where('user_id', $userId)->delete();

        // Masukkan kembali akses aplikasi yang baru
        $inserts = [];
        foreach ($appIds as $appId) {
            $inserts[] = [
                'user_id' => $userId, 
                'application_id' => $appId
            ];
        }

        if (count($inserts) > 0) {
            DB::table('user_applications')->insert($inserts);
        }

        $user = DB::table('users')->where('id', $userId)->first();
        try {
            AuditLogEvent::dispatch('admin', [
                'admin' => Auth::user(), 
                'action' => 'Update Akses Aplikasi SSO', 
                'target_user' => $user->name ?? 'Unknown'
            ]);
        } catch (\Exception $e) {}

        return back()->with('success_user', "Akses aplikasi untuk {$user->name} berhasil diperbarui.");
    }
}
