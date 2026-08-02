<?php
/**
 * includes/layout.php
 * Shared sidebar layout helpers.
 */

function layout_head(string $title, string $active_nav = '') {
    global $user;
    $role     = htmlspecialchars($user['role'] ?? 'user');
    $name     = htmlspecialchars($user['name'] ?? '');
    $email    = htmlspecialchars($user['email'] ?? '');
    $is_admin = ($user['role'] ?? '') === 'admin';

    $name_parts = preg_split('/\s+/', trim($user['name'] ?? ''));
    $initials   = '';
    if (count($name_parts) >= 2) {
        $initials = mb_strtoupper(mb_substr($name_parts[0], 0, 1) . mb_substr(end($name_parts), 0, 1));
    } elseif (count($name_parts) === 1 && $name_parts[0] !== '') {
        $initials = mb_strtoupper(mb_substr($name_parts[0], 0, 2));
    }
    $initials = htmlspecialchars($initials);

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

<!-- SEO & Meta -->
<meta name="description" content="GUDEG — General Unified Database And Employee Gateway. Sistem SSO terpadu Balai Monitor Spektrum Frekuensi Radio Kelas I Yogyakarta.">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#0c0f14">
<link rel="canonical" href="https://gudeg.xo.je/">

<!-- Open Graph -->
<meta property="og:type" content="website">
<meta property="og:site_name" content="GUDEG JOGJA">
<meta property="og:title" content="<?= htmlspecialchars($title) ?> — GUDEG JOGJA">
<meta property="og:description" content="Sistem SSO terpadu Balai Monitor Spektrum Frekuensi Radio Kelas I Yogyakarta.">
<meta property="og:url" content="https://gudeg.xo.je/">
<meta property="og:image" content="https://gudeg.xo.je/assets/og-image.png">
<meta property="og:locale" content="id_ID">

<!-- Favicon -->
<link rel="icon" type="image/png" href="/assets/favicon.png">
<link rel="apple-touch-icon" href="/assets/favicon.png">

<title><?= htmlspecialchars($title) ?> — GUDEG JOGJA</title>
<link rel="stylesheet" href="/assets/tabler/tabler-icons.min.css">
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=DM+Sans:opsz,wght@9..40,300;400;500;600&display=swap" rel="stylesheet">
<style>
/* ── Reset & tokens ─────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root,
[data-theme="dark"] {
  --bg:           #080b10;
  --surface:      #0f1520;
  --surface2:     #161e2c;
  --border:       #1e2a3a;
  --border-hi:    #2a3a50;
  --accent:       #00e5ff;
  --accent-glow:  rgba(0,229,255,.18);
  --accent-dim:   rgba(0,229,255,.7);
  --accent-bg:    rgba(0,229,255,.06);
  --accent-bdr:   rgba(0,229,255,.2);
  --text:         #f1f5f9;
  --muted-hi:     #6b8099;
  --muted:   #3a5070;
  --danger:       #ef4444;
  --danger-bg:    rgba(239,68,68,.08);
  --danger-bdr:   rgba(239,68,68,.25);
  --success:      #22c55e;
  --mono:         'Space Mono', 'Courier New', monospace;
  --sans:         'DM Sans', 'Segoe UI', system-ui, -apple-system, sans-serif;
  --sb-expanded:  200px;
  --sb-collapsed: 36px;
  --topbar-h:     48px;
  --autofill-bg:  #080b10;
  --autofill-txt: #f1f5f9;
  --glass:        rgba(8,11,16,.88);
  --glass-hi:     rgba(15,21,32,.97);
  --shadow-sb:    4px 0 24px rgba(0,0,0,.6);
  --transition-fast:       .12s cubic-bezier(.4,0,.2,1);
  --transition-med:        .22s cubic-bezier(.4,0,.2,1);
}

[data-theme="bright"] {
  --bg:           #f0f4f8;
  --surface:      #ffffff;
  --surface2:     #edf2f7;
  --border:       #d1dbe8;
  --border-hi:    #b8c8da;
  --accent:       #0284c7;
  --accent-glow:  rgba(2,132,199,.12);
  --accent-dim:   rgba(2,132,199,.8);
  --accent-bg:    rgba(2,132,199,.07);
  --accent-bdr:   rgba(2,132,199,.25);
  --text:         #0f172a;
  --muted-hi:     #475569;
  --muted:   #94a3b8;
  --danger:       #dc2626;
  --danger-bg:    rgba(220,38,38,.07);
  --danger-bdr:   rgba(220,38,38,.3);
  --success:      #16a34a;
  --glass:        rgba(240,244,248,.92);
  --glass-hi:     rgba(255,255,255,.98);
  --shadow-sb:    4px 0 20px rgba(0,0,0,.08);
  --autofill-bg:  #ffffff;
  --autofill-txt: #0f172a;
}

html { -webkit-text-size-adjust: 100%; }
body {
  background: var(--bg);
  color: var(--text);
  font-family: var(--sans);
  font-size: 14px;
  min-height: 100vh;
  overflow-x: hidden;
  transition: background var(--transition-med), color var(--transition-med);
}
a { color: inherit; text-decoration: none; }

/* ── Sidebar overlay (mobile) ───────────────────────────── */
#sidebar-overlay {
  display: none;
  position: fixed; inset: 0;
  background: rgba(0,0,0,.8);
  z-index: 40;
  backdrop-filter: blur(2px);
}
#sidebar-overlay.open { display: block; }

