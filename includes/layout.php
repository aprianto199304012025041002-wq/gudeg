<?php
/**
 * includes/layout.php
 * Shared sidebar layout helpers.
 */

function layout_head(string $title, string $active_nav = '', ?array $user = null) {
    // Cache-busting -- paksa WebView selalu ambil versi terbaru
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
    }
    if ($user === null) {
        $user = $GLOBALS['user'] ?? [];
    }
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
<meta name="description" content="GUDEG — General Unified Database And Employee Gateway. Sistem SSO terpadu BALAI MONITOR SPEKTRUM FREKUENSI RADIO DAN INFRASTRUKTUR DIGITAL<br>KELAS I YOGYAKARTA.">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#0c0f14">
<link rel="canonical" href="https://gudeg.xo.je/">

<!-- Open Graph -->
<meta property="og:type" content="website">
<meta property="og:site_name" content="GUDEG JOGJA">
<meta property="og:title" content="GUDEG - <?= htmlspecialchars($title) ?>">
<meta property="og:description" content="Sistem SSO terpadu BALAI MONITOR SPEKTRUM FREKUENSI RADIO DAN INFRASTRUKTUR DIGITAL KELAS I YOGYAKARTA.">
<meta property="og:url" content="https://gudeg.xo.je<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/') ?>">
<meta property="og:image" content="https://gudeg.xo.je/assets/og-image.png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:image" content="https://gudeg.xo.je/assets/og-image.png">
<meta property="og:locale" content="id_ID">

<!-- Favicon -->
<link rel="icon" type="image/x-icon" href="/assets/favicon.ico">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<link rel="apple-touch-icon" href="/assets/favicon.png">

<title>GUDEG - <?= htmlspecialchars($title) ?></title>
<link rel="stylesheet" href="/assets/tabler/tabler-icons.min.css">
<!-- Google Fonts dihapus: font-src 'self' di CSP tidak mengizinkan CDN eksternal. System font stack di --sans dan --mono sudah menjadi fallback. -->
<style nonce="<?= csp_nonce() ?>">
/* ── Reset & tokens ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root,
[data-theme="dark"] {
  /* Background */
  --bg:             #07090f;
  --bg-mesh:        radial-gradient(ellipse at 10% 10%, rgba(19,81,180,.12) 0%, transparent 55%),
                    radial-gradient(ellipse at 90% 85%, rgba(15,60,140,.08) 0%, transparent 55%),
                    radial-gradient(ellipse at 55% 0%,  rgba(10,40,100,.06) 0%, transparent 45%),
                    #07090f;

  /* Glass */
  --glass:          rgba(10,14,26,.75);
  --glass-hi:       rgba(13,18,32,.88);
  --glass-border:   rgba(255,255,255,.06);
  --glass-border-hi:rgba(255,255,255,.10);

  /* Elevation */
  --elev-1: 0 1px 3px rgba(0,0,0,.45), 0 1px 2px rgba(0,0,0,.5);
  --elev-2: 0 4px 12px rgba(0,0,0,.5),  0 1px 4px rgba(0,0,0,.4);
  --elev-3: 0 8px 32px rgba(0,0,0,.55), 0 2px 8px rgba(0,0,0,.4);
  --elev-inset: inset 0 1px 0 rgba(255,255,255,.05), inset 0 -1px 0 rgba(0,0,0,.15);

  /* Accent — biru pemerintah BSSN/Komdigi */
  --accent:         #4A90D9;
  --accent-deep:    #1351B4;
  --accent-glow:    rgba(74,144,217,.18);
  --accent-glow-sm: rgba(74,144,217,.09);
  --accent-dim:     rgba(74,144,217,.75);
  --accent-bg:      rgba(19,81,180,.08);
  --accent-bdr:     rgba(74,144,217,.22);

  /* Text */
  --text:           #e8edf5;
  --muted-hi:       #8da8cc;
  --muted:          #3d5470;

  /* Status */
  --danger:         #e05252;
  --danger-bg:      rgba(224,82,82,.08);
  --danger-bdr:     rgba(224,82,82,.22);
  --success:        #2ecc71;

  /* Type — lebih formal, readable */
  --sans:           'Segoe UI', 'Noto Sans', system-ui, -apple-system, Arial, sans-serif;
  --mono:           'Consolas', 'Courier New', monospace;

  /* Layout */
  --sb-expanded:    210px;
  --sb-collapsed:   40px;
  --topbar-h:       48px;
  --radius:         8px;
  --radius-sm:      5px;
  --transition-fast:.13s cubic-bezier(.4,0,.2,1);
  --transition-med: .22s cubic-bezier(.4,0,.2,1);
  --autofill-bg:    #07090f;
  --autofill-txt:   #e8edf5;
}

