@extends('layouts.app')

@section('title', 'Settings')
@section('topbar_title', 'Administration')

@push('styles')
<style>
    .settings-grid {
        display: grid; grid-template-columns: 1fr; gap: 14px;
    }
    @media (min-width: 1024px) {
        .settings-grid { grid-template-columns: 1fr 1fr; align-items: start; }
    }

    .settings-grid.user-only { display: flex; justify-content: flex-start; }
    .settings-grid.user-only .left-panel { width: 100%; max-width: 500px; }

    .card {
        background: rgba(13,25,50,0.75);
        border: 1px solid #1a2e50;
        border-radius: 4px; padding: 14px; margin-bottom: 14px;
    }
    .card:last-child { margin-bottom: 0; }

    .card-title {
        font-size: 13px; font-weight: 600; color: #d0e4ff;
        margin-bottom: 12px; display: flex;
        justify-content: space-between; align-items: center; gap: 8px;
    }

    label { display: block; font-size: 11px; color: #4a6a90; margin-bottom: 4px; }

    input, select {
        width: 100%; padding: 8px 10px;
        background: #060e1c; border: 1px solid #1a2e50;
        color: #c8d0e0; border-radius: 3px; font-size: 12px;
        margin-bottom: 10px; min-height: 36px; box-sizing: border-box;
    }
    input[type="checkbox"] { width: auto; min-height: auto; margin: 0; cursor: pointer; accent-color: #2d5fff; }
    input:focus, select:focus { border-color: #2d5fff; outline: none; }

    .btn {
        background: #1a3a8a; border: none; color: #d0e4ff;
        padding: 8px 12px; border-radius: 3px; cursor: pointer;
        font-size: 12px; font-weight: 600; min-height: 36px;
        display: inline-flex; align-items: center; justify-content: center;
        transition: 0.15s;
    }
    .btn:hover { background: #1e4aaa; }
    .btn-full { width: 100%; }
    .btn-danger { background: #6a1a1a; color: #f0a0a0; } .btn-danger:hover { background: #8a2020; }
    .btn-gray  { background: #0f1929; border: 1px solid #1a2e50; color: #7a9bbf; } .btn-gray:hover { background: #162236; }

    .badge-admin { background: #6a1a1a; padding: 2px 6px; border-radius: 3px; font-size: 9px; font-weight: bold; color: #f0a0a0; white-space: nowrap; }
    .badge-live  { background: #1a3a8a; padding: 2px 6px; border-radius: 3px; font-size: 9px; font-weight: bold; color: #90bae0; white-space: nowrap; }
    .badge-user  { background: #0a3a1a; padding: 2px 6px; border-radius: 3px; font-size: 9px; font-weight: bold; color: #7ee787; white-space: nowrap; }

    .alert { font-size: 11px; margin-bottom: 10px; padding: 8px 10px; border-radius: 3px; }
    .alert-error { color: #c05050; background: #2a0a0a; border: 1px solid #6a1a1a; }

    .search-bar { display: flex; gap: 6px; margin-bottom: 10px; }
    .search-bar input { margin: 0; flex: 1; min-width: 0; }
    .search-bar .btn { flex-shrink: 0; }

    /* Tabel desktop */
    .table-container { overflow-x: auto; max-width: 100%; }
    .user-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 11px; }
    .user-table th {
        color: #3d5a80; padding: 6px;
        border-bottom: 1px solid #1a2e50;
        font-size: 9px; text-transform: uppercase; white-space: nowrap;
    }
    .user-table th a:hover { color: #b0c8e8 !important; }
    .user-table td { padding: 8px 6px; border-bottom: 1px solid #0f1e38; vertical-align: middle; }
    .user-table td b { color: #b0c8e8; display: block; font-size: 12px; }
    .user-table td span.email-text { color: #3d5a80; font-size: 10px; display: block; word-break: break-all; white-space: normal; }
    .user-table select { margin: 0; padding: 2px 4px; font-size: 11px; min-height: 28px; width: auto; }

    .action-btns { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; justify-content: flex-end; }
    .action-btns .btn { padding: 4px 8px; font-size: 10px; min-height: 28px; }

    /* Card user mobile */
    .user-card { display: none; }
    @media (max-width: 767px) {
        .table-container { display: none; }
        .user-card { display: block; }
    }

    .user-card-item {
        background: #060e1c; border: 1px solid #1a2e50;
        border-radius: 4px; padding: 10px 12px; margin-bottom: 8px;
    }
    .user-card-item:last-child { margin-bottom: 0; }
    .user-card-item .user-name { font-size: 13px; font-weight: 600; color: #b0c8e8; }
    .user-card-item .user-email { font-size: 10px; color: #3d5a80; word-break: break-all; margin-top: 2px; margin-bottom: 8px; }
    .user-card-item .user-meta { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 8px; }
    .user-card-item .user-actions { display: flex; gap: 6px; flex-wrap: wrap; }
    .user-card-item .user-actions .btn { padding: 5px 10px; font-size: 11px; min-height: 32px; flex: 1; }

    .active-toggle-label {
        display: inline-flex; align-items: center; gap: 4px; font-size: 10px; color: #7a9bbf; cursor: pointer;
    }
    .active-toggle-label.disabled { cursor: not-allowed; opacity: 0.4; }

    /* Log */
    .log-item { font-size: 11px; padding: 8px 0; border-bottom: 1px solid #0f1e38; }
    .log-item:last-child { border-bottom: none; padding-bottom: 0; }
    .log-item b { color: #3a7acc; }
    .log-item .log-activity { color: #5daa7a; }
    .log-item .log-detail { color: #3d5a80; font-size: 9px; margin-top: 2px; word-break: break-all; }

    /* Pagination */
    .custom-pagination { margin-top: 14px; text-align: center; font-size: 11px; color: #3d5a80; }
    .custom-pagination .btn-group { display: flex; justify-content: center; align-items: center; gap: 4px; flex-wrap: wrap; }
    .custom-pagination .page-link {
        background: #0f1929; color: #7a9bbf; padding: 4px 10px; border-radius: 3px;
        text-decoration: none; min-height: 28px; min-width: 28px;
        display: inline-flex; align-items: center; justify-content: center;
        box-sizing: border-box; font-size: 11px; transition: 0.1s;
        border: 1px solid #1a2e50;
    }
    .custom-pagination .page-link:hover:not(.disabled) { background: #162236; color: #b0c8e8; }
    .custom-pagination .page-link.active { background: #1a3a8a; color: #d0e4ff; font-weight: bold; border-color: #2d5fff; }
    .custom-pagination .page-link.disabled { opacity: 0.3; pointer-events: none; }
</style>
@endpush

@section('content')
<div class="settings-grid {{ Auth::user()->role !== 'admin' ? 'user-only' : '' }}">

    <!-- PANEL KIRI -->
    <div class="left-panel">

        <!-- Pengaturan Password -->
        <div class="card">
            <div class="card-title">Pengaturan Password</div>

            @if($errors->has('password_expired'))
                <div style="border: 2px solid #6a1a1a; background: #2a0a0a; padding: 12px; border-radius: 4px; color: #c05050; font-size: 13px; font-weight: bold; text-align: center; margin-bottom: 15px; line-height: 1.5;">
                    ⚠️ {{ $errors->first('password_expired') }}
                </div>
            @endif

            @if($errors->any() && !$errors->has('password_confirm_session') && !$errors->has('password_expired') && !$errors->has('maintenance'))
                <div class="alert alert-error">
                    @foreach($errors->all() as $error)<div>• {{ $error }}</div>@endforeach
                </div>
            @endif

            <form method="POST" action="/settings/password">
                @csrf
                <label>Password Lama</label>
                <input type="password" name="current_password" placeholder="Masukkan password saat ini">
                <label>Password Baru</label>
                <input type="password" name="password" required placeholder="Minimal 8 karakter">
                <label>Konfirmasi Password Baru</label>
                <input type="password" name="password_confirmation" required placeholder="Ulangi password baru">
                <button type="submit" class="btn btn-full">Update Password</button>
            </form>
        </div>

        <!-- Sesi Perangkat -->
        <div class="card">
            <div class="card-title">Perangkat & Sesi Aktif</div>
            <p style="font-size: 11px; color: #3d5a80; margin-bottom: 12px;">Daftar perangkat yang saat ini masuk ke akun Anda. Jika ada perangkat yang mencurigakan, segera cabut aksesnya.</p>

            @if($errors->has('password_confirm_session'))
                <div class="alert alert-error">Password yang dimasukkan untuk logout sesi salah.</div>
            @endif

            @foreach($sessions as $session)
                @php
                    $agent = $session->user_agent;
                    $device = str_contains($agent, 'Windows') ? 'Windows' : (str_contains($agent, 'Macintosh') || str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') ? 'Apple Device' : (str_contains($agent, 'Android') ? 'Android' : (str_contains($agent, 'Linux') ? 'Linux' : 'Unknown Device')));
                    $browser = str_contains($agent, 'Chrome') ? 'Chrome' : (str_contains($agent, 'Firefox') ? 'Firefox' : (str_contains($agent, 'Safari') && !str_contains($agent, 'Chrome') ? 'Safari' : (str_contains($agent, 'Edge') ? 'Edge' : 'Unknown Browser')));
                    $isCurrent = $session->id === request()->session()->getId();
                @endphp
                <div class="log-item" style="display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <b>{{ $device }} - {{ $browser }}</b>
                        @if($isCurrent) <span class="badge-live" style="margin-left: 4px;">SESI INI</span> @endif
                        <div class="log-detail">IP: {{ $session->ip_address }} · {{ \Carbon\Carbon::createFromTimestamp($session->last_activity)->format('d/m/Y H:i:s') }} ({{ \Carbon\Carbon::createFromTimestamp($session->last_activity)->diffForHumans() }})</div>
                    </div>
                </div>
            @endforeach

            @if(count($sessions) > 1)
            <form action="/settings/sessions/destroy" method="POST" style="margin-top: 14px;" id="destroySessionsForm">
                @csrf
                <button type="button" class="btn btn-danger btn-full" onclick="confirmDestroySessions()">Logout dari Perangkat Lain</button>
            </form>
            @endif
        </div>

        @if(Auth::user()->role === 'admin')
        <!-- Audit Aktivitas Admin -->
        <div class="card">
            <div class="card-title">
                <span>Audit Aktivitas Admin</span>
                <span class="badge-live">LIVE</span>
            </div>

            @if(method_exists($logs, 'isEmpty') ? $logs->isEmpty() : $logs->count() === 0)
                <div style="font-size:11px;color:#3d5a80;text-align:center;padding:8px 0;">Belum ada aktivitas admin dalam 1 bulan terakhir.</div>
            @else
                @foreach($logs as $log)
                    <div class="log-item">
                        <div>
                            <b>{{ $log->admin_name }}</b>: {{ $log->action }}
                            @if($log->target_user && $log->target_user !== '-') ke <i>{{ $log->target_user }}</i> @endif
                        </div>
                        <div class="log-detail">{{ \Carbon\Carbon::parse($log->created_at)->format('d/m/Y H:i:s') }} ({{ \Carbon\Carbon::parse($log->created_at)->diffForHumans() }})</div>
                    </div>
                @endforeach

                @if($logs->hasPages())
                    <div class="custom-pagination">
                        <div class="btn-group">
                            <a href="{{ $logs->url(1) }}" class="page-link {{ $logs->onFirstPage() ? 'disabled' : '' }}">First</a>
                            <a href="{{ $logs->previousPageUrl() ?? '#' }}" class="page-link {{ $logs->onFirstPage() ? 'disabled' : '' }}">«</a>
                            @php $start = max(1, $logs->currentPage() - 2); $end = min($logs->lastPage(), $logs->currentPage() + 2); @endphp
                            @for ($i = $start; $i <= $end; $i++)
                                <a href="{{ $logs->url($i) }}" class="page-link {{ ($logs->currentPage() == $i) ? 'active' : '' }}">{{ $i }}</a>
                            @endfor
                            <a href="{{ $logs->nextPageUrl() ?? '#' }}" class="page-link {{ !$logs->hasMorePages() ? 'disabled' : '' }}">»</a>
                            <a href="{{ $logs->url($logs->lastPage()) }}" class="page-link {{ ($logs->currentPage() == $logs->lastPage() || $logs->lastPage() == 0) ? 'disabled' : '' }}">Last</a>
                        </div>
                    </div>
                @endif
            @endif
        </div>
        @endif

        @if(strtolower(Auth::user()->email) === 'admin@dummy.com')
        <!-- Mode Perbaikan Sistem -->
        <div class="card" style="border: 1px solid #6a1a1a; background: rgba(42,10,10,0.6);">
            <div class="card-title">
                <span style="color: #c05050;">Mode Perbaikan Sistem</span>
                <span class="badge-admin">SUPER ADMIN</span>
            </div>

            @if($errors->has('maintenance'))
                <div class="alert alert-error">{{ $errors->first('maintenance') }}</div>
            @endif

            <p style="font-size: 11px; color: #7a9bbf; margin-bottom: 12px;">Aktifkan mode ini untuk menutup portal dari semua pengguna (termasuk memblokir SSO) saat melakukan pembaruan atau backup database. Anda tidak akan ter-logout.</p>

            <form action="/settings/admin/toggle-maintenance" method="POST" id="maintenanceForm">
                @csrf
                <button type="button" class="btn btn-full" style="background: {{ $isMaintenance ? '#0a3a1a' : '#6a1a1a' }}; color: {{ $isMaintenance ? '#7ee787' : '#f0a0a0' }};" onclick="confirmMaintenance({{ $isMaintenance ? 'true' : 'false' }})">
                    {{ $isMaintenance ? 'NONAKTIFKAN Mode Perbaikan' : 'AKTIFKAN Mode Perbaikan' }}
                </button>
            </form>
        </div>
        @endif

    </div>

    <!-- PANEL KANAN -->
    @if(Auth::user()->role === 'admin')
    <div class="right-panel">

        <!-- Manajemen Pengguna -->
        <div class="card">
            <div class="card-title">
                <span>Manajemen Pengguna</span>
                <span class="badge-admin">ADMIN</span>
            </div>

            <form method="GET" action="/settings" class="search-bar" id="searchForm">
                <input type="text" name="search" placeholder="Cari nama/email..." value="{{ request('search') }}">
                <button type="submit" class="btn">Cari</button>
                @if(request()->has('search') || request()->has('sort_by'))
                    <a href="/settings" class="btn btn-gray" style="text-decoration:none;">Reset</a>
                @endif
            </form>

            {{-- TABEL: Desktop --}}
            <div class="table-container">
                <table class="user-table">
                    <thead>
                        <tr>
                            @php
                                function sortLink($column, $label) {
                                    $currentSort = request('sort_by', 'name');
                                    $currentOrder = request('order', 'asc');
                                    $order = ($currentSort === $column && $currentOrder === 'asc') ? 'desc' : 'asc';
                                    $icon = ($currentSort === $column) ? ($currentOrder === 'asc' ? '↑' : '↓') : '↕';
                                    $url = request()->fullUrlWithQuery(['sort_by' => $column, 'order' => $order, 'page' => 1]);
                                    return "<a href=\"{$url}\" style=\"color:#3d5a80; text-decoration:none; display:flex; align-items:center; gap:4px;\">{$label} <span style=\"font-size:10px;\">{$icon}</span></a>";
                                }
                            @endphp
                            <th>{!! sortLink('name', 'User') !!}</th>
                            <th>{!! sortLink('is_active', 'Status') !!}</th>
                            <th>{!! sortLink('role', 'Role') !!}</th>
                            <th style="text-align: right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $u)
                        @php
                            $isAdminDummy = strtolower($u->email) === 'admin@dummy.com';
                            $isSelf       = $u->id === Auth::id();
                        @endphp
                        <tr>
                            <td>
                                <b>{{ $u->name }}</b>
                                <span class="email-text">{{ $u->email }}</span>
                            </td>
                            <td>
                                <form action="/settings/admin/toggle-active" method="POST" id="form-active-{{ $u->id }}">
                                    @csrf
                                    <input type="hidden" name="user_id" value="{{ $u->id }}">
                                    <input type="hidden" name="is_active" value="0">
                                    <label class="active-toggle-label {{ ($isAdminDummy || $isSelf) ? 'disabled' : '' }}">
                                        <input type="checkbox" name="is_active" value="1"
                                            {{ (!isset($u->is_active) || $u->is_active) ? 'checked' : '' }}
                                            {{ ($isAdminDummy || $isSelf) ? 'disabled' : '' }}
                                            onchange="document.getElementById('form-active-{{ $u->id }}').submit()">
                                        <span style="color: {{ (!isset($u->is_active) || $u->is_active) ? '#5daa7a' : '#c05050' }}; font-weight: bold;">
                                            {{ (!isset($u->is_active) || $u->is_active) ? 'Aktif' : 'Deaktif' }}
                                        </span>
                                    </label>
                                </form>
                            </td>
                            <td>
                                @if($isAdminDummy)
                                    <span style="color: #c05050; font-weight: bold; font-size: 10px;">ADMINISTRATOR</span>
                                @else
                                    <form action="/settings/admin/update-role" method="POST">
                                        @csrf
                                        <input type="hidden" name="user_id" value="{{ $u->id }}">
                                        <select name="role" onchange="this.form.submit()" {{ $isSelf ? 'disabled' : '' }}>
                                            <option value="user"  {{ $u->role=='user'  ? 'selected' : '' }}>User</option>
                                            <option value="guest" {{ $u->role=='guest' ? 'selected' : '' }}>Guest</option>
                                        </select>
                                    </form>
                                @endif
                            </td>
                            <td>
                                <div class="action-btns">
                                    @if(!$isAdminDummy)
                                        <button type="button" class="btn" style="background: transparent; border: 1px solid #2d5fff; color: #6a9acc;" onclick="manageApps('{{ $u->id }}', '{{ $u->name }}')">Akses</button>
                                    @endif
                                    @if(!$isAdminDummy || $isSelf)
                                        <button type="button" onclick="promptReset('{{ $u->id }}', '{{ $u->name }}')" class="btn btn-gray">Reset</button>
                                    @endif
                                    @if(!$isAdminDummy && !$isSelf)
                                    <form action="/settings/admin/delete-user" method="POST" id="delete-form-{{ $u->id }}">
                                        @csrf
                                        <input type="hidden" name="user_id" value="{{ $u->id }}">
                                        <button type="button" onclick="confirmDelete('{{ $u->id }}', '{{ $u->name }}')" class="btn btn-danger">Hapus</button>
                                    </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- CARD: Mobile --}}
            <div class="user-card">
                @foreach($users as $u)
                @php
                    $isAdminDummy = strtolower($u->email) === 'admin@dummy.com';
                    $isSelf       = $u->id === Auth::id();
                @endphp
                <div class="user-card-item">
                    <div class="user-name">{{ $u->name }}</div>
                    <div class="user-email">{{ $u->email }}</div>

                    <div class="user-meta">
                        <form action="/settings/admin/toggle-active" method="POST" id="form-active-mob-{{ $u->id }}">
                            @csrf
                            <input type="hidden" name="user_id" value="{{ $u->id }}">
                            <input type="hidden" name="is_active" value="0">
                            <label class="active-toggle-label {{ ($isAdminDummy || $isSelf) ? 'disabled' : '' }}">
                                <input type="checkbox" name="is_active" value="1"
                                    {{ (!isset($u->is_active) || $u->is_active) ? 'checked' : '' }}
                                    {{ ($isAdminDummy || $isSelf) ? 'disabled' : '' }}
                                    onchange="document.getElementById('form-active-mob-{{ $u->id }}').submit()">
                                <span style="color: {{ (!isset($u->is_active) || $u->is_active) ? '#5daa7a' : '#c05050' }}; font-weight: bold;">
                                    {{ (!isset($u->is_active) || $u->is_active) ? 'Aktif' : 'Deaktif' }}
                                </span>
                            </label>
                        </form>

                        @if($isAdminDummy)
                            <span style="color: #c05050; font-weight: bold; font-size: 10px;">ADMINISTRATOR</span>
                        @else
                            <form action="/settings/admin/update-role" method="POST">
                                @csrf
                                <input type="hidden" name="user_id" value="{{ $u->id }}">
                                <select name="role" onchange="this.form.submit()" {{ $isSelf ? 'disabled' : '' }} style="margin:0; padding: 2px 4px; font-size: 11px; min-height: 28px; width: auto;">
                                    <option value="user"  {{ $u->role=='user'  ? 'selected' : '' }}>User</option>
                                    <option value="guest" {{ $u->role=='guest' ? 'selected' : '' }}>Guest</option>
                                </select>
                            </form>
                        @endif
                    </div>

                    <div class="user-actions">
                        @if(!$isAdminDummy)
                            <button type="button" class="btn" style="background: transparent; border: 1px solid #2d5fff; color: #6a9acc;" onclick="manageApps('{{ $u->id }}', '{{ $u->name }}')">Akses</button>
                        @endif
                        @if(!$isAdminDummy || $isSelf)
                            <button type="button" onclick="promptReset('{{ $u->id }}', '{{ $u->name }}')" class="btn btn-gray">Reset</button>
                        @endif
                        @if(!$isAdminDummy && !$isSelf)
                        <form action="/settings/admin/delete-user" method="POST" id="delete-form-mob-{{ $u->id }}" style="flex:1;">
                            @csrf
                            <input type="hidden" name="user_id" value="{{ $u->id }}">
                            <button type="button" onclick="confirmDelete('{{ $u->id }}', '{{ $u->name }}')" class="btn btn-danger btn-full">Hapus</button>
                        </form>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>

            @if($users->hasPages())
                <div class="custom-pagination">
                    <div class="btn-group">
                        <a href="{{ $users->url(1) }}" class="page-link {{ $users->onFirstPage() ? 'disabled' : '' }}">First</a>
                        <a href="{{ $users->previousPageUrl() ?? '#' }}" class="page-link {{ $users->onFirstPage() ? 'disabled' : '' }}">«</a>
                        @php $start = max(1, $users->currentPage() - 2); $end = min($users->lastPage(), $users->currentPage() + 2); @endphp
                        @for ($i = $start; $i <= $end; $i++)
                            <a href="{{ $users->url($i) }}" class="page-link {{ ($users->currentPage() == $i) ? 'active' : '' }}">{{ $i }}</a>
                        @endfor
                        <a href="{{ $users->nextPageUrl() ?? '#' }}" class="page-link {{ !$users->hasMorePages() ? 'disabled' : '' }}">»</a>
                        <a href="{{ $users->url($users->lastPage()) }}" class="page-link {{ ($users->currentPage() == $logs->lastPage() || $users->lastPage() == 0) ? 'disabled' : '' }}">Last</a>
                    </div>
                </div>
            @endif
        </div>

        <!-- Log Aktivitas Pengguna -->
        <div class="card">
            <div class="card-title">
                <span>Log Aktivitas Pengguna</span>
                <span class="badge-user">USER</span>
            </div>

            @if(method_exists($userLogs, 'isEmpty') ? $userLogs->isEmpty() : $userLogs->count() === 0)
                <div style="font-size:11px;color:#3d5a80;text-align:center;padding:8px 0;">Belum ada aktivitas pengguna dalam 1 bulan terakhir.</div>
            @else
                @foreach($userLogs as $log)
                    <div class="log-item">
                        <div>
                            <b>{{ $log->user_name }}</b> <span class="log-activity">— {{ $log->activity }}</span>
                        </div>
                        <div class="log-detail">{{ $log->user_email }} · IP: {{ $log->ip_address }} · {{ \Carbon\Carbon::parse($log->created_at)->format('d/m/Y H:i:s') }} ({{ \Carbon\Carbon::parse($log->created_at)->diffForHumans() }})</div>
                    </div>
                @endforeach

                @if($userLogs->hasPages())
                    <div class="custom-pagination">
                        <div class="btn-group">
                            <a href="{{ $userLogs->url(1) }}" class="page-link {{ $userLogs->onFirstPage() ? 'disabled' : '' }}">First</a>
                            <a href="{{ $userLogs->previousPageUrl() ?? '#' }}" class="page-link {{ $userLogs->onFirstPage() ? 'disabled' : '' }}">«</a>
                            @php $start = max(1, $userLogs->currentPage() - 2); $end = min($userLogs->lastPage(), $userLogs->currentPage() + 2); @endphp
                            @for ($i = $start; $i <= $end; $i++)
                                <a href="{{ $userLogs->url($i) }}" class="page-link {{ ($userLogs->currentPage() == $i) ? 'active' : '' }}">{{ $i }}</a>
                            @endfor
                            <a href="{{ $userLogs->nextPageUrl() ?? '#' }}" class="page-link {{ !$userLogs->hasMorePages() ? 'disabled' : '' }}">»</a>
                            <a href="{{ $userLogs->url($userLogs->lastPage()) }}" class="page-link {{ ($userLogs->currentPage() == $userLogs->lastPage() || $userLogs->lastPage() == 0) ? 'disabled' : '' }}">Last</a>
                        </div>
                    </div>
                @endif
            @endif
        </div>

    </div>
    @endif
</div>

@if(Auth::user()->role === 'admin')
<form id="resetForm" action="/settings/admin/reset-password" method="POST" style="display:none;">
    @csrf
    <input type="hidden" name="user_id" id="uid">
    <input type="hidden" name="new_password" id="npass">
</form>
@endif

@endsection

@push('scripts')
<script>
    const Toast = Swal.mixin({
        toast: true, position: 'top-end', showConfirmButton: false,
        timer: 4000, timerProgressBar: true, background: '#0d1929', color: '#c8d0e0',
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });

    @if(session('success'))      Toast.fire({ icon: 'success', title: '{{ session("success") }}' }); @endif
    @if(session('success_user')) Toast.fire({ icon: 'success', title: '{{ session("success_user") }}' }); @endif

    window.addEventListener('load', function() {
        if (performance.navigation.type === 1) {
            const url = new URL(window.location.href);
            if (url.searchParams.toString() !== '') {
                window.location.href = window.location.pathname;
            }
        }
    });

    function manageApps(id, name) {
        Swal.fire({ title: 'Memuat data...', allowOutsideClick: false, background: '#0d1929', color: '#c8d0e0', didOpen: () => { Swal.showLoading(); } });

        fetch(`/settings/admin/user-apps/${id}`)
        .then(res => res.json())
        .then(data => {
            if (!data.success) throw new Error('Gagal');
            let html = `<div style="text-align:left; margin-top:10px; max-height: 200px; overflow-y: auto;">`;
            if (data.all_apps.length === 0) {
                html += `<div style="color:#3d5a80; font-size:12px;">Belum ada aplikasi SSO yang aktif.</div>`;
            } else {
                data.all_apps.forEach(app => {
                    const isChecked = data.user_apps.includes(app.id) ? 'checked' : '';
                    html += `<label style="display:flex; align-items:center; gap:10px; margin-bottom:12px; cursor:pointer; color:#c8d0e0; font-size:13px; background:#060e1c; padding:10px; border-radius:4px; border:1px solid #1a2e50;">
                        <input type="checkbox" name="app_ids[]" value="${app.id}" class="app-checkbox" ${isChecked} style="width:16px; height:16px; margin:0; accent-color:#2d5fff; cursor:pointer;">
                        ${app.name}
                    </label>`;
                });
            }
            html += `</div>`;
            Swal.fire({
                title: `Akses Aplikasi: ${name}`, html: html,
                background: '#0d1929', color: '#c8d0e0', showCancelButton: true,
                confirmButtonText: 'Simpan', cancelButtonText: 'Batal',
                confirmButtonColor: '#1a3a8a', cancelButtonColor: '#1a2640',
            }).then((result) => {
                if (result.isConfirmed) {
                    const checkboxes = document.querySelectorAll('.app-checkbox:checked');
                    const selectedApps = Array.from(checkboxes).map(cb => cb.value);
                    let form = document.createElement('form');
                    form.method = 'POST'; form.action = '/settings/admin/user-apps';
                    let csrf = document.createElement('input'); csrf.type = 'hidden'; csrf.name = '_token'; csrf.value = '{{ csrf_token() }}'; form.appendChild(csrf);
                    let uid = document.createElement('input'); uid.type = 'hidden'; uid.name = 'user_id'; uid.value = id; form.appendChild(uid);
                    selectedApps.forEach(appId => {
                        let input = document.createElement('input'); input.type = 'hidden'; input.name = 'apps[]'; input.value = appId; form.appendChild(input);
                    });
                    document.body.appendChild(form); form.submit();
                }
            });
        })
        .catch(() => {
            Swal.fire({ title: 'Error', text: 'Gagal memuat data.', icon: 'error', background: '#0d1929', color: '#c8d0e0' });
        });
    }

    function confirmDestroySessions() {
        Swal.fire({
            title: 'Logout Perangkat Lain?', text: 'Masukkan password Anda untuk mengonfirmasi tindakan ini:',
            input: 'password', inputPlaceholder: 'Password saat ini',
            background: '#0d1929', color: '#c8d0e0', showCancelButton: true,
            confirmButtonText: 'Logout Perangkat', cancelButtonText: 'Batal',
            confirmButtonColor: '#6a1a1a', cancelButtonColor: '#1a2640',
            inputValidator: (v) => { if (!v) return 'Password wajib diisi!'; }
        }).then((result) => {
            if (result.isConfirmed) {
                let form = document.getElementById('destroySessionsForm');
                let input = document.createElement('input');
                input.type = 'hidden'; input.name = 'password'; input.value = result.value;
                form.appendChild(input); form.submit();
            }
        });
    }

    @if(strtolower(Auth::user()->email) === 'admin@dummy.com')
    function confirmMaintenance(isCurrentlyActive) {
        let title    = isCurrentlyActive ? 'Buka Kembali Portal?' : 'Tutup Portal Sekarang?';
        let text     = isCurrentlyActive ? 'Portal akan kembali online dan semua pengguna dapat login.' : 'Semua pengguna akan langsung dialihkan ke halaman Perbaikan. Apakah Anda yakin?';
        let btnColor = isCurrentlyActive ? '#0a3a1a' : '#6a1a1a';
        let btnText  = isCurrentlyActive ? 'Ya, Online-kan' : 'Ya, Tutup Portal';
        Swal.fire({
            title: title, text: text, icon: 'warning',
            background: '#0d1929', color: '#c8d0e0', showCancelButton: true,
            confirmButtonColor: btnColor, cancelButtonColor: '#1a2640',
            confirmButtonText: btnText, cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) { document.getElementById('maintenanceForm').submit(); }
        });
    }
    @endif

    @if(Auth::user()->role === 'admin')
    function promptReset(id, name) {
        Swal.fire({
            title: 'Reset Password', text: `Masukkan password baru untuk ${name}:`,
            input: 'password', inputPlaceholder: 'Minimal 8 karakter',
            background: '#0d1929', color: '#c8d0e0', showCancelButton: true,
            confirmButtonText: 'Simpan', cancelButtonText: 'Batal',
            confirmButtonColor: '#1a3a8a', cancelButtonColor: '#1a2640',
            inputValidator: (v) => { if (!v || v.length < 8) return 'Password harus minimal 8 karakter!'; }
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('uid').value = id;
                document.getElementById('npass').value = result.value;
                document.getElementById('resetForm').submit();
            }
        });
    }

    function confirmDelete(id, name) {
        Swal.fire({
            title: 'Hapus Akun?', text: `Apakah Anda yakin ingin menghapus ${name}?`,
            icon: 'warning', background: '#0d1929', color: '#c8d0e0', showCancelButton: true,
            confirmButtonColor: '#6a1a1a', cancelButtonColor: '#1a2640',
            confirmButtonText: 'Ya, Hapus!', cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.getElementById(`delete-form-${id}`) || document.getElementById(`delete-form-mob-${id}`);
                if (form) form.submit();
            }
        });
    }
    @endif
</script>
@endpush
