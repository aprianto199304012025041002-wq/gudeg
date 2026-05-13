<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Events\AuditLogEvent;

class AdminController extends Controller
{
    // Halaman Pengaturan Umum / Admin
    public function index()
    {
        return view('settings');
    }

    // --------------------------------------------------------
    // KUMPULAN METHOD UNTUK MANAJEMEN APLIKASI SSO (/apps)
    // --------------------------------------------------------

    public function appsIndex()
    {
        $apps = DB::table('sso_applications')->get();
        return view('admin.apps', compact('apps'));
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
            
            // Menggunakan base_path() agar folder 'logos' tercipta langsung di dalam /htdocs
            $destinationPath = base_path('logos'); 
            
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }
            
            $file->move($destinationPath, $logoName);
            $data['logo'] = 'logos/' . $logoName;
        }

        // Simpan data aplikasi ke database
        DB::table('sso_applications')->insert($data);

        // Catat Log Aktivitas
        try {
            AuditLogEvent::dispatch('admin', [
                'admin'       => Auth::user(),
                'action'      => 'Registrasi Aplikasi Baru',
                'target_user' => $request->name
            ]);
        } catch (\Exception $e) {
            // Abaikan jika event log gagal
        }

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
                    'target_user' => $app->name . ' (' . ($newState ? 'Aktif' : 'Nonaktif') . ')'
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
            // Hapus file fisik gambar jika ada di /htdocs/logos/
            if ($app->logo && file_exists(base_path($app->logo))) {
                unlink(base_path($app->logo));
            }

            $appName = $app->name;
            DB::table('sso_applications')->where('id', $request->id)->delete();

            // Catat Log Aktivitas
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
        // Logika ping / pengecekan koneksi ke redirect URL aplikasi
        $app = DB::table('sso_applications')->where('id', $request->id)->first();
        if (!$app) {
            return response()->json(['status' => 'error', 'message' => 'Aplikasi tidak ditemukan.']);
        }

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(5)->get($app->redirect);
            $status = $response->successful() ? 'ok' : 'unreachable';
            return response()->json(['status' => $status, 'code' => $response->status()]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'offline', 'message' => $e->getMessage()]);
        }
    }

    // --------------------------------------------------------
    // METHOD LAINNYA (Manajemen User & Role)
    // --------------------------------------------------------
    
    public function resetPassword(Request $request) { /* Logika reset password user */ }
    public function updateRole(Request $request) { /* Logika update role */ }
    public function toggleActive(Request $request) { /* Logika toggle user aktif */ }
    public function destroy(Request $request) { /* Logika hapus user */ }
    public function toggleMaintenance(Request $request) { /* Logika mode maintenance */ }
    public function getUserApps($id) { /* Ambil relasi user dan aplikasi */ }
    public function syncUserApps(Request $request) { /* Simpan sinkronisasi akses aplikasi user */ }
}