[data-theme="bright"] {
  --bg:             #f2f5fb;
  --bg-mesh:        radial-gradient(ellipse at 10% 10%, rgba(19,81,180,.08) 0%, transparent 55%),
                    radial-gradient(ellipse at 90% 85%, rgba(19,81,180,.05) 0%, transparent 55%),
                    #f2f5fb;

  --glass:          rgba(230,238,252,.80);
  --glass-hi:       rgba(255,255,255,.95);
  --glass-border:   rgba(19,81,180,.10);
  --glass-border-hi:rgba(19,81,180,.18);

  --elev-1: 0 1px 3px rgba(0,0,0,.07),  0 1px 2px rgba(0,0,0,.05);
  --elev-2: 0 4px 12px rgba(0,0,0,.09), 0 1px 4px rgba(0,0,0,.06);
  --elev-3: 0 8px 32px rgba(0,0,0,.11), 0 2px 8px rgba(0,0,0,.07);
  --elev-inset: inset 0 1px 0 rgba(255,255,255,.75), inset 0 -1px 0 rgba(0,0,0,.04);

  --accent:         #1351B4;
  --accent-deep:    #0D3D8C;
  --accent-glow:    rgba(19,81,180,.20);
  --accent-glow-sm: rgba(19,81,180,.10);
  --accent-dim:     rgba(19,81,180,.80);
  --accent-bg:      rgba(19,81,180,.07);
  --accent-bdr:     rgba(19,81,180,.25);

  --text:           #0d1829;
  --muted-hi:       #1e3a6a;
  --muted:          #5570a0;

  --danger:         #c0392b;
  --danger-bg:      rgba(192,57,43,.08);
  --danger-bdr:     rgba(192,57,43,.22);
  --success:        #1e8449;

  --autofill-bg:    #f2f5fb;
  --autofill-txt:   #0d1829;
}

html { -webkit-text-size-adjust: 100%; }

::-webkit-scrollbar { width: 4px; height: 4px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: rgba(255,255,255,.08); border-radius: 4px; }
[data-theme="bright"] ::-webkit-scrollbar-thumb { background: rgba(19,81,180,.15); }

body {
  background: var(--bg-mesh);
  background-attachment: fixed;
  color: var(--text);
  font-family: var(--sans);
  font-size: 14px;
  font-weight: 400;
  line-height: 1.6;
  min-height: 100vh;
  overflow-x: hidden;
  transition: background var(--transition-med), color var(--transition-med);
}
a { color: inherit; text-decoration: none; }

/* ── Sidebar overlay (mobile) ── */
#sidebar-overlay {
  display: none;
  position: fixed; inset: 0;
  background: rgba(0,0,0,.65);
  z-index: 40;
  backdrop-filter: blur(3px);
  -webkit-backdrop-filter: blur(3px);
}
#sidebar-overlay.open { display: block; }

/* ── Sidebar shell ── */
#sidebar {
  position: fixed;
  top: 0; left: 0;
  width: var(--sb-expanded);
  height: 100%;
  height: 100dvh;
  background: var(--glass);
  backdrop-filter: blur(24px) saturate(160%);
  -webkit-backdrop-filter: blur(24px) saturate(160%);
  border-right: 1px solid var(--glass-border-hi);
  box-shadow: 3px 0 24px rgba(0,0,0,.35);
  display: flex;
  flex-direction: column;
  z-index: 50;
  overflow: hidden;
  transition: width var(--transition-med), transform var(--transition-med);
}

