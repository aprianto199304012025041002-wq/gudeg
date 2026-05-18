<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>@yield('title') | Portal Balmon Jogja</title>

    <link rel="stylesheet" href="{{ asset('css/portal.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-dark@5/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        html, body {
            width: 100%;
            overflow-x: hidden;
        }

        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: #08101e;
            color: #c8d0e0;
            font-size: 13px;
            display: flex;
            min-height: 100vh;
        }

        /* Overlay */
        .overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.65);
            z-index: 40;
        }
        .overlay.active { display: block; }

        /* Sidebar */
        .sidebar {
            width: 200px;
            background: #0d1220;
            display: flex;
            flex-direction: column;
            height: 100vh;
            position: fixed;
            top: 0; left: 0;
            transition: transform 0.2s ease, width 0.2s ease;
            overflow: hidden;
            border-right: 1px solid #1a2640;
            z-index: 50;
            flex-shrink: 0;
        }
        .sidebar.collapsed { width: 40px; }

        @media (max-width: 768px) {
            .sidebar {
                width: 75vw !important;
                max-width: 260px !important;
                transform: translateX(-100%);
                box-shadow: none;
            }
            .sidebar.mobile-open {
                transform: translateX(0);
                box-shadow: 4px 0 20px rgba(0,0,0,0.6);
            }
        }

        .sidebar-header {
            display: flex; align-items: center; justify-content: flex-start;
            padding: 8px 16px;
            background: #111827;
            border-bottom: 1px solid #1a2640;
            height: 44px; flex-shrink: 0;
        }

        .sidebar.collapsed:not(.mobile-open) .sidebar-header {
            justify-content: center;
            padding: 8px 0;
        }

        .toggle-btn {
            background: none; border: none; cursor: pointer;
            padding: 8px; display: flex; flex-direction: column; gap: 5px;
            touch-action: manipulation;
        }
        .toggle-btn:hover .bar { background: #a0c0e0; }
        .bar {
            width: 18px; height: 2px;
            background: #4a6a90; display: block;
            transition: background 0.2s;
        }

        .sidebar-user {
            display: flex; align-items: center; gap: 10px;
            padding: 12px 10px;
            border-bottom: 1px solid #1a2640;
            background: #0f1929;
            overflow: hidden; flex-shrink: 0;
        }
        .sidebar.collapsed:not(.mobile-open) .sidebar-user {
            justify-content: center; padding: 10px 0;
        }
        .sidebar-avatar {
            width: 32px; height: 32px;
            background: #0e2346;
            border: 1px solid #1e3a6e;
            border-radius: 3px;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; color: #7ab3f0; font-weight: 900; flex-shrink: 0;
            box-shadow: 0 0 8px rgba(30,90,180,0.25);
        }
        .sidebar-user-info { overflow: hidden; min-width: 0; }
        .sidebar.collapsed:not(.mobile-open) .sidebar-user-info { display: none; }
        .sidebar-user-info .name {
            font-size: 12px; font-weight: 600; color: #b8c8e0;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .sidebar-user-info .email {
            font-size: 10px; color: #3d5a80;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        .menu-label {
            font-size: 10px; text-transform: uppercase; letter-spacing: 1px;
            color: #3d5a80; padding: 14px 10px 6px;
            white-space: nowrap; transition: opacity 0.2s;
        }
        .sidebar.collapsed:not(.mobile-open) .menu-label { opacity: 0; }

        .sidebar-menu { flex-shrink: 0; }
        .sidebar-menu a {
            display: flex; align-items: center; gap: 10px;
            padding: 12px 10px;
            color: #7a9bbf; text-decoration: none; font-size: 13px;
            white-space: nowrap; transition: background 0.1s;
            border-left: 2px solid transparent;
            touch-action: manipulation;
            min-height: 44px;
        }
        .sidebar-menu a:hover { background: #111d30; color: #c0d8f0; }
        .sidebar-menu a.active {
            background: #0e1f3d;
            border-left: 2px solid #2d5fff;
            color: #d0e4ff;
        }
        .sidebar-menu .icon {
            font-size: 16px; flex-shrink: 0; width: 20px; text-align: center;
        }
        .sidebar-menu .label { transition: opacity 0.2s; }
        .sidebar.collapsed:not(.mobile-open) .sidebar-menu .label {
            opacity: 0; width: 0; overflow: hidden;
        }
        .sidebar.collapsed:not(.mobile-open) .sidebar-menu a {
            justify-content: center; padding: 12px 0;
        }

        .sidebar-footer { border-top: 1px solid #1a2640; padding: 8px 10px; flex-shrink: 0; }
        .sidebar-footer form button {
            display: flex; align-items: center; gap: 10px;
            width: 100%; padding: 10px 0;
            background: none; border: none; color: #7a2020;
            cursor: pointer; font-size: 13px; white-space: nowrap;
            transition: color 0.1s; touch-action: manipulation;
            min-height: 44px;
        }
        .sidebar-footer form button:hover { color: #c03030; }
        .sidebar-footer .label { transition: opacity 0.2s; }
        .sidebar.collapsed:not(.mobile-open) .sidebar-footer .label {
            opacity: 0; width: 0; overflow: hidden;
        }
        .sidebar.collapsed:not(.mobile-open) .sidebar-footer form button {
            justify-content: center;
        }

        /* Main */
        .main {
            margin-left: 200px;
            flex: 1;
            display: flex;
            flex-direction: column;
            transition: margin-left 0.2s ease;
            min-width: 0;
            width: calc(100% - 200px);
            background: #08101e;
            position: relative;
        }

        /* Grid background di main content */
        .main::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(40,80,140,0.07) 1px, transparent 1px),
                linear-gradient(90deg, rgba(40,80,140,0.07) 1px, transparent 1px);
            background-size: 48px 48px;
            pointer-events: none;
            z-index: 0;
        }

        /* Radial glow di tengah */
        .main::after {
            content: '';
            position: fixed;
            inset: 0;
            background: radial-gradient(ellipse at 60% 40%, rgba(13,40,90,0.5) 0%, rgba(4,8,18,0.0) 68%);
            pointer-events: none;
            z-index: 0;
        }

        .main.collapsed { margin-left: 40px; width: calc(100% - 40px); }

        @media (max-width: 768px) {
            .main {
                margin-left: 0 !important;
                width: 100% !important;
            }
        }

        /* Topbar */
        .topbar {
            background: #0f1626;
            padding: 0 12px;
            display: flex;
            align-items: center;
            border-bottom: 1px solid #1a2640;
            height: 44px;
            position: sticky;
            top: 0;
            z-index: 30;
            gap: 10px;
            width: 100%;
        }
        .topbar-title {
            font-size: 13px; font-weight: 600; color: #7a9bbf;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            flex: 1;
        }
        .topbar-title .brand-gudeg { color: #ffffff; }
        .topbar-title .brand-jogja  { color: #e8b84b; }

        .mobile-menu-btn {
            display: none;
            background: none; border: none; cursor: pointer;
            padding: 8px; flex-direction: column; gap: 5px;
            touch-action: manipulation; flex-shrink: 0;
            min-width: 44px; min-height: 44px;
            align-items: center; justify-content: center;
        }
        .mobile-menu-btn .bar { width: 18px; height: 2px; background: #4a6a90; display: block; }

        @media (max-width: 768px) {
            .mobile-menu-btn { display: flex; }
            .sidebar-header { display: none; }
        }

        /* Content */
        .content {
            padding: 14px;
            flex: 1;
            padding-bottom: 44px;
            overflow-x: hidden;
            width: 100%;
            position: relative;
            z-index: 1;
        }

        @media (max-width: 480px) {
            .content { padding: 12px; padding-bottom: 44px; }
        }

        /* Statusbar */
        .statusbar {
            background: #1a3a8a;
            padding: 3px 12px;
            font-size: 11px;
            color: rgba(255,255,255,0.9);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: fixed;
            bottom: 0;
            z-index: 20;
            left: 200px;
            right: 0;
            transition: left 0.2s ease;
            height: 22px;
            overflow: hidden;
        }
        .statusbar.collapsed { left: 40px; }
        .statusbar-left {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            flex: 1;
            min-width: 0;
        }
        .statusbar-right { flex-shrink: 0; white-space: nowrap; padding-left: 8px; }
        .statusbar .brand-gudeg { color: #ffffff; font-weight: 700; }
        .statusbar .brand-jogja  { color: #e8b84b; font-weight: 700; }

        @media (max-width: 768px) {
            .statusbar { left: 0 !important; font-size: 10px; }
        }
        @media (max-width: 480px) {
            .statusbar-left { font-size: 9px; }
            .statusbar-right { font-size: 9px; }
        }
    </style>

    @stack('styles')
</head>
<body>

    <div class="overlay" id="overlay" onclick="closeMobileSidebar()"></div>

    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <button class="toggle-btn" onclick="toggleSidebar()">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </button>
        </div>

        <div class="sidebar-user">
            <div class="sidebar-avatar">
                {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
            </div>
            <div class="sidebar-user-info">
                <div class="name">
                    {{ Auth::user()->name }}
                    @if(Auth::user()->role === 'admin')
                        <span style="color:#8b2020;">●</span>
                    @endif
                </div>
                <div class="email">{{ Auth::user()->email }}</div>
            </div>
        </div>

        <div class="sidebar-menu">
            <div class="menu-label">MENU</div>
            <a href="/dashboard" class="{{ request()->is('dashboard') ? 'active' : '' }}">
                <span class="icon">⊞</span>
                <span class="label">Dashboard</span>
            </a>
            <a href="/settings" class="{{ request()->is('settings*') ? 'active' : '' }}">
                <span class="icon">⚙</span>
                <span class="label">Settings</span>
            </a>

            @if(Auth::user()->role === 'admin')
            <a href="/apps" class="{{ request()->is('apps*') ? 'active' : '' }}">
                <span class="icon">🔌</span>
                <span class="label">Aplikasi SSO</span>
            </a>
            @endif
        </div>

        <div class="sidebar-footer">
            <form id="logout-form" method="POST" action="/logout">
                @csrf
                <button type="submit">
                    <span class="icon">⏻</span>
                    <span class="label">Logout</span>
                </button>
            </form>
        </div>
    </div>

    <div class="main" id="main">
        <div class="topbar">
            <button class="mobile-menu-btn" onclick="openMobileSidebar()">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </button>
            <div class="topbar-title">
                <span class="brand-gudeg">GUDEG</span> <span class="brand-jogja">JOGJA</span>
                &nbsp;@yield('topbar_title')
            </div>
        </div>

        <div class="content">
            @yield('content')
        </div>
    </div>

    <div class="statusbar" id="statusbar">
        <span class="statusbar-left">
            <b><span class="brand-gudeg">GUDEG</span> <span class="brand-jogja">JOGJA</span></b> |
            {{ Auth::user()->name }} - {{ Auth::user()->email }}
        </span>
        <span class="statusbar-right" id="clock"></span>
    </div>

    <script>
        function updateClock() {
            const now = new Date();
            const d = String(now.getDate()).padStart(2,'0');
            const m = String(now.getMonth()+1).padStart(2,'0');
            const y = now.getFullYear();
            const h = String(now.getHours()).padStart(2,'0');
            const mi = String(now.getMinutes()).padStart(2,'0');
            const s = String(now.getSeconds()).padStart(2,'0');
            document.getElementById('clock').textContent = `${d}/${m}/${y} | ${h}:${mi}:${s}`;
        }
        setInterval(updateClock, 1000);
        updateClock();

        // Auto logout 15 menit
        let idleSeconds = 0;
        setInterval(function() {
            idleSeconds++;
            if (idleSeconds >= 900) {
                Swal.fire({
                    title: 'Sesi Kedaluwarsa',
                    text: 'Tidak ada aktivitas selama 15 menit. Sistem otomatis keluar demi keamanan.',
                    icon: 'warning',
                    background: '#0d1929',
                    confirmButtonColor: '#1a3a8a',
                    allowOutsideClick: false
                }).then(() => {
                    document.getElementById('logout-form').submit();
                });
            }
        }, 1000);
        function resetIdleTimer() { idleSeconds = 0; }
        window.onmousemove = resetIdleTimer;
        window.onkeypress = resetIdleTimer;
        window.onclick = resetIdleTimer;
        window.onscroll = resetIdleTimer;
        window.ontouchstart = resetIdleTimer;

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const main = document.getElementById('main');
            const statusbar = document.getElementById('statusbar');
            sidebar.classList.toggle('collapsed');
            main.classList.toggle('collapsed');
            statusbar.classList.toggle('collapsed');
            localStorage.setItem('sidebar', sidebar.classList.contains('collapsed') ? 'collapsed' : 'open');
        }
        function openMobileSidebar() {
            document.getElementById('sidebar').classList.add('mobile-open');
            document.getElementById('overlay').classList.add('active');
        }
        function closeMobileSidebar() {
            document.getElementById('sidebar').classList.remove('mobile-open');
            document.getElementById('overlay').classList.remove('active');
        }
        window.onload = function() {
            const isMobile = window.innerWidth <= 768;
            if (!isMobile && localStorage.getItem('sidebar') === 'collapsed') {
                document.getElementById('sidebar').classList.add('collapsed');
                document.getElementById('main').classList.add('collapsed');
                document.getElementById('statusbar').classList.add('collapsed');
            }
            updateClock();
        }
    </script>

    @stack('scripts')
</body>
</html>
