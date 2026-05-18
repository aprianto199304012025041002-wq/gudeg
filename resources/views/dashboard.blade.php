@extends('layouts.app')

@section('title', 'Dashboard')
@section('topbar_title', 'Dashboard')

@push('styles')
<style>
    .welcome-header { margin-bottom: 16px; }
    .welcome-header h1 {
        font-size: 15px; color: #d0e4ff;
        margin-bottom: 6px; font-weight: 600; line-height: 1.4;
    }
    .welcome-header h1 .brand-gudeg { color: #ffffff; }
    .welcome-header h1 .brand-jogja  { color: #e8b84b; }
    .welcome-header p { font-size: 11px; color: #4a6a90; line-height: 1.5; }

    .info-card {
        background: rgba(13,25,50,0.7);
        border: 1px solid #1a2e50;
        border-radius: 4px; padding: 12px; margin-bottom: 16px;
    }
    .info-card p { font-size: 11px; color: #7a9bbf; line-height: 1.5; }
    .status-box {
        margin-top: 8px; background: #061a10; border: 1px solid #1a5a2e;
        padding: 5px 8px; border-radius: 3px;
        color: #5daa7a; font-size: 10px; display: inline-block;
    }
    .status-box.warning {
        background: #2a0a0a; border-color: #6a1a1a; color: #c05050;
    }

    .app-section-title {
        font-size: 10px; font-weight: 600; color: #3d5a80;
        margin-bottom: 12px; text-transform: uppercase; letter-spacing: 1px;
    }

    .app-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 14px;
    }

    .app-card {
        background: rgba(10,20,42,0.75);
        border: 1px solid #1a2e50;
        border-radius: 4px; padding: 14px;
        text-decoration: none; color: inherit;
        display: flex; flex-direction: column; gap: 10px;
        width: 100%; box-sizing: border-box;
        transition: border-color 0.15s, background 0.15s;
    }
    .app-card:active { border-color: #2d5fff; background: #0d1e3a; }
    @media (hover: hover) { .app-card:hover { border-color: #2d5fff; background: #0d1e3a; } }

    .app-card-header {
        display: flex; align-items: center; gap: 12px;
        border-bottom: 1px solid #1a2e50; padding-bottom: 10px;
    }
    .app-thumb {
        width: 46px; height: 46px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        background: #0a1830; border-radius: 4px; padding: 4px;
        border: 1px solid #1e3060;
    }
    .app-thumb img {
        width: 100%; height: 100%;
        object-fit: contain; display: block;
    }
    .app-name {
        font-weight: 600; font-size: 14px; color: #b0c8e8;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        word-break: break-word;
        line-height: 1.3;
    }

    .app-card-body {
        display: flex; flex-direction: column; gap: 6px;
    }
    .app-desc {
        font-size: 11px; color: #4a6a90; line-height: 1.5;
    }
    .app-id {
        font-size: 10px; color: #2a4060; font-family: monospace;
        word-break: break-all;
    }

    .empty-state {
        grid-column: 1/-1; text-align: center; padding: 40px;
        border: 1px dashed #1a2e50; border-radius: 4px; color: #3d5a80;
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
    <h1>Selamat Datang di <span class="brand-gudeg">GUDEG</span> <span class="brand-jogja">JOGJA</span>, {{ Auth::user()->name }}!</h1>
    <p>General Unified Database and Employee Access Gateway. Pusat Autentikasi Single Sign-On (SSO) Balai Monitor Spektrum Frekuensi Radio Kelas I Yogyakarta.</p>
</div>

<div class="info-card">
    <p>Anda login menggunakan akun <b style="color:#b0c8e8;">{{ Auth::user()->email }}</b> dengan Hak Akses: <b style="color:#3a7acc;">{{ strtoupper(Auth::user()->role) }}</b>.</p>

    @if($noAppAccess)
        <p style="color: #c05050; margin-top: 8px;">
            ⚠️ <b>Akses Aplikasi Terkunci:</b> Akun Anda saat ini berstatus {{ $isGuest ? 'GUEST' : 'DINONAKTIFKAN' }}. Anda hanya dapat mengakses menu Settings untuk mengubah password. Silakan hubungi Administrator agar dapat menggunakan layanan aplikasi terintegrasi.
        </p>
        <div class="status-box warning">✕ Akses Aplikasi SSO Dibatasi</div>
    @else
        <p>Pilih aplikasi di bawah ini untuk masuk secara otomatis.</p>
        <div class="status-box">✓ Sesi SSO Aktif & Berjalan Normal</div>
    @endif
</div>

@if(!$noAppAccess)

<div class="app-section-title">Aplikasi Terintegrasi</div>

<div class="app-grid">

    @forelse($applications as $app)
        @if(!isset($app->is_active) || $app->is_active)
        <a href="/sso/authorize?redirect={{ urlencode($app->redirect) }}" class="app-card" target="_blank" rel="noopener noreferrer">

            <div class="app-card-header">
                <div class="app-thumb">
                    @if(!empty($app->logo))
                        <img src="/{{ $app->logo }}" alt="{{ $app->name }}" loading="lazy" onerror="this.outerHTML='<span style=\'font-size: 20px;\'>🚀</span>'">
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
