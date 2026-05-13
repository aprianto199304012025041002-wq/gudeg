public function appsStore(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:sso_applications,name',
            'redirect' => 'required|url',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048'
        ]);

        $data = [
            'name' => $request->name,
            'redirect' => $request->redirect,
            'client_id' => bin2hex(random_bytes(16)),
            'client_secret' => bin2hex(random_bytes(32)),
        ];

        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $logoName = time() . '_' . $file->getClientOriginalName();
            
            // Kunci Perbaikan untuk InfinityFree:
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

        // Catat Log Aktivitas (jika menggunakan event AuditLogEvent)
        try {
            \App\Events\AuditLogEvent::dispatch('admin', [
                'admin' => Auth::user(),
                'action' => 'Registrasi Aplikasi Baru',
                'target_user' => $request->name
            ]);
        } catch (\Exception $e) {
            // Abaikan jika event log gagal agar tidak mengganggu proses simpan
        }

        // Return session key spesifik agar notifikasi Toast muncul di tempat yang tepat
        return back()->with('success_store', 'Aplikasi berhasil didaftarkan!');
    }

    public function appsDestroy(Request $request)
    {
        // Asumsi ID dikirim via form input hidden bernama 'id'
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
                \App\Events\AuditLogEvent::dispatch('admin', [
                    'admin' => Auth::user(),
                    'action' => 'Hapus Aplikasi',
                    'target_user' => $appName
                ]);
            } catch (\Exception $e) {}

            return back()->with('success_delete', 'Aplikasi berhasil dihapus!');
        }

        return back()->with('error', 'Aplikasi tidak ditemukan.');
    }