/* Accent stripe top */
#sidebar::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 3px;
  background: linear-gradient(90deg, var(--accent-deep), var(--accent), var(--accent-deep));
  opacity: .85;
  z-index: 1;
  pointer-events: none;
}

@media (max-width: 767px) {
  #sidebar { transform: translateX(-100%); width: var(--sb-expanded) !important; }
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

/* ── Brand ── */
.sb-brand {
  padding: 14px 14px 12px;
  border-bottom: 1px solid var(--glass-border-hi);
  display: flex; align-items: center; gap: 10px;
  flex-shrink: 0; overflow: hidden;
  margin-top: 3px; /* clear the accent stripe */
}
.sb-shield {
  flex-shrink: 0; width: 30px; height: 30px;
  display: flex; align-items: center; justify-content: center;
  background: var(--accent-deep);
  border: 1px solid var(--accent-bdr);
  border-radius: 6px;
  box-shadow: 0 2px 8px rgba(19,81,180,.35);
}
.sb-shield i { font-size: 16px; color: #fff; }
.sb-brand-text { overflow: hidden; white-space: nowrap; }
.sb-title {
  font-family: var(--sans);
  font-size: 13px; font-weight: 700; letter-spacing: .02em; line-height: 1.2;
  color: var(--text);
}
.sb-title .gudeg { color: var(--accent); }
.sb-title .jogja { color: var(--muted-hi); }
.sb-sub {
  font-size: 10px; color: var(--muted);
  letter-spacing: .04em; margin-top: 2px;
  font-family: var(--sans);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* ── User panel ── */
.sb-user {
  padding: 10px 14px;
  border-bottom: 1px solid var(--glass-border);
  display: flex; align-items: flex-start; gap: 10px;
  flex-shrink: 0; overflow: hidden;
  min-height: 68px;
}
.sb-avatar {
  width: 32px; height: 32px; border-radius: 50%;
  background: var(--accent-deep);
  border: 2px solid var(--accent-bdr);
  display: flex; align-items: center; justify-content: center;
  font-size: 12px; font-weight: 700; color: #fff;
  flex-shrink: 0; letter-spacing: .02em; margin-top: 1px;
  font-family: var(--sans);
}
.sb-user-info { min-width: 0; flex: 1; }
.sb-user-name {
  font-size: 13px; color: var(--text); font-weight: 600;
  white-space: normal; line-height: 1.3; word-break: break-word;
}
.sb-user-email {
  font-size: 10px; font-weight: 400; color: var(--muted); font-family: var(--sans);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px;
}
.sb-role {
  display: inline-block; margin-top: 4px;
  background: var(--accent-bg); border: 1px solid var(--accent-bdr);
  color: var(--accent); font-size: 11px;
  text-transform: uppercase; letter-spacing: .06em;
  padding: 1px 6px; border-radius: 3px;
  font-family: var(--sans); font-weight: 600;
}

/* ── Nav ── */
.sb-nav { flex: 1; padding: 8px 0; overflow-y: auto; overflow-x: hidden; }
.sb-section-label {
  font-size: 9px; text-transform: uppercase; letter-spacing: .10em;
  color: var(--muted); padding: 10px 14px 4px;
  white-space: nowrap; overflow: hidden;
  font-family: var(--sans); font-weight: 700;
}
.sb-section-label--mt { margin-top: 4px; }
.sb-link {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 14px;
  font-size: 13px; font-weight: 500;
  color: var(--muted-hi);
  border-left: 3px solid transparent;
  white-space: nowrap; overflow: hidden;
  transition: color var(--transition-fast), background var(--transition-fast), border-color var(--transition-fast);
  font-family: var(--sans);
}
.sb-link i { font-size: 16px; width: 18px; flex-shrink: 0; opacity: .8; }
.sb-link:hover {
  color: var(--accent);
  background: var(--accent-bg);
}
.sb-link.active {
  color: var(--accent);
  background: var(--accent-bg);
  border-left-color: var(--accent);
  font-weight: 600;
}
.sb-link.active i { opacity: 1; }

/* ── Collapsed state ── */
#sidebar.collapsed .sb-brand-text,
#sidebar.collapsed .sb-user-info,
#sidebar.collapsed .sb-section-label,
#sidebar.collapsed .sb-link span { display: none; }
#sidebar.collapsed .sb-brand { justify-content: center; padding: 11px 0; gap: 0; }
#sidebar.collapsed .sb-user  { justify-content: center; padding: 8px 0; gap: 0; }
#sidebar.collapsed .sb-link  { justify-content: center; padding: 8px 0; gap: 0; border-left-width: 3px; }
#sidebar.collapsed .sb-section-label { padding: 4px 0; }
#sidebar.collapsed .sb-footer { padding: 0; align-items: center; }
#sidebar.collapsed .sb-datetime { display: none; }
#sidebar.collapsed .sb-divider { display: none; }
#sidebar.collapsed .sb-logout-wrap { padding: 6px 0; width: 100%; }
#sidebar.collapsed .sb-logout { justify-content: center; padding: 6px 0; border: none; border-radius: 0; width: 100%; }
#sidebar.collapsed .sb-logout span { display: none; }
#sidebar.collapsed .sb-collapse-wrap { border-top: none; }
#sidebar.collapsed .sb-theme-wrap { padding: 4px 0; }
#sidebar.collapsed .sb-theme-btn { justify-content: center; border: none; border-radius: 0; width: 100%; padding: 6px 0; }
#sidebar.collapsed .sb-theme-btn .theme-toggle-label { display: none; }
#sidebar.collapsed .sb-shield { margin: 0; }

/* ── Theme toggle (sidebar) ── */
.sb-theme-wrap { padding: 4px 10px; }
.sb-theme-btn {
  display: flex; align-items: center; gap: 7px;
  padding: 7px 10px;
  font-size: 12px; font-weight: 500;
  color: var(--muted-hi);
  border: 1px solid var(--glass-border-hi); border-radius: var(--radius-sm);
  width: 100%; cursor: pointer; background: transparent;
  font-family: var(--sans);
  transition: border-color var(--transition-fast), color var(--transition-fast);
  white-space: nowrap;
}
.sb-theme-btn:hover { border-color: var(--accent-bdr); color: var(--accent); }
.sb-theme-btn i { font-size: 15px; flex-shrink: 0; }

/* ── Footer ── */
.sb-footer {
  padding: 0 0 6px;
  border-top: 1px solid var(--glass-border);
  flex-shrink: 0; overflow: hidden;
  display: flex; flex-direction: column; gap: 0;
}
.sb-datetime { padding: 8px 14px 6px; }
.sb-divider { border: none; border-top: 1px solid var(--glass-border); margin: 0; }
.sb-logout-wrap { padding: 5px 10px; }
.sb-collapse-wrap {
  border-top: 1px solid var(--glass-border);
  display: flex; justify-content: center; padding: 5px 0;
}
.sb-datetime { font-size: 11px; color: var(--muted); font-family: var(--sans); }
.sb-datetime .dt-date { color: var(--muted-hi); letter-spacing: .02em; font-weight: 500; }
.sb-datetime .dt-time {
  font-size: 16px; font-weight: 700;
  color: var(--accent); letter-spacing: .06em; margin-top: 2px;
  font-family: var(--sans);
}
.sb-logout {
  display: flex; align-items: center; gap: 7px;
  padding: 7px 10px;
  font-size: 12px; font-weight: 500;
  color: var(--danger);
  border: 1px solid var(--danger-bdr); border-radius: var(--radius-sm);
  width: 100%; cursor: pointer; background: transparent;
  font-family: var(--sans);
  transition: border-color var(--transition-fast), color var(--transition-fast), background var(--transition-fast);
  white-space: nowrap;
}
.sb-logout:hover {
  border-color: var(--danger);
  background: var(--danger-bg);
}
.sb-logout i { font-size: 16px; color: var(--danger); flex-shrink: 0; }
.sb-collapse-btn {
  display: flex; align-items: center; justify-content: center;
  background: none; border: none; cursor: pointer;
  color: var(--muted-hi); padding: 2px;
  transition: color var(--transition-fast);
}
.sb-collapse-btn:hover { color: var(--accent); }
.sb-collapse-btn i { font-size: 14px; }

/* ── Topbar (mobile only) ── */
#topbar {
  display: none;
  position: sticky; top: 0;
  height: var(--topbar-h);
  background: var(--glass);
  backdrop-filter: blur(20px) saturate(160%);
  -webkit-backdrop-filter: blur(20px) saturate(160%);
  border-bottom: 1px solid var(--glass-border-hi);
  box-shadow: 0 1px 12px rgba(0,0,0,.2);
  align-items: center;
  padding: 0 14px; gap: 10px;
  z-index: 30;
}
@media (max-width: 767px) { #topbar { display: flex; } }

#hamburger {
  background: none; border: none; cursor: pointer;
  display: flex; flex-direction: column; gap: 4px; padding: 5px;
}
#hamburger span {
  display: block; width: 18px; height: 2px;
  background: var(--muted-hi); border-radius: 1px;
  transition: background var(--transition-fast);
}
#hamburger:hover span { background: var(--accent); }

.topbar-title { font-family: var(--sans); font-size: 14px; font-weight: 700; letter-spacing: .02em; flex: 1; color: var(--text); }
.topbar-title .gudeg { color: var(--accent); }
.topbar-title .jogja { color: var(--muted-hi); }

.topbar-theme-btn {
  display: flex; align-items: center; justify-content: center;
  width: 32px; height: 32px;
  background: transparent; border: 1px solid var(--glass-border-hi); border-radius: var(--radius-sm);
  cursor: pointer; color: var(--muted-hi);
  transition: border-color var(--transition-fast), color var(--transition-fast);
  flex-shrink: 0;
}
.topbar-theme-btn:hover { border-color: var(--accent-bdr); color: var(--accent); }
.topbar-theme-btn i { font-size: 16px; }

/* ── Page layout ── */
#layout { display: flex; min-height: 100vh; }
#main-content {
  flex: 1; min-width: 0;
  padding: 22px 20px;
  transition: margin-left var(--transition-med);
}
@media (min-width: 768px) {
  #main-content { margin-left: var(--sb-expanded); }
  body.sb-collapsed #main-content { margin-left: var(--sb-collapsed); }
}
@media (max-width: 767px) {
  #main-content { padding: 14px 12px; }
}

