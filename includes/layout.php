<?php
/**
 * includes/layout.php
 * Shared sidebar layout helpers.
 *
 * Usage:
 *   layout_head("Page Title");          — outputs <head> + open <body> + sidebar
 *   layout_foot();                      — closes main wrapper + </body></html>
 *
 * Expects $user array to be available in calling scope.
 */

function layout_head(string $title, string $active_nav = '') {
    global $user;
    $role     = htmlspecialchars($user['role'] ?? 'user');
    $name     = htmlspecialchars($user['name'] ?? '');
    $email    = htmlspecialchars($user['email'] ?? '');
    $is_admin = ($user['role'] ?? '') === 'admin';

    // Avatar: ambil huruf pertama dari kata pertama dan terakhir nama
    $name_parts = preg_split('/\s+/', trim($user['name'] ?? ''));
    $initials   = '';
    if (count($name_parts) >= 2) {
        $initials = mb_strtoupper(mb_substr($name_parts[0], 0, 1) . mb_substr(end($name_parts), 0, 1));
    } elseif (count($name_parts) === 1 && $name_parts[0] !== '') {
        $initials = mb_strtoupper(mb_substr($name_parts[0], 0, 2));
    }
    $initials = htmlspecialchars($initials);

    // Hari dalam Bahasa Indonesia (format 24 jam ditangani JS)
    $hari_id = ['Sunday'=>'MINGGU','Monday'=>'SENIN','Tuesday'=>'SELASA',
                'Wednesday'=>'RABU','Thursday'=>'KAMIS','Friday'=>'JUMAT','Saturday'=>'SABTU'];
    $bulan_id = ['January'=>'JANUARI','February'=>'FEBRUARI','March'=>'MARET',
                 'April'=>'APRIL','May'=>'MEI','June'=>'JUNI','July'=>'JULI',
                 'August'=>'AGUSTUS','September'=>'SEPTEMBER','October'=>'OKTOBER',
                 'November'=>'NOVEMBER','December'=>'DESEMBER'];
    $hari   = $hari_id[date('l')] ?? date('l');
    $bulan  = $bulan_id[date('F')] ?? date('F');
    $tanggal_str = $hari . ', ' . date('j') . ' ' . $bulan . ' ' . date('Y');
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($title) ?> — GUDEG JOGJA</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
<style>
/* ── Reset & tokens ─────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --bg:           #080a0e;
  --surface:      #0c0e13;
  --border:       #161b25;
  --accent:       #7aa2d4;
  --accent-dim:   #4a72a0;
  --accent-glow:  rgba(80,120,180,.07);
  --accent-bg:    #0e1a2a;
  --accent-bdr:   #1e2e44;
  --text:         #a0b4cc;
  --text-dim:     #2e4060;
  --text-muted:   #1e2836;
  --danger:       #c04040;
  --danger-bg:    rgba(192,64,64,.08);
  --danger-bdr:   #3a1818;
  --success:      #3a8a60;
  --mono:         'Courier New', Courier, monospace;
  --sb-expanded:  190px;
  --sb-collapsed: 28px;
  --topbar-h:     44px;
}

html { -webkit-text-size-adjust: 100%; }
body {
  background: var(--bg);
  color: var(--text);
  font-family: var(--mono);
  font-size: 11px;
  min-height: 100vh;
  overflow-x: hidden;
}
a { color: inherit; text-decoration: none; }

/* ── Sidebar overlay (mobile) ───────────────────────────── */
#sidebar-overlay {
  display: none;
  position: fixed; inset: 0;
  background: rgba(0,0,0,.75);
  z-index: 40;
}
#sidebar-overlay.open { display: block; }

/* ── Sidebar shell ──────────────────────────────────────── */
#sidebar {
  position: fixed;
  top: 0; left: 0;
  width: var(--sb-expanded);
  height: 100%;
  height: 100dvh;
  background: var(--surface);
  border-right: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  z-index: 50;
  overflow: hidden;
  transition: width .22s ease, transform .22s ease;
}

