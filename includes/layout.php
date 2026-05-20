<?php
/**
 * includes/layout.php
 * Shared sidebar layout helpers.
 */

function layout_head($pageTitle = 'GUDEG JOGJA', $activePage = '') {
    global $user;
    
    // Fallback jika $user tidak terdefinisi (misal belum login tapi akses page tertentu)
    $isAdmin = isset($user['role']) && $user['role'] === 'admin';
    $userName = $user['name'] ?? 'Pengguna';
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
      <meta charset="UTF-8"/>
      <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
      <title><?= htmlspecialchars($pageTitle) ?> — GUDEG JOGJA</title>
      
      <!-- Fonts: Syne (Heading/Branding) & DM Sans (Body) -->
      <link rel="preconnect" href="https://fonts.googleapis.com"/>
      <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
      <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
      
      <style>
        /* ── TOKENS ───────────────────────────────────────── */
        :root {
          --bg:         #080f1e;
          --bg2:        #0d1730;
          --bg3:        #111f3a;
          --surface:    #13233f;
          --border:     rgba(255,255,255,.07);
          --border-hi:  rgba(255,255,255,.14);
          --text:       #e2eaf8;
          --text-muted: #7a94b8;
          --accent:     #f5c518;   /* Kuning GUDEG */
          --accent-dim: rgba(245,197,24,.12);
          --blue:       #2f80ed;
          --blue-dim:   rgba(47,128,237,.15);
          --danger:     #e05252;
          --success:    #34c97e;
          
          --sidebar-w:  240px;
          --topbar-h:   56px;
          --radius:     12px;
          --font-head:  'Syne', sans-serif;
          --font-body:  'DM Sans', sans-serif;
        }

        /* ── RESET ───────────────────────────────────────── */
        *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
        html { font-size:15px; }
        body {
          font-family: var(--font-body);
          background: var(--bg);
          color: var(--text);
          min-height: 100vh;
          -webkit-font-smoothing: antialiased;
        }
        a { color: inherit; text-decoration: none; }
        button { cursor: pointer; font-family: inherit; }
        input, select, textarea { font-size: 1rem; font-family: inherit; }

        /* ── LAYOUT SHELL ────────────────────────────────── */
        #app { display:flex; min-height:100vh; }

        /* ── SIDEBAR ─────────────────────────────────────── */
        #sidebar {
          width: var(--sidebar-w);
          background: var(--bg2);
          border-right: 1px solid var(--border);
          display: flex;
          flex-direction: column;
          position: fixed;
          top: 0; left: 0;
          height: 100vh;
          z-index: 200;
          transition: transform .25s cubic-bezier(.4,0,.2,1);
        }
        #sidebar.hidden { transform: translateX(-100%); }

        .sidebar-brand {
          padding: 20px 20px 16px;
          border-bottom: 1px solid var(--border);
        }
        .sidebar-brand .brand-gudeg {
          font-family: var(--font-head);
          font-size: 1.4rem;
          font-weight: 800;
          letter-spacing: .02em;
          color: var(--accent);
        }
        .sidebar-brand .brand-jogja {
          font-family: var(--font-head);
          font-size: 1.4rem;
          font-weight: 800;
          letter-spacing: .02em;
          color: #ffffff;
        }
        .sidebar-brand .brand-tagline {
          font-size: .65rem;
          color: var(--text-muted);
          margin-top: 6px;
          letter-spacing: .02em;
          line-height: 1.4;
          text-transform: uppercase;
        }

        .sidebar-nav {
          flex: 1;
          padding: 16px 12px;
          overflow-y: auto;
          display: flex;
          flex-direction: column;
          gap: 2px;
        }
        .nav-section-label {
          font-size: .65rem;
          font-weight: 700;
          letter-spacing: .1em;
          text-transform: uppercase;
          color: rgba(122,148,184,.6);
          padding: 12px 12px 6px;
        }
        .nav-item {
          display: flex;
          align-items: center;
          gap: 10px;
          padding: 10px 12px;
          border-radius: 8px;
          color: var(--text-muted);
          font-size: .9rem;
          font-weight: 500;
          transition: background .2s, color .2s;
        }
        .nav-item:hover { background: var(--bg3); color: var(--text); }
        .nav-item.active {
          background: var(--accent-dim);
          color: var(--accent);
          font-weight: 600;
        }
        .nav-item svg { width:18px; height:18px; flex-shrink:0; }

        .sidebar-footer {
          padding: 16px 12px;
          border-top: 1px solid var(--border);
        }
        .sidebar-footer .nav-item { color: var(--danger); }
        .sidebar-footer .nav-item:hover { background: rgba(224,82,82,.1); color: var(--danger); }

        /* ── OVERLAY (mobile) ────────────────────────────── */
        #overlay {
          display: none;
          position: fixed;
          inset: 0;
          background: rgba(0,0,0,.6);
          z-index: 199;
          backdrop-filter: blur(2px);
        }
        #overlay.show { display: block; }

        /* ── TOPBAR ──────────────────────────────────────── */
        #topbar {
          position: fixed;
          top: 0; left: 0; right: 0;
          height: var(--topbar-h);
          background: rgba(13,23,48,.8);
          backdrop-filter: blur(8px);
          border-bottom: 1px solid var(--border);
          display: flex;
          align-items: center;
          padding: 0 20px;
          gap: 16px;
          z-index: 100;
        }
        @media (min-width:768px) {
          #topbar { left: var(--sidebar-w); }
        }
        #hamburger {
          background: none;
          border: none;
          color: var(--text);
          display: flex;
          padding: 6px;
          border-radius: 6px;
        }
        #hamburger:hover { background: var(--bg3); }
        @media (min-width:768px) { #hamburger { display:none; } }

        .topbar-title {
          font-family: var(--font-head);
          font-size: 1rem;
          font-weight: 700;
          color: var(--text);
        }
        .topbar-right {
          margin-left: auto;
          display: flex;
          align-items: center;
          font-size: .85rem;
          color: var(--text-muted);
        }
        .topbar-right strong { color: var(--text); margin-left: 5px; }

        /* ── MAIN CONTENT ────────────────────────────────── */
        #main {
          flex: 1;
          margin-top: var(--topbar-h);
          padding: 24px 20px;
          min-width: 0;
        }
        @media (min-width:768px) {
          #main { margin-left: var(--sidebar-w); padding: 32px; }
          #sidebar { transform: none !important; }
          #sidebar.hidden { transform: none !important; }
        }

        /* ── SHARED UI COMPONENTS ────────────────────────── */
        .page-header { margin-bottom: 24px; }
        .page-header h1 {
          font-family: var(--font-head);
          font-size: 1.5rem;
          font-weight: 700;
          color: #fff;
        }
        .page-header p {
          font-size: .85rem;
          color: var(--text-muted);
          margin-top: 4px;
        }

        .card {
          background: var(--surface);
          border: 1px solid var(--border);
          border-radius: var(--radius);
          padding: 20px;
        }
        .card + .card { margin-top: 16px; }

        /* form controls */
        .form-group { margin-bottom: 16px; }
        .form-label {
          display: block;
          font-size: .75rem;
          font-weight: 600;
          color: var(--text-muted);
          margin-bottom: 6px;
          text-transform: uppercase;
          letter-spacing: .05em;
        }
        .form-control {
          width: 100%;
          background: var(--bg);
          border: 1px solid var(--border-hi);
          border-radius: 8px;
          padding: 10px 14px;
          color: var(--text);
          transition: border-color .2s, box-shadow .2s;
          outline: none;
        }
        .form-control:focus {
          border-color: var(--blue);
          box-shadow: 0 0 0 3px var(--blue-dim);
        }

        /* buttons */
        .btn {
          display: inline-flex;
          align-items: center;
          justify-content: center;
          gap: 8px;
          padding: 10px 20px;
          border-radius: 8px;
          font-size: .875rem;
          font-weight: 600;
          border: none;
          transition: opacity .2s, transform .1s;
        }
        .btn:active { transform: scale(.98); }
        .btn-primary { background: var(--accent); color: #0a0c10; }
        .btn-primary:hover { opacity: .9; }
        .btn-danger { background: rgba(224,82,82,.15); color: var(--danger); border: 1px solid rgba(224,82,82,.3); }
        .btn-danger:hover { background: rgba(224,82,82,.25); }
        .btn-ghost { background: var(--bg3); color: var(--text); border: 1px solid var(--border-hi); }
        .btn-ghost:hover { background: var(--border-hi); }
        .btn-sm { padding: 6px 12px; font-size: .8rem; }
        .btn-block { width: 100%; }

        /* alerts */
        .alert {
          padding: 12px 16px;
          border-radius: 8px;
          font-size: .85rem;
          margin-bottom: 16px;
        }
        .alert-success { background: rgba(52,201,126,.1); border: 1px solid rgba(52,201,126,.3); color: var(--success); }
        .alert-danger  { background: rgba(224,82,82,.1);  border: 1px solid rgba(224,82,82,.3);  color: var(--danger);  }

        /* badge */
        .badge {
          display: inline-block;
          padding: 4px 10px;
          border-radius: 100px;
          font-size: .7rem;
          font-weight: 600;
          letter-spacing: .02em;
        }
        .badge-blue   { background: var(--blue-dim);         color: var(--blue);    }
        .badge-green  { background: rgba(52,201,126,.15);    color: var(--success); }
        .badge-red    { background: rgba(224,82,82,.15);     color: var(--danger);  }
        .badge-yellow { background: rgba(245,197,24,.15);    color: var(--accent);  }
        .badge-muted  { background: var(--bg3);              color: var(--text-muted); }

        /* tables */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: .875rem; }
        th {
          text-align: left;
          padding: 12px 16px;
          font-size: .75rem;
          font-weight: 600;
          letter-spacing: .05em;
          text-transform: uppercase;
          color: var(--text-muted);
          border-bottom: 1px solid var(--border);
        }
        td {
          padding: 14px 16px;
          border-bottom: 1px solid var(--border);
          color: var(--text);
          vertical-align: middle;
        }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(255,255,255,.02); }

        /* pagination */
        .pagination { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 20px; }
        .pagination a, .pagination span {
          display: inline-flex; align-items: center; justify-content: center;
          min-width: 36px; height: 36px; padding: 0 10px;
          border-radius: 8px; font-size: .85rem; font-weight: 500;
          background: var(--bg3); color: var(--text-muted);
          border: 1px solid var(--border); transition: all .2s;
        }
        .pagination a:hover { background: var(--bg2); color: var(--text); }
        .pagination .active { background: var(--accent); color: #0a0c10; border-color: var(--accent); font-weight: 700; }
        .pagination .disabled { opacity: .4; pointer-events: none; }
      </style>
    </head>
    <body>
    <div id="app">

    <!-- Overlay (mobile) -->
    <div id="overlay" onclick="closeSidebar()"></div>

    <!-- Sidebar -->
    <nav id="sidebar">
      <div class="sidebar-brand">
        <div>
          <span class="brand-gudeg">GUDEG</span><span class="brand-jogja"> JOGJA</span>
        </div>
        <div class="brand-tagline">General Unified Database<br/>&amp; Employee Gateway</div>
      </div>

      <div class="sidebar-nav">
        <span class="nav-section-label">Menu Utama</span>

        <a href="/dashboard.php" class="nav-item <?= $activePage==='dashboard' ? 'active' : '' ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
            <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
          </svg>
          Dashboard
        </a>

        <a href="/change-password.php" class="nav-item <?= $activePage==='password' ? 'active' : '' ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
          </svg>
          Ganti Password
        </a>

        <?php if ($isAdmin): ?>
        <span class="nav-section-label" style="margin-top:12px">Administrasi</span>

        <a href="/admin/index.php" class="nav-item <?= $activePage==='admin-index' ? 'active' : '' ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
          </svg>
          Pengguna
        </a>

        <a href="/admin/apps.php" class="nav-item <?= $activePage==='admin-apps' ? 'active' : '' ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>
          </svg>
          Aplikasi SSO
        </a>

        <a href="/admin/logs.php" class="nav-item <?= $activePage==='admin-logs' ? 'active' : '' ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
            <line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
          </svg>
          Log Akses
        </a>
        <?php endif; ?>
      </div>

      <div class="sidebar-footer">
        <a href="/logout.php" class="nav-item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
            <polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
          </svg>
          Keluar Sistem
        </a>
      </div>
    </nav>

    <!-- Topbar -->
    <div id="topbar">
      <button id="hamburger" onclick="toggleSidebar()" aria-label="Toggle menu">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>
        </svg>
      </button>
      <span class="topbar-title"><?= htmlspecialchars($pageTitle) ?></span>
      <div class="topbar-right">
        Halo, <strong><?= htmlspecialchars($userName) ?></strong>
      </div>
    </div>

    <!-- Main content wrapper -->
    <main id="main">
    <?php
}

function layout_foot() {
    ?>
    </main>
    </div><!-- #app -->

    <script>
    function toggleSidebar() {
      const sb = document.getElementById('sidebar');
      const ov = document.getElementById('overlay');
      sb.classList.toggle('hidden');
      ov.classList.toggle('show');
    }
    function closeSidebar() {
      document.getElementById('sidebar').classList.add('hidden');
      document.getElementById('overlay').classList.remove('show');
    }
    // Mobile start hidden
    if (window.innerWidth < 768) {
      document.getElementById('sidebar').classList.add('hidden');
    }
    window.addEventListener('resize', () => {
      if (window.innerWidth >= 768) {
        document.getElementById('sidebar').classList.remove('hidden');
        document.getElementById('overlay').classList.remove('show');
      }
    });
    </script>
    </body>
    </html>
    <?php
}