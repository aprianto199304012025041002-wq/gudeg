@extends('layouts.app')

@section('title', 'Dashboard')
@section('topbar_title', 'GUDEG JOGJA Dashboard')

@push('styles')
<style>
    .welcome-header { margin-bottom: 16px; }
    .welcome-header h1 {
        font-size: 15px; color: #fff;
        margin-bottom: 6px; font-weight: 600; line-height: 1.4;
    }
    .welcome-header p { font-size: 11px; color: #858585; line-height: 1.5; }

    .info-card {
        background: #252526; border: 1px solid #3c3c3c;
        border-radius: 4px; padding: 12px; margin-bottom: 16px;
    }
    .info-card p { font-size: 11px; color: #bdbdbd; line-height: 1.5; }
    .status-box {
        margin-top: 8px; background: #122d15; border: 1px solid #1f6f38;
        padding: 5px 8px; border-radius: 3px;
        color: #7ee787; font-size: 10px; display: inline-block;
    }
    .status-box.warning {
        background: #3b1214; border-color: #f85149; color: #ff7b72;
    }

    .app-section-title {
        font-size: 10px; font-weight: 600; color: #858585;
        margin-bottom: 12px; text-transform: uppercase; letter-spacing: 1px;
    }

    .app-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 14px;
    }

    /* Layout Bertingkat: Atas (Ikon+Nama) & Bawah (Deskripsi+ID) */
    .app-card {
        background: #252526; border: 1px solid #3c3c3c;
        border-radius: 4px; padding: 14px;
        text-decoration: none; color: inherit;
        display: flex; flex-direction: column; gap: 10px;
        width: 100%; box-sizing: border-box;
        transition: border-color 0.15s, background 0.15s;
    }
    .app-card:active { border-color: #007acc; background: #2d2d2e; }
    @media (hover: hover) { .app-card:hover { border-color: #007acc; background: #2d2d2e; } }

    /* Bagian Atas Card */
    .app-card-header {
        display: flex; align-items: center; gap: 12px;
        border-bottom: 1px solid #333; padding-bottom: 10px;
    }
    .app-thumb {
        width: 46px; height: 46px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        background: #1e1e1e; border-radius: 4px; padding: 4px;
        border: 1px solid #3c3c3c;
    }
    .app-thumb img {
        width: 100%; height: 100%;
        object-fit: contain; display: block;
    }
    .app-name {
        font-weight: 600; font-size: 14px; color: #fff;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        word-break: break-word;
        line-height: 1.3;
    }

    /* Bagian Bawah Card */
    .app-card-body {
        display: flex; flex-direction: column; gap: 6px;
    }
    .app-desc {
        font-size: 11px; color: #aaaaaa; line-height: 1.5;
    }
    .app-id {
        font-size: 10px; color: #666; font-family: monospace;
        word-break: break-all;
    }

    .empty-state {
        grid-column: 1/-1; text-align: center; padding: 40px; 
        border: 1px dashed #3c3c3c; border-radius: 4px; color: #858585;
    }
</style>
@endpush

@section('content')

@php 
    $isGuest    = Auth::user()->role === 'guest';
    $isDeactive = isset(Auth::user()->is_active) && !Auth::user()->is_active;
    $noAppAccess = $isGuest || $isDeactive;
@endphp

<div class="welcome-header">
    <h1>Selamat Datang di GUDEG JOGJA, {{ Auth::user()->name }}!</h1>
    <p>General Unified Database and Employee Access Gateway. Pusat Autentikasi Single Sign-On (SSO) Balai Monitor Spektrum Frekuensi Radio Kelas I Yogyakarta.</p>
</div>

<div class="info-card">
    <p>Anda login menggunakan akun <b>{{ Auth::user()->email }}</b> dengan Hak Akses: <b style="color:#007acc;">{{ strtoupper(Auth::user()->role) }}</b>.</p>
    
    @if($noAppAccess)
        <p style="color: #ff7b72; margin-top: 8px;">
            ⚠️ <b>Akses Aplikasi Terkunci:</b> Akun Anda saat ini berstatus {{ $isGuest ? 'GUEST' : 'DINONAKTIFKAN' }}. Anda hanya dapat mengakses menu Settings untuk mengubah password. Silakan hubungi Administrator agar dapat menggunakan layanan aplikasi terintegrasi.
        </p>
        <div class="status-box warning">✕ Akses Aplikasi SSO Dibatasi</div>
    @else
        <p>Pilih aplikasi di bawah ini untuk masuk secara otomatis.</p>
        <div class="status-box">✓ Sesi SSO Aktif & Berjalan Normal</div>
    @endif
</div>

{{-- JIKA BUKAN GUEST & BUKAN DEAKTIF, BARU TAMPILKAN APLIKASI --}}
@if(!$noAppAccess)

<div class="app-section-title">Aplikasi Terintegrasi</div>

<div class="app-grid">
    
    @forelse($applications as $app)
        @if(!isset($app->is_active) || $app->is_active)
        <a href="/sso/authorize?redirect={{ urlencode($app->redirect) }}" class="app-card" target="_blank" rel="noopener noreferrer">
            
            <div class="app-card-header">
                <div class="app-thumb">
                    @if(!empty($app->logo))
                        <img src="/{{ $app->logo }}" alt="{{ $app->name }}" onerror="this.outerHTML='<span style=\'font-size: 20px;\'>🚀</span>'">
                    @else
                        <span style="font-size: 20px;">🚀</span>
                    @endif
                </div>
                <div class="app-name">{{ $app->name }}</div>
            </div>

            <div class="app-card-body">
                <div class="app-desc">{{ $app->description ?? 'Aplikasi SSO Terintegrasi.' }}</div>
                <div class="app-id">ID: {{ $app->client_id }}</div>
            </div>

        </a>
        @endif
    @empty
        <div class="empty-state">
            Belum ada aplikasi yang aktif terintegrasi dengan SSO.
        </div>
    @endforelse

</div>

@endif

@endsection