/* Mobile: hidden off-screen by default, overlay open */
@media (max-width: 767px) {
  #sidebar {
    transform: translateX(-100%);
    width: var(--sb-expanded) !important;
  }
  #sidebar.open { transform: translateX(0); }
  #sidebar-overlay { display: none; }
  #sidebar-overlay.open { display: block; }
  .sb-collapse-btn { display: none !important; }
}

/* Desktop: always visible, collapsible */
@media (min-width: 768px) {
  #sidebar { transform: translateX(0) !important; }
  #sidebar-overlay { display: none !important; }
  #sidebar.collapsed { width: var(--sb-collapsed); }
}

/* ── Brand ──────────────────────────────────────────────── */
.sb-brand {
  padding: 9px 10px;
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center; gap: 8px;
  flex-shrink: 0; overflow: hidden;
}
.sb-shield { flex-shrink: 0; width: 20px; display: flex; align-items: center; justify-content: center; }
.sb-shield i { font-size: 20px; color: var(--accent-dim); }
.sb-brand-text { overflow: hidden; white-space: nowrap; }
.sb-title {
  font-size: 12px; font-weight: 900; letter-spacing: .03em; line-height: 1.1;
}
.sb-title .gudeg { color: #ffffff; }
.sb-title .jogja { color: #f0c040; }
.sb-sub {
  font-size: 8px; color: var(--text-muted);
  text-transform: uppercase; letter-spacing: .07em; margin-top: 2px;
}

/* ── User panel ─────────────────────────────────────────── */
.sb-user {
  padding: 8px 10px;
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center; gap: 8px;
  flex-shrink: 0; overflow: hidden;
}
.sb-avatar {
  width: 30px; height: 30px; border-radius: 50%;
  background: var(--accent-bg); border: 1px solid var(--accent-bdr);
  display: flex; align-items: center; justify-content: center;
  font-size: 11px; font-weight: 900; color: var(--accent);
  flex-shrink: 0; letter-spacing: .02em;
}
.sb-user-info { min-width: 0; flex: 1; overflow: hidden; }
.sb-user-name {
  font-size: 11px; color: var(--text); font-weight: 700;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.sb-user-email {
  font-size: 9px; color: var(--text-muted);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 1px;
}
.sb-role {
  display: inline-block; margin-top: 3px;
  background: var(--accent-bg); border: 1px solid var(--accent-bdr);
  color: var(--accent-dim); font-size: 8px;
  text-transform: uppercase; letter-spacing: .07em;
  padding: 1px 5px; border-radius: 3px;
}

/* ── Nav ────────────────────────────────────────────────── */
.sb-nav { flex: 1; padding: 4px 0; overflow-y: auto; overflow-x: hidden; }
.sb-section-label {
  font-size: 8px; text-transform: uppercase; letter-spacing: .1em;
  color: var(--text-muted); padding: 6px 10px 2px;
  white-space: nowrap; overflow: hidden;
}
.sb-link {
  display: flex; align-items: center; gap: 7px;
  padding: 6px 10px;
  font-size: 10px; font-weight: 700; text-transform: uppercase;
  letter-spacing: .06em; color: var(--text-dim);
  border-left: 2px solid transparent;
  white-space: nowrap; overflow: hidden;
  transition: color .12s, background .12s, border-color .12s;
}
.sb-link i { font-size: 14px; width: 15px; flex-shrink: 0; }
.sb-link:hover { color: var(--accent-dim); background: rgba(80,120,180,.04); }
.sb-link.active {
  color: var(--accent); background: var(--accent-glow);
  border-left-color: var(--accent-dim);
}

/* Collapsed state: hide text, labels, user-info, brand-text */
#sidebar.collapsed .sb-brand-text,
#sidebar.collapsed .sb-user-info,
#sidebar.collapsed .sb-section-label,
#sidebar.collapsed .sb-link span { display: none; }
#sidebar.collapsed .sb-brand { justify-content: center; padding: 9px 0; gap: 0; }
#sidebar.collapsed .sb-user  { justify-content: center; padding: 8px 0; gap: 0; }
#sidebar.collapsed .sb-link  { justify-content: center; padding: 6px 0; gap: 0; border-left-width: 2px; }
#sidebar.collapsed .sb-section-label { padding: 4px 0; }
#sidebar.collapsed .sb-footer { padding: 7px 0 9px; align-items: center; }
#sidebar.collapsed .sb-datetime { display: none; }
#sidebar.collapsed .sb-logout { justify-content: center; padding: 5px 0; border: none; border-radius: 0; width: 100%; }
#sidebar.collapsed .sb-logout span { display: none; }

/* ── Footer ─────────────────────────────────────────────── */
.sb-footer {
  padding: 7px 10px 9px;
  border-top: 1px solid var(--border);
  flex-shrink: 0; overflow: hidden;
  display: flex; flex-direction: column; gap: 6px;
}
.sb-datetime { font-size: 9px; color: var(--text-muted); }
.sb-datetime .dt-date { color: #3a5070; letter-spacing: .04em; }
.sb-datetime .dt-time {
  font-size: 13px; font-weight: 700;
  color: #3a5a80; letter-spacing: .06em; margin-top: 2px;
}
.sb-logout {
  display: flex; align-items: center; gap: 6px;
  padding: 5px 8px;
  font-size: 10px; font-weight: 700; text-transform: uppercase;
  letter-spacing: .06em; color: #7a3030;
  border: 1px solid var(--danger-bdr); border-radius: 4px;
  width: 100%; cursor: pointer; background: transparent;
  font-family: var(--mono);
  transition: border-color .12s, color .12s;
  white-space: nowrap;
}
.sb-logout:hover { border-color: var(--danger); color: var(--danger); }
.sb-logout i { font-size: 15px; color: var(--danger); flex-shrink: 0; }

/* Collapse toggle button (desktop only) */
.sb-collapse-btn {
  display: flex; align-items: center; justify-content: center;
  background: none; border: none; cursor: pointer;
  color: var(--text-dim); padding: 2px;
  transition: color .12s;
}
.sb-collapse-btn:hover { color: var(--accent); }
.sb-collapse-btn i { font-size: 14px; }

/* ── Topbar (mobile only) ───────────────────────────────── */
#topbar {
  display: none;
  position: sticky; top: 0;
  height: var(--topbar-h);
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  align-items: center;
  padding: 0 10px; gap: 8px;
  z-index: 30;
}
@media (max-width: 767px) { #topbar { display: flex; } }

#hamburger {
  background: none; border: none; cursor: pointer;
  display: flex; flex-direction: column; gap: 4px; padding: .3rem;
}
#hamburger span {
  display: block; width: 18px; height: 2px;
  background: var(--text-dim); border-radius: 1px;
  transition: background .15s;
}
#hamburger:hover span { background: var(--accent); }

.topbar-title { font-size: 11px; font-weight: 900; letter-spacing: .03em; }
.topbar-title .gudeg { color: #ffffff; }
.topbar-title .jogja { color: #f0c040; }

/* ── Page layout ────────────────────────────────────────── */
#layout { display: flex; min-height: 100vh; }
#main-content {
  flex: 1; min-width: 0;
  padding: 16px 14px;
  transition: margin-left .22s ease;
}
@media (min-width: 768px) {
  #main-content { margin-left: var(--sb-expanded); }
  body.sb-collapsed #main-content { margin-left: var(--sb-collapsed); }
}
@media (max-width: 767px) {
  #main-content { padding: 12px 10px; }
}

/* ── Shared UI components ───────────────────────────────── */
.page-title {
  font-size: 13px; font-weight: 900;
  color: var(--accent); text-transform: uppercase;
  letter-spacing: .06em; line-height: 1.2;
}
.page-subtitle { color: var(--text-muted); font-size: 9px; margin-top: 2px; }
.page-header { margin-bottom: 14px; }

.section-label {
  font-size: 8px; font-weight: 700;
  text-transform: uppercase; letter-spacing: .1em;
  color: var(--text-muted); margin-bottom: 6px;
}

.card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 4px;
}
.card-pad { padding: 10px 12px; }

.msg-box {
  background: var(--accent-glow);
  border: 1px solid var(--accent-bdr);
  color: var(--accent);
  border-radius: 4px;
  padding: 6px 10px;
  font-size: 10px;
  margin-bottom: 10px;
}
.msg-box.error {
  background: var(--danger-bg);
  border-color: var(--danger-bdr);
  color: var(--danger);
}

.badge {
  display: inline-block;
  padding: 1px 5px; border-radius: 3px;
  font-size: 8px; font-family: var(--mono);
  text-transform: uppercase; letter-spacing: .04em;
}
.badge-admin    { background: rgba(80,120,180,.12); color: var(--accent);    border: 1px solid var(--accent-bdr); }
.badge-user     { background: rgba(60,100,160,.1);  color: #5a84b4;          border: 1px solid #1a2a40; }
.badge-guest    { background: rgba(40,50,60,.2);    color: var(--text-dim);  border: 1px solid var(--border); }
.badge-active   { background: rgba(58,138,96,.1);   color: var(--success);   border: 1px solid rgba(58,138,96,.2); }
.badge-inactive { background: rgba(192,64,64,.1);   color: var(--danger);    border: 1px solid rgba(192,64,64,.2); }
.badge-hmac     { background: rgba(60,100,160,.1);  color: #5a84b4;          border: 1px solid #1a2a40; }
.badge-rsa      { background: rgba(80,120,180,.12); color: var(--accent);    border: 1px solid var(--accent-bdr); }

button, .btn { font-family: var(--mono); cursor: pointer; }
</style>
</head>
<body>

<!-- Overlay (mobile) -->
<div id="sidebar-overlay" onclick="closeSidebar()"></div>

<!-- Topbar (mobile only) -->
<div id="topbar">
  <button id="hamburger" onclick="toggleSidebar()" aria-label="Buka menu">
    <span></span><span></span><span></span>
  </button>
  <span class="topbar-title">
    <span class="gudeg">GUDEG</span> <span class="jogja">JOGJA</span>
  </span>
</div>

<!-- Sidebar -->
<aside id="sidebar">

  <div class="sb-brand">
    <div class="sb-shield"><i class="ti ti-shield-check" aria-hidden="true"></i></div>
    <div class="sb-brand-text">
      <div class="sb-title">
        <span class="gudeg">GUDEG</span> <span class="jogja">JOGJA</span>
      </div>
      <div class="sb-sub">Portal Kerja Pegawai</div>
    </div>
  </div>

  <div class="sb-user">
    <div class="sb-avatar"><?= $initials ?></div>
    <div class="sb-user-info">
      <div class="sb-user-name"><?= $name ?></div>
      <div class="sb-user-email"><?= $email ?></div>
      <span class="sb-role"><?= $role ?></span>
    </div>
  </div>

  <nav class="sb-nav">
    <div class="sb-section-label">Menu</div>
    <a href="/dashboard.php" class="sb-link <?= $active_nav === 'dashboard' ? 'active' : '' ?>">
      <i class="ti ti-home" aria-hidden="true"></i><span>Dashboard</span>
    </a>
    <a href="/change-password.php" class="sb-link <?= $active_nav === 'password' ? 'active' : '' ?>">
      <i class="ti ti-key" aria-hidden="true"></i><span>Ubah Password</span>
    </a>

    <?php if ($is_admin): ?>
    <div class="sb-section-label" style="margin-top:4px">Admin</div>
    <a href="/admin/" class="sb-link <?= $active_nav === 'admin-users' ? 'active' : '' ?>">
      <i class="ti ti-users" aria-hidden="true"></i><span>Kelola User</span>
    </a>
    <a href="/admin/apps.php" class="sb-link <?= $active_nav === 'admin-apps' ? 'active' : '' ?>">
      <i class="ti ti-device-desktop" aria-hidden="true"></i><span>Aplikasi SSO</span>
    </a>
    <a href="/admin/logs.php" class="sb-link <?= $active_nav === 'admin-logs' ? 'active' : '' ?>">
      <i class="ti ti-clipboard-list" aria-hidden="true"></i><span>Logs</span>
    </a>
    <?php endif; ?>
  </nav>

  <div class="sb-footer">
    <div class="sb-datetime">
      <div class="dt-date"><?= $tanggal_str ?></div>
      <div class="dt-time" id="sb-clock">--:--:--</div>
    </div>
    <form method="POST" action="/logout.php" style="margin:0">
      <button type="submit" class="sb-logout">
        <i class="ti ti-power" aria-hidden="true"></i><span>Keluar</span>
      </button>
    </form>
    <button class="sb-collapse-btn" onclick="toggleCollapse()" id="collapse-btn" aria-label="Ciutkan sidebar">
      <i class="ti ti-chevron-left" id="collapse-icon"></i>
    </button>
  </div>

</aside>

<!-- Main content wrapper -->
<div id="layout">
  <main id="main-content">
<?php
}

function layout_foot() {
?>
  </main>
</div>

<script>
(function() {
  /* ── Clock ── */
  function updateClock() {
    var now = new Date();
    var hh = String(now.getHours()).padStart(2,'0');
    var mm = String(now.getMinutes()).padStart(2,'0');
    var ss = String(now.getSeconds()).padStart(2,'0');
    var el = document.getElementById('sb-clock');
    if (el) el.textContent = hh + ':' + mm + ':' + ss;
  }
  updateClock();
  setInterval(updateClock, 1000);

  /* ── Mobile sidebar ── */
  window.toggleSidebar = function() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebar-overlay').classList.toggle('open');
  };
  window.closeSidebar = function() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebar-overlay').classList.remove('open');
  };
  document.querySelectorAll('#sidebar .sb-link').forEach(function(el) {
    el.addEventListener('click', function() {
      if (window.innerWidth < 768) closeSidebar();
    });
  });

  /* ── Desktop collapsible ── */
  var STORAGE_KEY = 'gudeg_sb_collapsed';
  var sidebar     = document.getElementById('sidebar');
  var mainContent = document.getElementById('main-content');
  var icon        = document.getElementById('collapse-icon');

  function applyCollapsed(collapsed) {
    if (collapsed) {
      sidebar.classList.add('collapsed');
      document.body.classList.add('sb-collapsed');
      if (icon) { icon.classList.remove('ti-chevron-left'); icon.classList.add('ti-chevron-right'); }
    } else {
      sidebar.classList.remove('collapsed');
      document.body.classList.remove('sb-collapsed');
      if (icon) { icon.classList.remove('ti-chevron-right'); icon.classList.add('ti-chevron-left'); }
    }
  }

  /* Restore state from localStorage */
  if (window.innerWidth >= 768) {
    try {
      var saved = localStorage.getItem(STORAGE_KEY);
      if (saved === '1') applyCollapsed(true);
    } catch(e) {}
  }

  window.toggleCollapse = function() {
    if (window.innerWidth < 768) return;
    var isNowCollapsed = !sidebar.classList.contains('collapsed');
    applyCollapsed(isNowCollapsed);
    try { localStorage.setItem(STORAGE_KEY, isNowCollapsed ? '1' : '0'); } catch(e) {}
  };
})();
</script>
</body>
</html>
<?php
}