/* ── Sidebar shell ──────────────────────────────────────── */
#sidebar {
  position: fixed;
  top: 0; left: 0;
  width: var(--sb-expanded);
  height: 100%;
  height: 100dvh;
  background: var(--glass-hi);
  backdrop-filter: blur(20px) saturate(160%);
  border-right: 1px solid var(--border-hi);
  box-shadow: var(--shadow-sb);
  display: flex;
  flex-direction: column;
  z-index: 50;
  overflow: hidden;
  transition: width var(--transition-med), transform var(--transition-med), border-color var(--transition-med);
}

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
.sb-shield i { font-size: 22px; color: var(--accent); }
.sb-brand-text { overflow: hidden; white-space: nowrap; }
.sb-title {
  font-family: var(--mono);
  font-size: 13px; font-weight: 700; letter-spacing: .06em; line-height: 1.1;
}
.sb-title .gudeg {
  background: linear-gradient(135deg, var(--accent), #7dd3fc);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
.sb-title .jogja { color: #eab308; -webkit-text-fill-color: #eab308; }
.sb-sub {
  font-size: 9px; color: var(--muted);
  text-transform: uppercase; letter-spacing: .08em; margin-top: 2px;
  font-family: var(--mono);
}

/* ── User panel ─────────────────────────────────────────── */
.sb-user {
  padding: 8px 10px;
  border-bottom: 1px solid var(--border);
  display: flex; align-items: flex-start; gap: 8px;
  flex-shrink: 0; overflow: hidden;
  min-height: 72px;
}
.sb-avatar {
  width: 32px; height: 32px; border-radius: 50%;
  background: var(--accent-bg); border: 1px solid var(--accent-bdr);
  display: flex; align-items: center; justify-content: center;
  font-size: 11px; font-weight: 700; color: var(--accent);
  flex-shrink: 0; letter-spacing: .02em; margin-top: 2px;
  font-family: var(--mono);
  box-shadow: 0 0 12px var(--accent-glow);
}
.sb-user-info { min-width: 0; flex: 1; }
.sb-user-name {
  font-size: 13px; color: var(--text); font-weight: 600;
  white-space: normal; line-height: 1.35; word-break: break-word;
}
.sb-user-email {
  font-size: 10px; color: var(--muted); font-family: var(--mono);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px;
}
.sb-role {
  display: inline-block; margin-top: 4px;
  background: var(--accent-bg); border: 1px solid var(--accent-bdr);
  color: var(--accent); font-size: 9px;
  text-transform: uppercase; letter-spacing: .08em;
  padding: 1px 6px; border-radius: 3px;
  font-family: var(--mono);
}

/* ── Nav ────────────────────────────────────────────────── */
.sb-nav { flex: 1; padding: 4px 0; overflow-y: auto; overflow-x: hidden; }
.sb-section-label {
  font-size: 9px; text-transform: uppercase; letter-spacing: .1em;
  color: var(--muted); padding: 8px 10px 3px;
  white-space: nowrap; overflow: hidden;
  font-family: var(--mono);
}
.sb-link {
  display: flex; align-items: center; gap: 8px;
  padding: 7px 10px;
  font-size: 11px; font-weight: 700; text-transform: uppercase;
  letter-spacing: .06em; color: var(--muted-hi);
  border-left: 2px solid transparent;
  white-space: nowrap; overflow: hidden;
  transition: color var(--transition-fast), background var(--transition-fast), border-color var(--transition-fast);
  font-family: var(--mono);
}
.sb-link i { font-size: 16px; width: 16px; flex-shrink: 0; }
.sb-link:hover { color: var(--accent); background: var(--accent-bg); }
.sb-link.active {
  color: var(--accent); background: var(--accent-bg);
  border-left-color: var(--accent);
  box-shadow: inset 0 0 12px var(--accent-glow);
}

/* Collapsed state */
#sidebar.collapsed .sb-brand-text,
#sidebar.collapsed .sb-user-info,
#sidebar.collapsed .sb-section-label,
#sidebar.collapsed .sb-link span { display: none; }
#sidebar.collapsed .sb-brand { justify-content: center; padding: 9px 0; gap: 0; }
#sidebar.collapsed .sb-user  { justify-content: center; padding: 8px 0; gap: 0; }
#sidebar.collapsed .sb-link  { justify-content: center; padding: 6px 0; gap: 0; border-left-width: 2px; }
#sidebar.collapsed .sb-section-label { padding: 4px 0; }
#sidebar.collapsed .sb-footer { padding: 0; align-items: center; }
#sidebar.collapsed .sb-datetime { display: none; }
#sidebar.collapsed .sb-divider { display: none; }
#sidebar.collapsed .sb-logout-wrap { padding: 6px 0; width: 100%; }
#sidebar.collapsed .sb-logout { justify-content: center; padding: 5px 0; border: none; border-radius: 0; width: 100%; }
#sidebar.collapsed .sb-logout span { display: none; }
#sidebar.collapsed .sb-collapse-wrap { border-top: none; }
#sidebar.collapsed .sb-theme-wrap { padding: 4px 0; }
#sidebar.collapsed .sb-theme-btn { justify-content: center; border: none; border-radius: 0; width: 100%; padding: 5px 0; }
#sidebar.collapsed .sb-theme-btn .theme-toggle-label { display: none; }

/* ── Theme toggle button (sidebar) ─────────────────────── */
.sb-theme-wrap { padding: 4px 10px; }
.sb-theme-btn {
  display: flex; align-items: center; gap: 6px;
  padding: 6px 8px;
  font-size: 10px; font-weight: 700; text-transform: uppercase;
  letter-spacing: .08em; color: var(--muted-hi);
  border: 1px solid var(--border); border-radius: 5px;
  width: 100%; cursor: pointer; background: transparent;
  font-family: var(--mono);
  transition: border-color var(--transition-fast), color var(--transition-fast), box-shadow var(--transition-fast);
  white-space: nowrap;
}
.sb-theme-btn:hover { border-color: var(--accent); color: var(--accent); box-shadow: 0 0 8px var(--accent-glow); }
.sb-theme-btn i { font-size: 15px; flex-shrink: 0; }

/* ── Footer ─────────────────────────────────────────────── */
.sb-footer {
  padding: 0 0 9px;
  border-top: 1px solid var(--border);
  flex-shrink: 0; overflow: hidden;
  display: flex; flex-direction: column; gap: 0;
}
.sb-datetime { padding: 8px 10px 6px; }
.sb-divider { border: none; border-top: 1px solid var(--border); margin: 0; }
.sb-logout-wrap { padding: 6px 10px; }
.sb-collapse-wrap {
  border-top: 1px solid var(--border);
  display: flex; justify-content: center; padding: 5px 0;
}
.sb-datetime { font-size: 10px; color: var(--muted); font-family: var(--mono); }
.sb-datetime .dt-date { color: var(--muted-hi); letter-spacing: .05em; }
.sb-datetime .dt-time {
  font-size: 14px; font-weight: 700;
  color: var(--accent); letter-spacing: .08em; margin-top: 2px;
  text-shadow: 0 0 12px var(--accent-glow);
}
.sb-logout {
  display: flex; align-items: center; gap: 6px;
  padding: 6px 8px;
  font-size: 10px; font-weight: 700; text-transform: uppercase;
  letter-spacing: .08em; color: var(--danger);
  border: 1px solid var(--danger-bdr); border-radius: 5px;
  width: 100%; cursor: pointer; background: transparent;
  font-family: var(--mono);
  transition: border-color var(--transition-fast), color var(--transition-fast), box-shadow var(--transition-fast), background var(--transition-fast);
  white-space: nowrap;
}
.sb-logout:hover { border-color: var(--danger); background: var(--danger-bg); box-shadow: 0 0 8px rgba(239,68,68,.15); }
.sb-logout i { font-size: 16px; color: var(--danger); flex-shrink: 0; }

.sb-collapse-btn {
  display: flex; align-items: center; justify-content: center;
  background: none; border: none; cursor: pointer;
  color: var(--muted-hi); padding: 2px;
  transition: color var(--transition-fast);
}
.sb-collapse-btn:hover { color: var(--accent); }
.sb-collapse-btn i { font-size: 14px; }

/* ── Topbar (mobile only) ───────────────────────────────── */
#topbar {
  display: none;
  position: sticky; top: 0;
  height: var(--topbar-h);
  background: var(--glass-hi);
  backdrop-filter: blur(16px) saturate(160%);
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
  background: var(--muted-hi); border-radius: 1px;
  transition: background var(--transition-fast);
}
#hamburger:hover span { background: var(--accent); }

.topbar-title { font-family: var(--mono); font-size: 13px; font-weight: 700; letter-spacing: .06em; flex: 1; }
.topbar-title .gudeg {
  background: linear-gradient(135deg, var(--accent), #7dd3fc);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
.topbar-title .jogja { color: #eab308; -webkit-text-fill-color: #eab308; }

/* Topbar theme toggle (mobile) */
.topbar-theme-btn {
  display: flex; align-items: center; justify-content: center;
  width: 32px; height: 32px;
  background: none; border: 1px solid var(--border); border-radius: 5px;
  cursor: pointer; color: var(--muted-hi);
  transition: border-color var(--transition-fast), color var(--transition-fast), box-shadow var(--transition-fast);
  flex-shrink: 0;
}
.topbar-theme-btn:hover { border-color: var(--accent); color: var(--accent); box-shadow: 0 0 8px var(--accent-glow); }
.topbar-theme-btn i { font-size: 16px; }

/* ── Page layout ────────────────────────────────────────── */
#layout { display: flex; min-height: 100vh; }
#main-content {
  flex: 1; min-width: 0;
  padding: 20px 18px;
  transition: margin-left var(--transition-med);
}
@media (min-width: 768px) {
  #main-content { margin-left: var(--sb-expanded); }
  body.sb-collapsed #main-content { margin-left: var(--sb-collapsed); }
}
@media (max-width: 767px) {
  #main-content { padding: 14px 12px; }
}

/* ── Shared UI components ───────────────────────────────── */
.page-title {
  font-size: 16px; font-weight: 700;
  color: var(--accent); text-transform: uppercase;
  letter-spacing: .08em; line-height: 1.2;
  font-family: var(--mono);
  text-shadow: 0 0 20px var(--accent-glow);
}
.page-subtitle { color: var(--muted); font-size: 11px; margin-top: 4px; font-family: var(--mono); letter-spacing: .05em; }
.page-header { margin-bottom: 18px; }

.section-label {
  font-size: 9px; font-weight: 700;
  text-transform: uppercase; letter-spacing: .12em;
  color: var(--muted); margin-bottom: 8px;
  font-family: var(--mono);
}

.card {
  background: var(--glass-hi);
  backdrop-filter: blur(16px) saturate(150%);
  border: 1px solid var(--border-hi);
  border-radius: 8px;
  box-shadow: 0 4px 20px rgba(0,0,0,.3);
  transition: border-color var(--transition-fast);
}
.card-pad { padding: 12px 14px; }

.msg-box {
  background: var(--accent-bg);
  border: 1px solid var(--accent-bdr);
  color: var(--accent);
  border-radius: 6px;
  padding: 8px 12px;
  font-size: 12px;
  margin-bottom: 12px;
  font-family: var(--mono);
}
.msg-box.error {
  background: var(--danger-bg);
  border-color: var(--danger-bdr);
  color: var(--danger);
}

.badge {
  display: inline-block;
  padding: 2px 7px; border-radius: 3px;
  font-size: 9px; font-family: var(--mono);
  text-transform: uppercase; letter-spacing: .06em;
}
.badge-admin    { background: var(--accent-bg);            color: var(--accent);  border: 1px solid var(--accent-bdr); }
.badge-user     { background: rgba(99,102,241,.1);         color: #a5b4fc;        border: 1px solid rgba(99,102,241,.25); }
.badge-guest    { background: rgba(107,128,153,.1);        color: var(--muted-hi);border: 1px solid var(--border); }
.badge-active   { background: rgba(34,197,94,.08);         color: var(--success); border: 1px solid rgba(34,197,94,.25); }
.badge-inactive { background: rgba(239,68,68,.08);         color: var(--danger);  border: 1px solid rgba(239,68,68,.25); }
.badge-hmac     { background: rgba(234,179,8,.08);         color: #fde68a;        border: 1px solid rgba(234,179,8,.25); }
.badge-rsa      { background: var(--accent-bg);            color: var(--accent);  border: 1px solid var(--accent-bdr); }

button, .btn { font-family: var(--sans); cursor: pointer; }

/* ── Mobile optimizations ───────────────────────────────── */
@media (max-width: 767px) {
  #main-content { padding: 12px 10px 20px; }
  .page-header { margin-bottom: 12px; }
  .page-title { font-size: 13px; }
  .sb-user-email { max-width: 130px; }
  #topbar { height: 50px; padding: 0 12px; }
  .badge { font-size: 8px; padding: 2px 5px; }
}

@media (max-width: 400px) {
  #main-content { padding: 10px 8px 20px; }
  .sb-user-email { max-width: 110px; }
}
</style>

<!-- Apply theme BEFORE paint to prevent flash -->
<script>
(function() {
  try {
    var t = localStorage.getItem('gudeg_theme') || 'dark';
    document.documentElement.setAttribute('data-theme', t);
  } catch(e) {}
})();
</script>
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
  <!-- Theme toggle di topbar (mobile) -->
  <button class="topbar-theme-btn" onclick="toggleTheme()" aria-label="Ganti tema">
    <i class="ti ti-sun theme-toggle-icon" aria-hidden="true"></i>
  </button>
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
      <i class="ti ti-home" aria-hidden="true"></i><span>Beranda</span>
    </a>
    <a href="/change-password.php" class="sb-link <?= $active_nav === 'password' ? 'active' : '' ?>">
      <i class="ti ti-settings" aria-hidden="true"></i><span>Pengaturan</span>
    </a>

    <?php if ($is_admin): ?>
    <div class="sb-section-label" style="margin-top:4px">Admin</div>
    <a href="/admin/" class="sb-link <?= $active_nav === 'admin-users' ? 'active' : '' ?>">
      <i class="ti ti-users" aria-hidden="true"></i><span>Kelola User</span>
    </a>
    <a href="/admin/apps.php" class="sb-link <?= $active_nav === 'admin-apps' ? 'active' : '' ?>">
      <i class="ti ti-device-desktop" aria-hidden="true"></i><span>Kelola Aplikasi</span>
    </a>
    <a href="/admin/logs.php" class="sb-link <?= $active_nav === 'admin-logs' ? 'active' : '' ?>">
      <i class="ti ti-clipboard-list" aria-hidden="true"></i><span>Log Aktivitas</span>
    </a>
    <?php endif; ?>
  </nav>

  <div class="sb-footer">
    <div class="sb-datetime">
      <div class="dt-date"><?= $tanggal_str ?></div>
      <div class="dt-time" id="sb-clock">--:--:--</div>
    </div>
    <hr class="sb-divider">
    <!-- Theme toggle di sidebar -->
    <div class="sb-theme-wrap">
      <button class="sb-theme-btn" onclick="toggleTheme()" aria-label="Ganti tema">
        <i class="ti ti-sun theme-toggle-icon" aria-hidden="true"></i>
        <span class="theme-toggle-label">Terang</span>
      </button>
    </div>
    <div class="sb-logout-wrap">
      <form method="POST" action="/logout.php" style="margin:0">
        <?= csrf_field() ?>
        <button type="submit" class="sb-logout">
          <i class="ti ti-power" aria-hidden="true"></i><span>Keluar</span>
        </button>
      </form>
    </div>
    <div class="sb-collapse-wrap">
      <button class="sb-collapse-btn" onclick="toggleCollapse()" id="collapse-btn" aria-label="Ciutkan sidebar">
        <i class="ti ti-chevron-left" id="collapse-icon"></i>
      </button>
    </div>
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
  /* ── Theme ── */
  var THEME_KEY = 'gudeg_theme';
  function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    try { localStorage.setItem(THEME_KEY, theme); } catch(e) {}
    document.querySelectorAll('.theme-toggle-icon').forEach(function(ic) {
      ic.className = 'ti ' + (theme === 'bright' ? 'ti-moon' : 'ti-sun') + ' theme-toggle-icon';
    });
    document.querySelectorAll('.theme-toggle-label').forEach(function(lb) {
      lb.textContent = theme === 'bright' ? 'Dark' : 'Terang';
    });
  }
  function getTheme() {
    try { return localStorage.getItem(THEME_KEY) || 'dark'; } catch(e) { return 'dark'; }
  }
  window.toggleTheme = function() {
    applyTheme(getTheme() === 'dark' ? 'bright' : 'dark');
  };
  /* Sync icon on load (theme already applied in <head>) */
  applyTheme(getTheme());

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

/* ── Kirim kredensial ke APK Android setelah login berhasil ── */
(function() {
  try {
    var email = sessionStorage.getItem('__gudeg_pending_email');
    var password = sessionStorage.getItem('__gudeg_pending_password');
    if (email && password && window.GudegNative && window.GudegNative.saveCredentials) {
      window.GudegNative.saveCredentials(email, password);
    }
    sessionStorage.removeItem('__gudeg_pending_email');
    sessionStorage.removeItem('__gudeg_pending_password');
  } catch (e) {}
})();
</script>
</body>
</html>
<?php
}