@extends('layouts.app')

@section('title', 'Aplikasi SSO')
@section('topbar_title', 'Aplikasi SSO')

@push('styles')
<style>
    .apps-grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: 14px; }
    @media (min-width: 1024px) { .apps-grid { grid-template-columns: 350px minmax(0, 1fr); align-items: start; } }

    .card {
        background: rgba(13,25,50,0.75);
        border: 1px solid #1a2e50;
        border-radius: 4px; padding: 14px; overflow: hidden;
    }
    .card-title {
        font-size: 13px; font-weight: 600; color: #d0e4ff;
        margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center;
    }

    label { display: block; font-size: 11px; color: #4a6a90; margin-bottom: 4px; }
    input, textarea {
        width: 100%; padding: 8px 10px;
        background: #060e1c; border: 1px solid #1a2e50;
        color: #c8d0e0; border-radius: 3px; font-size: 12px;
        margin-bottom: 10px; box-sizing: border-box; font-family: inherit;
    }
    input[type="file"] { padding: 5px; font-size: 11px; cursor: pointer; }
    input:focus, textarea:focus { border-color: #2d5fff; outline: none; }

    .btn {
        background: #1a3a8a; border: none; color: #d0e4ff;
        padding: 8px 12px; border-radius: 3px; cursor: pointer;
        font-size: 12px; font-weight: 600; min-height: 36px;
        display: inline-flex; align-items: center; justify-content: center;
        transition: 0.15s;
    }
    .btn:hover { background: #1e4aaa; }
    .btn-full { width: 100%; }
    .btn-danger  { background: #6a1a1a; color: #f0a0a0; } .btn-danger:hover  { background: #8a2020; }
    .btn-success { background: #0a3a1a; color: #7ee787; } .btn-success:hover { background: #0d4a22; }
    .btn-warning { background: #3a2a00; color: #e8b84b; } .btn-warning:hover { background: #4a3600; }
    .btn-copy    { background: transparent; border: none; cursor: pointer; color: #e8b84b; padding: 2px 4px; font-size: 12px; transition: transform 0.1s; }
    .btn-copy:active { transform: scale(0.9); }

    .alert { font-size: 11px; margin-bottom: 10px; padding: 8px 10px; border-radius: 3px; }
    .alert-error { color: #c05050; background: #2a0a0a; border: 1px solid #6a1a1a; }

    .table-container { overflow-x: auto; max-width: 100%; }
    .app-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 11px; }
    .app-table th {
        color: #3d5a80; padding: 8px 6px;
        border-bottom: 1px solid #1a2e50;
        font-size: 9px; text-transform: uppercase; white-space: nowrap;
    }
    .app-table td { padding: 10px 6px; border-bottom: 1px solid #0f1e38; vertical-align: middle; }
    .app-table td b { color: #b0c8e8; display: block; font-size: 12px; margin-bottom: 2px; }

    .action-btns { display: flex; gap: 4px; flex-wrap: wrap; justify-content: flex-end; }
    .action-btns .btn { padding: 4px 8px; font-size: 10px; min-height: 28px; }

    .badge-admin  { background: #6a1a1a; padding: 2px 6px; border-radius: 3px; font-size: 9px; font-weight: bold; color: #f0a0a0; }
    
    .status-badge { 
        padding: 4px 8px; border-radius: 4px; font-size: 9px; font-weight: bold; 
        display: inline-flex; align-items: center; gap: 4px; border: 1px solid transparent; 
    }
    .badge-active { background: rgba(10, 58, 26, 0.3); color: #4ade80; border-color: #166534; }
    .badge-inactive { background: rgba(106, 26, 26, 0.3); color: #f87171; border-color: #991b1b; }
    .badge-signed { background: rgba(45, 26, 90, 0.4); color: #c0a0f0; border-color: #4a2a9a; }
    .badge-cache { background: rgba(13, 30, 58, 0.4); color: #7a9bbf; border-color: #1a2e50; }

    .active-toggle-label { display: inline-flex; align-items: center; gap: 6px; font-size: 10px; cursor: pointer; }
    input[type="checkbox"] { width: auto; min-height: auto; margin: 0; cursor: pointer; accent-color: #2d5fff; }

    .checkbox-row {
        display: flex; align-items: flex-start; gap: 8px; margin-bottom: 12px;
        background: #060e1c; border: 1px solid #1a2e50;
        border-radius: 3px; padding: 10px;
    }
    .checkbox-row input[type="checkbox"] { margin-top: 2px; accent-color: #4a2a9a; }
    .checkbox-row .checkbox-label { font-size: 11px; color: #7a9bbf; line-height: 1.5; }
    .checkbox-row .checkbox-label span { display: block; font-size: 10px; color: #3d5a80; margin-top: 2px; }

    .app-logo-preview {
        width: 36px; height: 36px; object-fit: contain;
        background: #060e1c; border: 1px solid #1a2e50;
        border-radius: 4px; padding: 4px;
    }
    
    .client-id-box {
        display: flex; align-items: center; gap: 6px; margin-top: 6px;
    }
    .client-id-text {
        font-family: monospace; font-size: 10px; color: #7a9bbf;
        background: #060e1c; padding: 2px 6px; border-radius: 3px; border: 1px solid #1a2e50;
    }
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

                <div class="checkbox-row">
                    <input type="checkbox" name="use_signed_token" value="1" id="use_signed_token">
                    <div class="checkbox-label">
                        <label for="use_signed_token" style="display:inline; color:#7a9bbf; font-size:11px; margin:0; cursor:pointer;">Gunakan Signed Token</label>
                        <span>Aktifkan jika aplikasi tidak bisa melakukan validasi server-to-server ke Gudeg SSO (misal: hosting shared seperti Hostinger, InfinityFree, dll).</span>
                    </div>
                </div>

                <button type="submit" class="btn btn-success btn-full" style="margin-top: 5px;">Daftarkan Aplikasi</button>
            </form>
        </div>
    </div>

    <div class="right-panel">
        <div class="card">
            <div class="card-title">Daftar Aplikasi Terintegrasi</div>

            @if($apps->isEmpty())
                <div style="font-size:11px;color:#3d5a80;text-align:center;padding:20px 0;">Belum ada aplikasi yang didaftarkan.</div>
            @else
                <div class="table-container">
                    <table class="app-table">
                        <thead>
                            <tr>
                                <th style="width: 50px; text-align: center;">Logo</th>
                                <th>Informasi Aplikasi</th>
                                <th style="text-align: center;">Status</th>
                                <th style="text-align: center;">Token Mode</th>
                                <th style="text-align: right;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($apps as $app)
                            <tr>
                                <td style="text-align: center;">
                                    @if($app->logo)
                                        <img src="/{{ $app->logo }}" class="app-logo-preview" alt="logo" loading="lazy" onerror="this.outerHTML='<div class=\'app-logo-preview\' style=\'display:flex;align-items:center;justify-content:center;font-size:16px;margin:0 auto;\'>🚀</div>'">
                                    @else
                                        <div class="app-logo-preview" style="display:flex; align-items:center; justify-content:center; font-size: 16px; margin:0 auto;">🚀</div>
                                    @endif
                                </td>
                                <td>
                                    <b style="font-size: 13px; color: #b0c8e8;">{{ $app->name }}</b>
                                    <div style="font-size: 10px; color: #4a6a90; margin-top: 2px;">
                                        {{ !empty($app->description) ? $app->description : 'Tidak ada deskripsi' }}
                                    </div>
                                    <div style="margin-top:6px; font-size:10px; color:#3d5a80; word-break: break-all;">🔗 {{ $app->redirect }}</div>
                                    <div class="client-id-box">
                                        <span class="client-id-text">ID: {{ $app->client_id }}</span>
                                        <button type="button" class="btn-copy" onclick="copyText('{{ $app->client_id }}')" title="Salin ID Klien">📋</button>
                                    </div>
                                </td>
                                <td style="text-align: center;">
                                    <form action="/apps/toggle" method="POST" id="form-active-{{ $app->id }}">
                                        @csrf
                                        <input type="hidden" name="id" value="{{ $app->id }}">
                                        <input type="hidden" name="is_active" value="0">
                                        <label class="active-toggle-label">
                                            <input type="checkbox" name="is_active" value="1" {{ (!isset($app->is_active) || $app->is_active) ? 'checked' : '' }} onchange="this.form.submit()">
                                            <span class="status-badge {{ (!isset($app->is_active) || $app->is_active) ? 'badge-active' : 'badge-inactive' }}">
                                                {{ (!isset($app->is_active) || $app->is_active) ? '🟢 Aktif' : '🔴 Offline' }}
                                            </span>
                                        </label>
                                    </form>
                                </td>
                                <td style="text-align: center;">
                                    <form action="/apps/toggle-signed-token" method="POST" id="form-signed-{{ $app->id }}">
                                        @csrf
                                        <input type="hidden" name="id" value="{{ $app->id }}">
                                        <label class="active-toggle-label">
                                            <input type="checkbox" {{ $app->use_signed_token ? 'checked' : '' }} onchange="this.form.submit()" style="accent-color: #4a2a9a;">
                                            <span class="status-badge {{ $app->use_signed_token ? 'badge-signed' : 'badge-cache' }}">
                                                {{ $app->use_signed_token ? '🔑 Signed' : '💾 Cache' }}
                                            </span>
                                        </label>
                                    </form>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <button type="button" class="btn" onclick="pingApp('{{ $app->id }}', '{{ $app->name }}')">Test Ping</button>
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
    const Toast = Swal.mixin({
        toast: true, position: 'top-end', showConfirmButton: false,
        timer: 3000, background: '#0d1929', color: '#c8d0e0'
    });

    @if(session('success_store')) Toast.fire({ icon: 'success', title: '{{ session("success_store") }}' }); @endif
    @if(session('success_toggle')) Toast.fire({ icon: 'success', title: '{{ session("success_toggle") }}' }); @endif
    @if(session('success_delete')) Toast.fire({ icon: 'success', title: '{{ session("success_delete") }}' }); @endif
    @if(session('error')) Toast.fire({ icon: 'error', title: '{{ session("error") }}' }); @endif

    function copyText(text) {
        navigator.clipboard.writeText(text).then(() => {
            Toast.fire({ icon: 'success', title: 'Client ID berhasil disalin!' });
        }).catch(err => {
            console.error('Gagal menyalin text: ', err);
            Toast.fire({ icon: 'error', title: 'Gagal menyalin text.' });
        });
    }

    function pingApp(id, name) {
        Swal.fire({
            title: `Pinging ${name}...`,
            text: 'Mencoba terhubung ke server target via HTTP Request.',
            allowOutsideClick: false,
            background: '#0d1929', color: '#c8d0e0',
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
                Swal.fire({ title: 'Koneksi Berhasil!', text: `Aplikasi merespons dengan status code: ${data.code}`, icon: 'success', background: '#0d1929', color: '#c8d0e0', confirmButtonColor: '#0a3a1a' });
            } else if (data.status === 'unreachable') {
                Swal.fire({ title: 'Server Unreachable', text: `Target ditemukan namun membalas dengan error code: ${data.code}`, icon: 'warning', background: '#0d1929', color: '#c8d0e0', confirmButtonColor: '#6a1a1a' });
            } else {
                Swal.fire({ title: 'Gagal Terhubung', text: data.message || 'Server target offline atau tidak dapat diakses.', icon: 'error', background: '#0d1929', color: '#c8d0e0', confirmButtonColor: '#6a1a1a' });
            }
        })
        .catch(() => {
            Swal.fire({ title: 'Error Jaringan', text: 'Terjadi kesalahan sistem saat melakukan ping.', icon: 'error', background: '#0d1929', color: '#c8d0e0', confirmButtonColor: '#6a1a1a' });
        });
    }

    function confirmDeleteApp(id, name) {
        Swal.fire({
            title: 'Hapus Aplikasi?',
            text: `Aplikasi ${name} tidak akan bisa menggunakan SSO lagi. Lanjutkan?`,
            icon: 'warning', background: '#0d1929', color: '#c8d0e0', showCancelButton: true,
            confirmButtonColor: '#6a1a1a', cancelButtonColor: '#1a2640',
            confirmButtonText: 'Ya, Hapus!', cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) { document.getElementById(`delete-app-${id}`).submit(); }
        });
    }
</script>
@endpush
