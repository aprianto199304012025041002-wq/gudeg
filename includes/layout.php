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
    $role  = htmlspecialchars($user['role'] ?? 'user');
    $name  = htmlspecialchars($user['name'] ?? '');
    $email = htmlspecialchars($user['email'] ?? '');
    $is_admin = ($user['role'] ?? '') === 'admin';
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($title) ?> — GUDEG SSO</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Mono:wght@400;500&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
/* ── Reset & tokens ─────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --bg:          #0a0c10;
  --surface:     #12151c;
  --surface2:    #181c26;
  --border:      #1e2330;
  --accent:      #c8a96e;
  --accent-dim:  #8a7249;
  --accent-glow: rgba(200,169,110,.12);
  --text:        #e8e4dc;
  --text-dim:    #7a7a8a;
  --danger:      #e57373;
  --success:     #69d98c;
  --sidebar-w:   260px;
  --topbar-h:    52px;
}

html { -webkit-text-size-adjust: 100%; }
body {
  background: var(--bg);
  color: var(--text);
  font-family: 'DM Sans', sans-serif;
  min-height: 100vh;
  overflow-x: hidden;
}

a { color: inherit; text-decoration: none; }

/* ── Sidebar overlay (mobile) ───────────────────────────── */
#sidebar-overlay {
  display: none;
  position: fixed; inset: 0;
  background: rgba(0,0,0,.6);
  z-index: 40;
  backdrop-filter: blur(2px);
}
#sidebar-overlay.open { display: block; }

/* ── Sidebar ────────────────────────────────────────────── */
#sidebar {
  position: fixed;
  top: 0; left: 0;
  width: var(--sidebar-w);
  height: 100%;
  background: var(--surface);
  border-right: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  z-index: 50;
  transform: translateX(-100%);
  transition: transform .28s cubic-bezier(.4,0,.2,1);
  overflow-y: auto;
}
#sidebar.open, body.sidebar-open #sidebar { transform: translateX(0); }

/* Desktop: always visible */
@media (min-width: 768px) {
  #sidebar { transform: translateX(0) !important; }
  #sidebar-overlay { display: none !important; }
}

/* Sidebar brand */
.sb-brand {
  padding: 1.25rem 1.25rem 1rem;
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center; gap: .75rem;
}
.sb-logo {
  width: 36px; height: 36px;
  background: linear-gradient(135deg, var(--accent), var(--accent-dim));
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1rem; flex-shrink: 0;
}
.sb-brand-text .sb-title {
  font-family: 'DM Serif Display', serif;
  font-size: 1rem; color: var(--accent); line-height: 1.1;
}
.sb-brand-text .sb-sub {
  font-family: 'DM Mono', monospace;
  font-size: .62rem; color: var(--text-dim);
  text-transform: uppercase; letter-spacing: .08em;
}

/* User info box */
.sb-user {
  padding: 1rem 1.25rem;
  border-bottom: 1px solid var(--border);
}
.sb-user-name {
  font-weight: 500; font-size: .88rem;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.sb-user-email {
  font-size: .72rem; color: var(--text-dim);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  margin-top: .15rem;
}
.sb-role {
  display: inline-block;
  margin-top: .4rem;
  background: var(--accent-glow);
  border: 1px solid rgba(200,169,110,.2);
  color: var(--accent);
  font-family: 'DM Mono', monospace;
  font-size: .62rem; text-transform: uppercase; letter-spacing: .08em;
  padding: .15rem .5rem; border-radius: 20px;
}

/* Nav section */
.sb-nav { flex: 1; padding: .75rem 0; }
.sb-section-label {
  font-family: 'DM Mono', monospace;
  font-size: .6rem; text-transform: uppercase; letter-spacing: .1em;
  color: var(--text-dim); opacity: .5;
  padding: .6rem 1.25rem .3rem;
}
.sb-link {
  display: flex; align-items: center; gap: .65rem;
  padding: .65rem 1.25rem;
  font-size: .88rem; color: var(--text-dim);
  border-left: 2px solid transparent;
  transition: color .15s, background .15s, border-color .15s;
}
.sb-link .sb-icon { font-size: 1rem; width: 20px; text-align: center; }
.sb-link:hover {
  color: var(--text);
  background: rgba(255,255,255,.03);
}
.sb-link.active {
  color: var(--accent);
  background: var(--accent-glow);
  border-left-color: var(--accent);
}

/* Sidebar footer */
.sb-footer {
  padding: .75rem 1.25rem 1.25rem;
  border-top: 1px solid var(--border);
}
.sb-logout {
  display: flex; align-items: center; gap: .65rem;
  padding: .6rem .75rem;
  font-size: .85rem; color: var(--text-dim);
  border: 1px solid var(--border);
  border-radius: 8px;
  width: 100%;
  cursor: pointer; background: transparent;
  transition: border-color .15s, color .15s;
  font-family: 'DM Sans', sans-serif;
}
.sb-logout:hover { border-color: var(--danger); color: var(--danger); }

/* ── Topbar (mobile hamburger) ──────────────────────────── */
#topbar {
  display: none;
  position: sticky; top: 0;
  height: var(--topbar-h);
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  align-items: center;
  padding: 0 1rem;
  z-index: 30;
  gap: .75rem;
}
@media (max-width: 767px) { #topbar { display: flex; } }

#hamburger {
  background: none; border: none; cursor: pointer;
  display: flex; flex-direction: column; gap: 5px; padding: .35rem;
}
#hamburger span {
  display: block; width: 20px; height: 2px;
  background: var(--text-dim); border-radius: 2px;
  transition: background .2s;
}
#hamburger:hover span { background: var(--accent); }

