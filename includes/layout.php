<?php
// includes/layout.php
// Usage: include at top of page after session_start()
// $pageTitle   = string   — shown in <title> and topbar
// $activePage  = string   — matches nav item key: dashboard|change-password|admin-index|admin-apps|admin-logs
// $isAdmin     = bool     — controls admin nav section visibility

if (!isset($pageTitle))  $pageTitle  = 'GUDEG JOGJA';
if (!isset($activePage)) $activePage = '';
if (!isset($isAdmin))    $isAdmin    = false;

$baseUrl = '/'; // adjust if in subdirectory
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= htmlspecialchars($pageTitle) ?> — GUDEG JOGJA</title>
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
      --accent:     #f5c518;   /* kuning GUDEG */
      --accent-dim: rgba(245,197,24,.12);
      --blue:       #2f80ed;
      --blue-dim:   rgba(47,128,237,.15);
      --danger:     #e05252;
      --success:    #34c97e;
      --sidebar-w:  220px;
      --topbar-h:   52px;
      --radius:     10px;
      --font-head:  'Syne', sans-serif;
      --font-body:  'DM Sans', sans-serif;
      --shadow:     0 4px 24px rgba(0,0,0,.45);
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
      padding: 18px 20px 14px;
      border-bottom: 1px solid var(--border);
    }
    .sidebar-brand .brand-gudeg {
      font-family: var(--font-head);
      font-size: 1.3rem;
      font-weight: 800;
      letter-spacing: .04em;
      color: var(--accent);
      line-height: 1;
    }
    .sidebar-brand .brand-jogja {
      font-family: var(--font-head);
      font-size: 1.3rem;
      font-weight: 800;
      letter-spacing: .04em;
      color: #fff;
    }
    .sidebar-brand .brand-tagline {
      font-size: .65rem;
      color: var(--text-muted);
      margin-top: 4px;
      letter-spacing: .03em;
      text-transform: uppercase;
    }

    .sidebar-nav {
      flex: 1;
      padding: 12px 10px;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
      gap: 2px;
    }
    .nav-section-label {
      font-size: .6rem;
      font-weight: 600;
      letter-spacing: .1em;
      text-transform: uppercase;
      color: var(--text-muted);
      padding: 10px 10px 4px;
    }
    .nav-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 9px 12px;
      border-radius: 7px;
      color: var(--text-muted);
      font-size: .875rem;
      font-weight: 500;
      transition: background .15s, color .15s;
    }
    .nav-item:hover { background: var(--bg3); color: var(--text); }
    .nav-item.active {
      background: var(--accent-dim);
      color: var(--accent);
    }
    .nav-item svg { width:16px; height:16px; flex-shrink:0; }

    .sidebar-footer {
      padding: 12px 10px;
      border-top: 1px solid var(--border);
    }
    .sidebar-footer .nav-item { color: var(--danger); }
    .sidebar-footer .nav-item:hover { background: rgba(224,82,82,.1); color: var(--danger); }

    /* ── OVERLAY (mobile) ────────────────────────────── */
    #overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,.55);
      z-index: 199;
      backdrop-filter: blur(2px);
    }
    #overlay.show { display: block; }

    /* ── TOPBAR ──────────────────────────────────────── */
    #topbar {
      position: fixed;
      top: 0; left: 0; right: 0;
      height: var(--topbar-h);
      background: var(--bg2);
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      padding: 0 16px;
      gap: 12px;
      z-index: 100;
    }
    @media (min-width:768px) {
      #topbar { left: var(--sidebar-w); }
    }
    #hamburger {
      background: none;
      border: none;
      color: var(--text-muted);
      display: flex;
      padding: 6px;
      border-radius: 6px;
      transition: background .15s, color .15s;
    }
    #hamburger:hover { background: var(--bg3); color: var(--text); }
    @media (min-width:768px) { #hamburger { display:none; } }

    .topbar-title {
      font-family: var(--font-head);
      font-size: .95rem;
      font-weight: 700;
      color: var(--text);
      letter-spacing: .02em;
    }
    .topbar-right {
      margin-left: auto;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .topbar-user {
      font-size: .8rem;
      color: var(--text-muted);
    }
    .topbar-user strong { color: var(--text); }

    /* ── MAIN CONTENT ────────────────────────────────── */
    #main {
      flex: 1;
      margin-top: var(--topbar-h);
      padding: 24px 20px;
      min-width: 0;
    }
    @media (min-width:768px) {
      #main { margin-left: var(--sidebar-w); }
      #sidebar { transform: none !important; }
      #sidebar.hidden { transform: none !important; }
    }

    /* ── SHARED COMPONENTS ───────────────────────────── */
    .page-header {
      margin-bottom: 20px;
    }
    .page-header h1 {
      font-family: var(--font-head);
      font-size: 1.3rem;
      font-weight: 700;
      color: var(--text);
    }
    .page-header p {
      font-size: .825rem;
      color: var(--text-muted);
      margin-top: 3px;
    }

    .card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 18px;
    }
    .card + .card { margin-top: 14px; }

    /* form controls */
    .form-group { margin-bottom: 14px; }
    .form-label {
      display: block;
      font-size: .8rem;
      font-weight: 600;
      color: var(--text-muted);
      margin-bottom: 5px;
      letter-spacing: .03em;
      text-transform: uppercase;
    }
    .form-control {
      width: 100%;
      background: var(--bg3);
      border: 1px solid var(--border-hi);
      border-radius: 7px;
      padding: 9px 12px;
      color: var(--text);
      font-size: 1rem;
      transition: border-color .15s, box-shadow .15s;
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
      gap: 7px;
      padding: 9px 18px;
      border-radius: 7px;
      font-size: .875rem;
      font-weight: 600;
      border: none;
      transition: opacity .15s, transform .1s;
    }
    .btn:active { transform: scale(.97); }
    .btn-primary {
      background: var(--blue);
      color: #fff;
    }
    .btn-primary:hover { opacity: .88; }
    .btn-danger {
      background: rgba(224,82,82,.15);
      color: var(--danger);
      border: 1px solid rgba(224,82,82,.25);
    }
    .btn-danger:hover { background: rgba(224,82,82,.25); }
    .btn-ghost {
      background: var(--bg3);
      color: var(--text-muted);
      border: 1px solid var(--border-hi);
    }
    .btn-ghost:hover { color: var(--text); }
    .btn-sm { padding: 6px 12px; font-size: .8rem; }
    .btn-block { width: 100%; }

    /* alerts */
    .alert {
      padding: 10px 14px;
      border-radius: 7px;
      font-size: .85rem;
      margin-bottom: 14px;
    }
    .alert-success { background: rgba(52,201,126,.12); border: 1px solid rgba(52,201,126,.25); color: var(--success); }
    .alert-danger  { background: rgba(224,82,82,.12);  border: 1px solid rgba(224,82,82,.25);  color: var(--danger);  }

    /* badge */
    .badge {
      display: inline-block;
      padding: 2px 8px;
      border-radius: 100px;
      font-size: .72rem;
      font-weight: 600;
    }
    .badge-blue    { background: var(--blue-dim);          color: var(--blue);    }
    .badge-green   { background: rgba(52,201,126,.12);     color: var(--success); }
    .badge-red     { background: rgba(224,82,82,.12);      color: var(--danger);  }
    .badge-yellow  { background: rgba(245,197,24,.12);     color: var(--accent);  }
    .badge-muted   { background: var(--bg3);               color: var(--text-muted); }

    /* table */
    .table-wrap { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; font-size: .85rem; }
    th {
      text-align: left;
      padding: 9px 12px;
      font-size: .7rem;
      font-weight: 600;
      letter-spacing: .07em;
      text-transform: uppercase;
      color: var(--text-muted);
      border-bottom: 1px solid var(--border);
    }
    td {
      padding: 10px 12px;
      border-bottom: 1px solid var(--border);
      color: var(--text);
      vertical-align: middle;
    }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: rgba(255,255,255,.02); }

    /* pagination */
    .pagination {
      display: flex;
      gap: 4px;
      flex-wrap: wrap;
      margin-top: 16px;
    }
    .pagination a, .pagination span {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 32px;
      height: 32px;
      padding: 0 8px;
      border-radius: 6px;
      font-size: .8rem;
      font-weight: 500;
      background: var(--bg3);
      color: var(--text-muted);
      border: 1px solid var(--border);
      transition: background .15s, color .15s;
    }
    .pagination a:hover { background: var(--bg2); color: var(--text); }
    .pagination .active { background: var(--blue); color: #fff; border-color: var(--blue); }
    .pagination .disabled { opacity: .35; pointer-events: none; }
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
    <div class="brand-tagline">General Unified Database &amp; Employee Gateway</div>
  </div>

  <div class="sidebar-nav">
    <span class="nav-section-label">Menu</span>

    <a href="/dashboard.php" class="nav-item <?= $activePage==='dashboard' ? 'active' : '' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
        <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
      </svg>
      Dashboard
    </a>

    <a href="/change-password.php" class="nav-item <?= $activePage==='change-password' ? 'active' : '' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
      </svg>
      Ganti Password
    </a>

    <?php if ($isAdmin): ?>
    <span class="nav-section-label" style="margin-top:6px">Admin</span>

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
      Keluar
    </a>
  </div>
</nav>

<!-- Topbar -->
<div id="topbar">
  <button id="hamburger" onclick="toggleSidebar()" aria-label="Toggle menu">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
      <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
    </svg>
  </button>
  <span class="topbar-title"><?= htmlspecialchars($pageTitle) ?></span>
  <div class="topbar-right">
    <?php if (isset($_SESSION['name'])): ?>
    <span class="topbar-user">Halo, <strong><?= htmlspecialchars($_SESSION['name']) ?></strong></span>
    <?php endif; ?>
  </div>
</div>

<!-- Main content wrapper -->
<main id="main">
<?php
// page content follows here
?>

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
// mobile: start hidden
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
<?php
// includes/layout_end.php — tutup tag yang dibuka layout.php
?>
</main>
</div><!-- #app -->
</body>
</html>