/* ── Shared UI components ── */
.page-title {
  font-size: 17px; font-weight: 700;
  color: var(--text);
  letter-spacing: .01em; line-height: 1.3;
  font-family: var(--sans);
}
.page-title span, .page-title strong { color: var(--accent); }
.page-subtitle { color: var(--muted-hi); font-size: 12px; font-weight: 400; margin-top: 4px; font-family: var(--sans); }
.page-header { margin-bottom: 20px; }

.section-label {
  font-size: 10px; font-weight: 700;
  text-transform: uppercase; letter-spacing: .10em;
  color: var(--muted); margin-bottom: 10px;
  font-family: var(--sans);
}

.card {
  background: var(--glass-hi);
  backdrop-filter: blur(16px) saturate(140%);
  -webkit-backdrop-filter: blur(16px) saturate(140%);
  border: 1px solid var(--glass-border-hi);
  border-radius: var(--radius);
  box-shadow: var(--elev-2);
  transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
  position: relative; overflow: hidden;
}
.card:hover {
  border-color: var(--accent-bdr);
  box-shadow: var(--elev-3);
}
.card-pad { padding: 16px 18px; }

.msg-box {
  background: var(--accent-bg);
  border: 1px solid var(--accent-bdr);
  color: var(--accent);
  border-radius: var(--radius-sm);
  padding: 10px 14px;
  font-size: 13px;
  margin-bottom: 14px;
  font-family: var(--sans);
  box-shadow: var(--elev-1);
}
.msg-box.error {
  background: var(--danger-bg);
  border-color: var(--danger-bdr);
  color: var(--danger);
}