.topbar-title {
  font-family: 'DM Serif Display', serif;
  font-size: 1rem; color: var(--accent);
}

/* ── Page layout ────────────────────────────────────────── */
#layout {
  display: flex; min-height: 100vh;
}
#main-content {
  flex: 1;
  min-width: 0;
  padding: 2rem 1.5rem;
}
@media (min-width: 768px) {
  #main-content { margin-left: var(--sidebar-w); }
}
@media (max-width: 767px) {
  #main-content { padding: 1.25rem 1rem; }
}

/* ── Shared UI components ───────────────────────────────── */
.page-title {
  font-family: 'DM Serif Display', serif;
  font-size: 1.75rem; line-height: 1.15;
}
.page-subtitle {
  color: var(--text-dim); font-size: .85rem; margin-top: .3rem;
}
.page-header { margin-bottom: 1.75rem; }

.section-label {
  font-size: .7rem; font-weight: 500;
  text-transform: uppercase; letter-spacing: .1em;
  color: var(--text-dim); margin-bottom: .75rem;
  font-family: 'DM Mono', monospace;
}

.card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
}
.card-pad { padding: 1.25rem; }

.msg-box {
  background: var(--accent-glow);
  border: 1px solid rgba(200,169,110,.25);
  color: var(--accent);
  border-radius: 8px;
  padding: .7rem 1rem;
  font-size: .85rem;
  margin-bottom: 1.25rem;
}
.msg-box.error {
  background: rgba(229,115,115,.08);
  border-color: rgba(229,115,115,.25);
  color: var(--danger);
}

.badge {
  display: inline-block;
  padding: .18rem .55rem;
  border-radius: 20px;
  font-size: .68rem;
  font-family: 'DM Mono', monospace;
  text-transform: uppercase; letter-spacing: .04em;
}
.badge-admin   { background: rgba(200,169,110,.15); color: var(--accent); }
.badge-user    { background: rgba(100,160,220,.15); color: #7ab5f0; }
.badge-guest   { background: rgba(120,120,120,.15); color: var(--text-dim); }
.badge-active  { background: rgba(39,174,96,.12);   color: var(--success); }
.badge-inactive{ background: rgba(229,115,115,.12); color: var(--danger); }
.badge-hmac    { background: rgba(100,160,220,.12); color: #7ab5f0; }
.badge-rsa     { background: rgba(200,169,110,.12); color: var(--accent); }

btn, button, .btn {
  font-family: 'DM Sans', sans-serif;
  cursor: pointer;
}
</style>
</head>
<body>

<!-- Overlay -->
<div id="sidebar-overlay" onclick="closeSidebar()"></div>

<!-- Topbar (mobile only) -->
<div id="topbar">
  <button id="hamburger" onclick="toggleSidebar()" aria-label="Menu">
    <span></span><span></span><span></span>
  </button>
  <span class="topbar-title">GUDEG SSO</span>
</div>

<!-- Sidebar -->
<aside id="sidebar">
  <div class="sb-brand">
    <div class="sb-logo">🔐</div>
    <div class="sb-brand-text">
      <div class="sb-title">GUDEG SSO</div>
      <div class="sb-sub">Portal Autentikasi</div>
    </div>
  </div>

  <div class="sb-user">
    <div class="sb-user-name"><?= $name ?></div>
    <div class="sb-user-email"><?= $email ?></div>
    <span class="sb-role"><?= $role ?></span>
  </div>

  <nav class="sb-nav">
    <div class="sb-section-label">Menu</div>
    <a href="/dashboard.php" class="sb-link <?= $active_nav === 'dashboard' ? 'active' : '' ?>">
      <span class="sb-icon">🏠</span> Dashboard
    </a>
    <a href="/change-password.php" class="sb-link <?= $active_nav === 'password' ? 'active' : '' ?>">
      <span class="sb-icon">🔑</span> Ubah Password
    </a>

    <?php if ($is_admin): ?>
    <div class="sb-section-label" style="margin-top:.5rem">Admin</div>
    <a href="/admin/" class="sb-link <?= $active_nav === 'admin-users' ? 'active' : '' ?>">
      <span class="sb-icon">👥</span> Kelola User
    </a>
    <a href="/admin/apps.php" class="sb-link <?= $active_nav === 'admin-apps' ? 'active' : '' ?>">
      <span class="sb-icon">🖥️</span> Aplikasi SSO
    </a>
    <a href="/admin/logs.php" class="sb-link <?= $active_nav === 'admin-logs' ? 'active' : '' ?>">
      <span class="sb-icon">📋</span> Logs
    </a>
    <?php endif; ?>
  </nav>

  <div class="sb-footer">
    <form method="POST" action="/logout.php">
      <button type="submit" class="sb-logout">
        <span>↩</span> Keluar
      </button>
    </form>
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
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('sidebar-overlay').classList.toggle('open');
}
function closeSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebar-overlay').classList.remove('open');
}
// Close on nav link click (mobile)
document.querySelectorAll('#sidebar .sb-link').forEach(function(el) {
  el.addEventListener('click', function() {
    if (window.innerWidth < 768) closeSidebar();
  });
});
</script>
</body>
</html>
<?php
}