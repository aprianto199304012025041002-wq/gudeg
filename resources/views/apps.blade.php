@extends('layouts.app')

@section('title', 'Aplikasi SSO')
@section('topbar_title', 'GUDEG JOGJA Aplikasi SSO')

@push('styles')
<style>
    .apps-grid { display: grid; grid-template-columns: 1fr; gap: 14px; }
    @media (min-width: 1024px) { .apps-grid { grid-template-columns: 350px 1fr; align-items: start; } }

    .card { background: #252526; border: 1px solid #3c3c3c; border-radius: 4px; padding: 14px; }
    .card-title { font-size: 13px; font-weight: 600; color: #fff; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; }
    
    label { display: block; font-size: 11px; color: #858585; margin-bottom: 4px; }
    input, textarea { width: 100%; padding: 8px 10px; background: #1e1e1e; border: 1px solid #3c3c3c; color: #fff; border-radius: 3px; font-size: 12px; margin-bottom: 10px; box-sizing: border-box; font-family: inherit; }
    input[type="file"] { padding: 5px; font-size: 11px; cursor: pointer; }
    input:focus, textarea:focus { border-color: #007acc; outline: none; }
    
    .btn { background: #007acc; border: none; color: white; padding: 8px 12px; border-radius: 3px; cursor: pointer; font-size: 12px; font-weight: 600; min-height: 36px; display: inline-flex; align-items: center; justify-content: center; transition: 0.15s; }
    .btn:hover { background: #0090f1; }
    .btn-full { width: 100%; }
    .btn-danger { background: #c72e2e; } .btn-danger:hover { background: #d93a3a; }
    .btn-success { background: #2ea043; } .btn-success:hover { background: #3fb950; }

    .alert { font-size: 11px; margin-bottom: 10px; padding: 8px 10px; border-radius: 3px; }
    .alert-error { color: #ff7b72; background: #3b1214; border: 1px solid #f85149; }

    .table-container { overflow-x: auto; max-width: 100%; }
    .app-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 11px; }
    .app-table th { color: #858585; padding: 8px 6px; border-bottom: 1px solid #3c3c3c; font-size: 9px; text-transform: uppercase; white-space: nowrap; }
    .app-table td { padding: 10px 6px; border-bottom: 1px solid #2a2a2a; vertical-align: middle; }
    .app-table td b { color: #fff; display: block; font-size: 12px; margin-bottom: 2px; }
    
    .action-btns { display: flex; gap: 4px; flex-wrap: wrap; justify-content: flex-end; }
    .action-btns .btn { padding: 4px 8px; font-size: 10px; min-height: 28px; }
    
    .badge-admin { background: #c72e2e; padding: 2px 6px; border-radius: 3px; font-size: 9px; font-weight: bold; color: white; }

    .active-toggle-label { display: inline-flex; align-items: center; gap: 4px; font-size: 10px; cursor: pointer; }
    input[type="checkbox"] { width: auto; min-height: auto; margin: 0; cursor: pointer; accent-color: #007acc; }
    
    .app-logo-preview { width: 36px; height: 36px; object-fit: contain; background: #1e1e1e; border: 1px solid #3c3c3c; border-radius: 4px; padding: 4px; }
</style>
@endpush

@section('content')
<div class="apps-grid">

    <div class="left-panel">
        <div class="card">
            <div class="card-title">
                <span>Registrasi Aplikasi Baru</span>
                <span class="badge-admin">ADMIN</span>
            </div>

            @if($errors->any())
                <div class="alert alert-error">
                    @foreach($errors->all() as $error)<div>• {{ $error }}</div>@endforeach
                </div>
            @endif

            <form method="POST" action="/apps/store" enctype="multipart/form-data">
                @csrf
                <label>Nama Aplikasi</label>
                <input type="text" name="name" required placeholder="Contoh: E-Kinerja">
                
                <label>Callback URL (Redirect SSO)</label>
                <input type="url" name="redirect" required placeholder="https://aplikasi.go.id/login/sso/callback">

                <label>Upload Logo/Ikon (Opsional)</label>
                <input type="file" name="logo" accept="image/png, image/jpeg, image/webp, image/svg+xml">

                <label>Deskripsi Singkat (Opsional)</label>
                <textarea name="description" rows="3" placeholder="Aplikasi absensi pegawai..."></textarea>

                <button type="submit" class="btn btn-success btn-full" style="margin-top: 5px;">Daftarkan Aplikasi</button>
            </form>
        </div>
    </div>

    <div class="right-panel">
        <div class="card">
            <div class="card-title">Daftar Aplikasi Terintegrasi</div>

            @if($apps->isEmpty())
                <div style="font-size:11px;color:#858585;text-align:center;padding:20px 0;">Belum ada aplikasi yang didaftarkan.</div>
            @else
                <div class="table-container">
                    <table class="app-table">
                        <thead>
                            <tr>
                                <th style="width: 50px; text-align: center;">Logo</th>
                                <th>Informasi Aplikasi</th>
                                <th style="text-align: center;">Status</th>
                                <th style="text-align: right;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($apps as $app)
                            <tr>
                                <td style="text-align: center;">
                                    @if($app->logo)
                                        <img src="/{{ $app->logo }}" class="app-logo-preview" alt="logo" onerror="this.outerHTML='<div class=\'app-logo-preview\' style=\'display:flex;align-items:center;justify-content:center;font-size:16px;margin:0 auto;\'>🚀</div>'">
                                    @else
                                        <div class="app-logo-preview" style="display:flex; align-items:center; justify-content:center; font-size: 16px; margin:0 auto;">🚀</div>
                                    @endif
                                </td>
                                <td>
                                    <b style="font-size: 13px; color: #fff;">{{ $app->name }}</b>
                                    
                                    {{-- PERBAIKAN: Menggunakan !empty() agar jika isinya string kosong ("") tetap tertulis 'Tidak ada deskripsi' --}}
                                    <div style="font-size: 10px; color: #aaa; margin-top: 2px;">
                                        {{ !empty($app->description) ? $app->description : 'Tidak ada deskripsi' }}
                                    </div>
                                    
                                    <div style="margin-top:6px; font-size:10px; color:#858585;">🔗 {{ $app->redirect }}</div>
                                    <div style="margin-top:2px; font-size:10px; color:#555; font-family: monospace; word-break: break-all;">ID: {{ $app->client_id }}</div>
                                </td>
                                <td style="text-align: center;">
                                    <form action="/apps/toggle" method="POST" id="form-active-{{ $app->id }}">
                                        @csrf
                                        <input type="hidden" name="id" value="{{ $app->id }}">
                                        <input type="hidden" name="is_active" value="0">
                                        
                                        <label class="active-toggle-label">
                                            <input type="checkbox" name="is_active" value="1" {{ (!isset($app->is_active) || $app->is_active) ? 'checked' : '' }} onchange="this.form.submit()">
                                            <span style="color: {{ (!isset($app->is_active) || $app->is_active) ? '#7ee787' : '#ff7b72' }}; font-weight: bold;">
                                                {{ (!isset($app->is_active) || $app->is_active) ? 'Aktif' : 'Deaktif' }}
                                            </span>
                                        </label>
                                    </form>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <button type="button" class="btn" style="background:#007acc;" onclick="pingApp('{{ $app->id }}', '{{ $app->name }}')">Test Ping</button>
                                        
                                        <form action="/apps/delete" method="POST" id="delete-app-{{ $app->id }}" style="margin:0;">
                                            @csrf
                                            <input type="hidden" name="id" value="{{ $app->id }}">
                                            <button type="button" class="btn btn-danger" onclick="confirmDeleteApp('{{ $app->id }}', '{{ $app->name }}')">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
    const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, background: '#252526', color: '#fff' });

    @if(session('success_store')) Toast.fire({ icon: 'success', title: '{{ session("success_store") }}' }); @endif
    @if(session('success_toggle')) Toast.fire({ icon: 'success', title: '{{ session("success_toggle") }}' }); @endif
    @if(session('success_delete')) Toast.fire({ icon: 'success', title: '{{ session("success_delete") }}' }); @endif
    @if(session('error')) Toast.fire({ icon: 'error', title: '{{ session("error") }}' }); @endif

    function pingApp(id, name) {
        Swal.fire({
            title: `Pinging ${name}...`,
            text: 'Mencoba terhubung ke server target via HTTP Request.',
            allowOutsideClick: false,
            background: '#252526', color: '#fff',
            didOpen: () => { Swal.showLoading(); }
        });

        fetch('/apps/ping', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'ok') {
                Swal.fire({ title: 'Koneksi Berhasil!', text: `Aplikasi merespons dengan status code: ${data.code}`, icon: 'success', background: '#252526', color: '#fff', confirmButtonColor: '#2ea043' });
            } else if (data.status === 'unreachable') {
                Swal.fire({ title: 'Server Unreachable', text: `Target ditemukan namun membalas dengan error code: ${data.code}`, icon: 'warning', background: '#252526', color: '#fff', confirmButtonColor: '#c72e2e' });
            } else {
                Swal.fire({ title: 'Gagal Terhubung', text: data.message || 'Server target offline atau tidak dapat diakses.', icon: 'error', background: '#252526', color: '#fff', confirmButtonColor: '#c72e2e' });
            }
        })
        .catch(error => {
            Swal.fire({ title: 'Error Jaringan', text: 'Terjadi kesalahan sistem saat melakukan ping.', icon: 'error', background: '#252526', color: '#fff', confirmButtonColor: '#c72e2e' });
        });
    }

    function confirmDeleteApp(id, name) {
        Swal.fire({
            title: 'Hapus Aplikasi?',
            text: `Aplikasi ${name} tidak akan bisa menggunakan SSO lagi. Lanjutkan?`,
            icon: 'warning', background: '#252526', color: '#fff', showCancelButton: true,
            confirmButtonColor: '#c72e2e', cancelButtonColor: '#444',
            confirmButtonText: 'Ya, Hapus!', cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) { document.getElementById(`delete-app-${id}`).submit(); }
        });
    }
</script>
@endpush