.badge {
  display: inline-block;
  padding: 2px 8px; border-radius: 3px;
  font-size: 10px; font-family: var(--sans);
  text-transform: uppercase; letter-spacing: .05em;
  font-weight: 600;
}
.badge-admin    { background: var(--accent-bg);        color: var(--accent);  border: 1px solid var(--accent-bdr); }
.badge-user     { background: rgba(99,102,241,.08);    color: #818cf8;        border: 1px solid rgba(99,102,241,.20); }
.badge-guest    { background: rgba(100,116,139,.08);   color: var(--muted-hi);border: 1px solid var(--glass-border-hi); }
.badge-active   { background: rgba(46,204,113,.08);    color: var(--success); border: 1px solid rgba(46,204,113,.22); }
.badge-inactive { background: var(--danger-bg);        color: var(--danger);  border: 1px solid var(--danger-bdr); }
.badge-hmac     { background: rgba(234,179,8,.08);     color: #f59e0b;        border: 1px solid rgba(234,179,8,.22); }
.badge-rsa      { background: var(--accent-bg);        color: var(--accent);  border: 1px solid var(--accent-bdr); }

button, .btn { font-family: var(--sans); cursor: pointer; }

/* ── Mobile ── */
@media (max-width: 767px) {
  #main-content { padding: 14px 12px 20px; }
  .page-header { margin-bottom: 14px; }
  .page-title { font-size: 15px; }
  .sb-user-email { max-width: 140px; }
  #topbar { height: 50px; padding: 0 12px; }
  .badge { font-size: 10px; padding: 2px 6px; }
}
@media (max-width: 400px) {
  #main-content { padding: 12px 10px 20px; }
  .sb-user-email { max-width: 115px; }
}

/* ── Reduced motion ── */
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after { transition: none !important; animation: none !important; }
}
</style>

<!-- Apply theme BEFORE paint to prevent flash -->
<script nonce="<?= csp_nonce() ?>">
(function() {
  try {
    var t = localStorage.getItem('gudeg_theme') || 'dark';
    if (t === 'light') { t = 'bright'; localStorage.setItem('gudeg_theme', 'bright'); }
    document.documentElement.setAttribute('data-theme', t);
  } catch(e) {}
})();
</script>
</head>
<body>

<!-- Overlay (mobile) -->
<div id="sidebar-overlay"></div>

<!-- Topbar (mobile only) -->
<div id="topbar">
  <button id="hamburger" aria-label="Buka menu">
    <span></span><span></span><span></span>
  </button>
  <span class="topbar-title">
    <span class="gudeg">GUDEG</span> <span class="jogja">JOGJA</span>
  </span>
  <!-- Theme toggle di topbar (mobile) -->
  <button class="topbar-theme-btn" aria-label="Ganti tema">
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
    <div class="sb-section-label sb-section-label--mt">Admin</div>
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
      <button class="sb-theme-btn" aria-label="Ganti tema">
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
      <button class="sb-collapse-btn" id="collapse-btn" aria-label="Ciutkan sidebar">
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

<script nonce="<?= csp_nonce() ?>">
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
      lb.textContent = theme === 'bright' ? 'Gelap' : 'Terang';
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

// Bind event listeners - pengganti onclick= yang diblokir CSP (#79 #80)
(function() {
  function bindEvents() {
    var overlay = document.getElementById('sidebar-overlay');
    if (overlay) overlay.addEventListener('click', function() { if (window.closeSidebar) closeSidebar(); });

    var hamburger = document.getElementById('hamburger');
    if (hamburger) hamburger.addEventListener('click', function() { if (window.toggleSidebar) toggleSidebar(); });

    document.querySelectorAll('.topbar-theme-btn, .sb-theme-btn').forEach(function(btn) {
      btn.addEventListener('click', function() { if (window.toggleTheme) toggleTheme(); });
    });

    var collapseBtn = document.getElementById('collapse-btn');
    if (collapseBtn) collapseBtn.addEventListener('click', function() { if (window.toggleCollapse) toggleCollapse(); });
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindEvents);
  } else {
    bindEvents();
  }
})();
</script>
</body>
</html>
<?php
}