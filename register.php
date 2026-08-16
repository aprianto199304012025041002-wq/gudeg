<?php
require_once __DIR__ . '/includes/functions.php';
session_start_safe();

if (!empty($_SESSION['user_id'])) {
    header("Location: /dashboard.php");
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $name     = sanitize_input($_POST['name'] ?? '', 100);
    $email    = sanitize_input($_POST['email'] ?? '', 150);
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if (!$name || !$email || !$password || !$confirm) {
        $error = 'Semua field wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif ($len_err = validate_length('Nama', $name, 100) ?? validate_length('Email', $email, 150)) {
        $error = $len_err;
    } elseif ($strength_err = validate_password_strength($password)) {
        $error = $strength_err;
    } elseif ($password !== $confirm) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        // Proteksi double-submit: tolak kalau request register lain sedang diproses
        // dalam session yang sama (klik dua kali, submit ganda).
        if (!empty($_SESSION['register_lock'])) {
            $error = 'Permintaan sedang diproses, mohon tunggu.';
        } else {
            $_SESSION['register_lock'] = true;
            try {
                $sb    = getSupabase();
                $check = $sb->selectOne('users', ['email' => $email], 'id');
                if ($check) {
                    $error = 'Email sudah terdaftar.';
                } else {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $now  = date('Y-m-d H:i:s');
                    $inserted = $sb->insert('users', [
                        'name'                 => $name,
                        'email'                => $email,
                        'password'             => $hash,
                        'role'                 => 'guest',
                        'is_active'            => false,
                        'password_changed_at'  => $now,
                        'created_at'           => $now,
                        'updated_at'           => $now,
                    ]);
                    $new_id = $inserted['id'] ?? null;
                    log_activity($new_id, $name, $email, 'Register Akun Baru (Guest)');
                    $success = true;
                }
            } catch (\Throwable $e) {
                $error = 'Terjadi kesalahan, silakan coba lagi.';
                function_exists('gudeg_log') ? gudeg_log('ERROR', 'register.php', $e->getMessage()) : error_log('register.php: ' . $e->getMessage());
            } finally {
                // Selalu clear lock setelah selesai — sukses maupun gagal
                unset($_SESSION['register_lock']);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>GUDEG - Register</title>
<meta name="description" content="Daftar akun GUDEG — sistem SSO terpadu Balai Monitor Spektrum Frekuensi Radio Yogyakarta.">
<meta name="robots" content="index, follow">
<meta property="og:type" content="website">
<meta property="og:title" content="GUDEG - Register">
<meta property="og:description" content="Daftar akun GUDEG — sistem SSO terpadu Balai Monitor Spektrum Frekuensi Radio Yogyakarta.">
<meta property="og:url" content="https://gudeg.xo.je/register.php">
<meta property="og:site_name" content="GUDEG JOGJA">
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="GUDEG - Register">
<meta name="twitter:description" content="Daftar akun GUDEG — sistem SSO terpadu Balai Monitor Spektrum Frekuensi Radio Yogyakarta.">
<link rel="icon" type="image/x-icon" href="/assets/favicon.ico">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<link rel="apple-touch-icon" href="/assets/favicon.png">
<link rel="stylesheet" href="/assets/tabler/tabler-icons.min.css">
<script nonce="<?= csp_nonce() ?>">
(function(){
  try {
    var t = localStorage.getItem('gudeg_theme') || 'dark';
    if (t === 'light') { t = 'bright'; localStorage.setItem('gudeg_theme', 'bright'); }
    document.documentElement.setAttribute('data-theme', t);
  } catch(e) {}
})();
</script>
<style nonce="<?= csp_nonce() ?>">
  body { background: #080a0e; margin: 0; }

/* ── Success message ── */
.success-msg {
  background: var(--success-bg);
  border: 1px solid var(--success-bdr);
  color: var(--success);
  border-radius: var(--radius-sm);
  padding: 12px 14px;
  font-size: 13px;
  margin-bottom: 14px;
  font-family: var(--mono);
  box-shadow: 0 0 16px rgba(34,197,94,.08), var(--elev-1);
}
.success-msg a { color: var(--success); }

/* ── Note ── */
.note {
  font-size: 11px; color: var(--muted);
  margin-top: 8px; letter-spacing: .03em; line-height: 1.6;
  font-family: var(--mono);
}
.note strong { color: var(--muted-hi); }

/* ── Password hint ── */
#pw-hint {
  font-size: 10px; line-height: 1.9;
  color: var(--muted); font-family: var(--mono);
  margin-top: 8px;
  padding: 10px 12px;
  background: rgba(0,0,0,.15);
  border-radius: var(--radius-sm);
  border: 1px solid var(--glass-border);
}
[data-theme="bright"] #pw-hint { background: rgba(0,0,0,.03); }

/* ── Bubble success ── */
.bubble.success { background: rgba(34,197,94,.1); border-color: rgba(34,197,94,.3); color: #86efac; }
[data-theme="bright"] .bubble.success { color: #166534; }
</style>
<style nonce="<?= csp_nonce() ?>">
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

/* ── Tokens ── */
:root,
[data-theme="dark"] {
  --bg:             #05080f;
  --bg-mesh:        radial-gradient(ellipse at 20% 20%, rgba(0,100,200,.15) 0%, transparent 55%),
                    radial-gradient(ellipse at 80% 85%, rgba(0,200,180,.08) 0%, transparent 55%),
                    radial-gradient(ellipse at 65% 5%, rgba(120,0,255,.07) 0%, transparent 45%),
                    #05080f;

  /* Glass */
  --glass:          rgba(8,12,22,.72);
  --glass-hi:       rgba(10,15,28,.85);
  --glass-border:   rgba(255,255,255,.07);
  --glass-border-hi:rgba(255,255,255,.13);

  /* Elevation */
  --elev-1: 0 1px 3px rgba(0,0,0,.5), 0 1px 2px rgba(0,0,0,.6);
  --elev-2: 0 4px 12px rgba(0,0,0,.55), 0 1px 4px rgba(0,0,0,.5);
  --elev-3: 0 8px 32px rgba(0,0,0,.6), 0 2px 8px rgba(0,0,0,.45);
  --elev-4: 0 20px 60px rgba(0,0,0,.7), 0 4px 20px rgba(0,0,0,.5);
  --elev-inset: inset 0 1px 0 rgba(255,255,255,.05), inset 0 -1px 0 rgba(0,0,0,.2);

  /* Accent */
  --accent:         #00e5ff;
  --accent-glow:    rgba(0,229,255,.2);
  --accent-glow-sm: rgba(0,229,255,.1);
  --accent-dim:     rgba(0,229,255,.7);
  --accent-bg:      rgba(0,229,255,.06);
  --accent-bdr:     rgba(0,229,255,.2);

  /* Text */
  --text:           #f1f5f9;
  --muted-hi:       #7a9ab8;
  --muted:          #3a5070;

  /* Status */
  --danger:         #ef4444;
  --success:        #22c55e;
  --success-bg:     rgba(34,197,94,.08);
  --success-bdr:    rgba(34,197,94,.25);
  --danger-glow:    rgba(239,68,68,.18);
  --warning-bg:     rgba(234,179,8,.1);
  --warning-bdr:    rgba(234,179,8,.3);

  /* Type */
  --sans:           'DM Sans', 'Segoe UI', system-ui, -apple-system, sans-serif;
  --mono:           'Space Mono', 'Courier New', monospace;

  /* Misc */
  --radius:         10px;
  --radius-sm:      7px;
  --radius-lg:      16px;
  --transition-fast:.12s cubic-bezier(.4,0,.2,1);
  --transition-med: .22s cubic-bezier(.4,0,.2,1);
  --autofill-bg:    #05080f;
  --autofill-txt:   #f1f5f9;
}

[data-theme="bright"] {
  --bg:             #eef2f7;
  --bg-mesh:        radial-gradient(ellipse at 20% 20%, rgba(2,132,199,.07) 0%, transparent 55%),
                    radial-gradient(ellipse at 80% 85%, rgba(0,180,160,.05) 0%, transparent 55%),
                    #eef2f7;
  --glass:          rgba(240,244,250,.82);
  --glass-hi:       rgba(255,255,255,.92);
  --glass-border:   rgba(0,0,0,.06);
  --glass-border-hi:rgba(0,0,0,.10);
  --elev-1: 0 1px 3px rgba(0,0,0,.08), 0 1px 2px rgba(0,0,0,.06);
  --elev-2: 0 4px 12px rgba(0,0,0,.1), 0 1px 4px rgba(0,0,0,.07);
  --elev-3: 0 8px 32px rgba(0,0,0,.12), 0 2px 8px rgba(0,0,0,.08);
  --elev-4: 0 20px 60px rgba(0,0,0,.15), 0 4px 20px rgba(0,0,0,.1);
  --elev-inset: inset 0 1px 0 rgba(255,255,255,.8), inset 0 -1px 0 rgba(0,0,0,.05);
  --accent:         #0284c7;
  --accent-glow:    rgba(2,132,199,.15);
  --accent-glow-sm: rgba(2,132,199,.08);
  --accent-dim:     rgba(2,132,199,.8);
  --accent-bg:      rgba(2,132,199,.07);
  --accent-bdr:     rgba(2,132,199,.25);
  --text:           #0f172a;
  --muted-hi:       #475569;
  --muted:          #94a3b8;
  --danger:         #dc2626;
  --success:        #16a34a;
  --success-bg:     rgba(22,163,74,.07);
  --success-bdr:    rgba(22,163,74,.25);
  --danger-glow:    rgba(220,38,38,.12);
  --warning-bg:     rgba(217,119,6,.08);
  --warning-bdr:    rgba(217,119,6,.25);
  --autofill-bg:    #ffffff;
  --autofill-txt:   #0f172a;
}

html { -webkit-text-size-adjust: 100%; }

/* ── Scrollbar ── */
::-webkit-scrollbar { width: 4px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: rgba(255,255,255,.1); border-radius: 4px; }
[data-theme="bright"] ::-webkit-scrollbar-thumb { background: rgba(0,0,0,.12); }

body {
  background: var(--bg-mesh);
  background-attachment: fixed;
  color: var(--text);
  font-family: var(--sans);
  font-size: 14px;
  min-height: 100vh;
  min-height: 100dvh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.25rem 1rem;
  transition: background var(--transition-med), color var(--transition-med);
  position: relative;
}

/* Ambient glow orbs */
body::before,
body::after {
  content: '';
  position: fixed;
  border-radius: 50%;
  pointer-events: none;
  z-index: 0;
  filter: blur(80px);
}
body::before {
  width: 500px; height: 500px;
  top: -150px; left: -150px;
  background: radial-gradient(circle, rgba(0,229,255,.07) 0%, transparent 70%);
}
body::after {
  width: 400px; height: 400px;
  bottom: -100px; right: -50px;
  background: radial-gradient(circle, rgba(120,58,237,.06) 0%, transparent 70%);
}
[data-theme="bright"] body::before { background: radial-gradient(circle, rgba(2,132,199,.05) 0%, transparent 70%); }
[data-theme="bright"] body::after  { background: radial-gradient(circle, rgba(120,58,237,.04) 0%, transparent 70%); }

.container { width: 100%; max-width: 400px; position: relative; z-index: 1; }

/* ── Theme FAB ── */
.theme-fab {
  position: fixed;
  top: calc(14px + env(safe-area-inset-top)); right: 14px;
  z-index: 999;
  display: flex; align-items: center; justify-content: center;
  width: 38px; height: 38px;
  background: var(--glass);
  backdrop-filter: blur(20px) saturate(160%);
  -webkit-backdrop-filter: blur(20px) saturate(160%);
  border: 1px solid var(--glass-border-hi);
  border-radius: var(--radius);
  cursor: pointer;
  color: var(--muted-hi);
  box-shadow: var(--elev-3), var(--elev-inset);
  transition: border-color var(--transition-fast), color var(--transition-fast), box-shadow var(--transition-fast);
  -webkit-tap-highlight-color: transparent;
}
.theme-fab:hover {
  border-color: var(--accent-bdr);
  color: var(--accent);
  box-shadow: var(--elev-3), 0 0 16px var(--accent-glow), var(--elev-inset);
}
.theme-fab i { font-size: 17px; }

/* ── Logo ── */
.logo-area { text-align: center; margin-bottom: 28px; }
.logo-shield {
  width: 58px; height: 58px;
  background: var(--accent-bg);
  border: 1px solid var(--accent-bdr);
  border-radius: var(--radius-lg);
  display: inline-flex; align-items: center; justify-content: center;
  margin-bottom: 16px;
  box-shadow: 0 0 32px var(--accent-glow), var(--elev-2), var(--elev-inset);
  transition: box-shadow var(--transition-med), transform var(--transition-med);
  position: relative;
}
/* Accent shimmer on logo */
.logo-shield::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 1px;
  background: linear-gradient(90deg, transparent, rgba(0,229,255,.4), transparent);
  border-radius: var(--radius-lg) var(--radius-lg) 0 0;
}
.logo-shield:hover {
  box-shadow: 0 0 48px var(--accent-glow), var(--elev-3), var(--elev-inset);
  transform: translateY(-1px);
}
.logo-shield i { font-size: 28px; color: var(--accent); }
.logo-title {
  font-family: var(--mono);
  font-size: 18px; font-weight: 700;
  letter-spacing: .08em; line-height: 1.1;
}
.logo-title .gudeg {
  background: linear-gradient(135deg, var(--accent), #7dd3fc);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
.logo-title .jogja { color: #eab308; -webkit-text-fill-color: #eab308; }
.logo-acronym {
  font-size: 10px; color: var(--muted);
  text-transform: uppercase; letter-spacing: .08em;
  margin-top: 10px; line-height: 1.8;
  font-family: var(--mono);
}
.logo-acronym strong {
  display: block; color: var(--muted-hi);
  font-size: 10px; letter-spacing: .05em;
  margin-top: 3px;
}

/* ── Card (glass) ── */
.card {
  background: var(--glass-hi);
  backdrop-filter: blur(24px) saturate(180%);
  -webkit-backdrop-filter: blur(24px) saturate(180%);
  border: 1px solid var(--glass-border-hi);
  border-radius: var(--radius-lg);
  padding: 26px 24px;
  box-shadow: var(--elev-4), var(--elev-inset);
  transition: border-color var(--transition-med), box-shadow var(--transition-med);
  position: relative;
  overflow: hidden;
}
/* Top shimmer line on card */
.card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 1px;
  background: linear-gradient(90deg, transparent, var(--glass-border-hi), transparent);
}

/* ── Form ── */
.form-group { margin-bottom: 16px; }
label {
  display: block;
  font-size: 10px; font-weight: 700;
  color: var(--muted-hi);
  text-transform: uppercase; letter-spacing: .12em;
  margin-bottom: 7px;
  font-family: var(--mono);
}
input[type="text"],
input[type="email"],
input[type="password"] {
  width: 100%;
  background: rgba(0,0,0,.2);
  border: 1px solid var(--glass-border-hi);
  border-radius: var(--radius-sm);
  padding: 10px 13px;
  color: var(--text);
  font-family: var(--sans);
  font-size: 14px;
  outline: none;
  transition: border-color var(--transition-fast), box-shadow var(--transition-fast), background var(--transition-fast);
  -webkit-appearance: none;
  box-shadow: var(--elev-inset);
}
[data-theme="bright"] input[type="text"],
[data-theme="bright"] input[type="email"],
[data-theme="bright"] input[type="password"] {
  background: rgba(255,255,255,.7);
}
input[type="text"]:focus,
input[type="email"]:focus,
input[type="password"]:focus {
  border-color: var(--accent-bdr);
  background: var(--accent-bg);
  box-shadow: 0 0 0 3px var(--accent-glow-sm), var(--elev-inset);
}
input:-webkit-autofill,
input:-webkit-autofill:hover,
input:-webkit-autofill:focus {
  -webkit-box-shadow: 0 0 0 1000px var(--autofill-bg) inset !important;
  -webkit-text-fill-color: var(--autofill-txt) !important;
  caret-color: var(--autofill-txt);
  border-color: var(--glass-border-hi) !important;
}

/* ── Remember row ── */
.remember-row {
  display: flex; align-items: center; gap: 8px;
  margin: 14px 0 8px;
}
.remember-row input[type="checkbox"] {
  width: 15px; height: 15px;
  accent-color: var(--accent);
  cursor: pointer; flex-shrink: 0;
}
.remember-row label {
  font-size: 11px; color: var(--muted-hi); cursor: pointer;
  text-transform: uppercase; letter-spacing: .08em;
  margin-bottom: 0;
}

/* ── Submit button ── */
.btn-submit {
  width: 100%;
  background: linear-gradient(135deg, var(--accent-bg), rgba(120,58,237,.08));
  color: var(--accent);
  border: 1px solid var(--accent-bdr);
  border-radius: var(--radius-sm);
  padding: 11px;
  font-family: var(--mono);
  font-size: 12px; font-weight: 700;
  text-transform: uppercase; letter-spacing: .12em;
  cursor: pointer;
  margin-top: 8px;
  box-shadow: 0 0 20px var(--accent-glow-sm), var(--elev-1);
  transition: background var(--transition-fast), border-color var(--transition-fast),
              box-shadow var(--transition-fast), transform var(--transition-fast);
  -webkit-tap-highlight-color: transparent;
  position: relative;
  overflow: hidden;
}
.btn-submit::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 1px;
  background: linear-gradient(90deg, transparent, rgba(0,229,255,.3), transparent);
}
.btn-submit:hover {
  background: linear-gradient(135deg, rgba(0,229,255,.12), rgba(120,58,237,.12));
  border-color: var(--accent);
  box-shadow: 0 0 28px var(--accent-glow), var(--elev-2);
}
.btn-submit:active { transform: scale(.98); }

/* ── Divider & links ── */
.divider { border: none; border-top: 1px solid var(--glass-border-hi); margin: 18px 0; }
.link-row {
  text-align: center; font-size: 12px;
  color: var(--muted);
  font-family: var(--mono);
  letter-spacing: .03em;
}
.link-row a { color: var(--accent); transition: color var(--transition-fast); }
.link-row a:hover { color: var(--text); }
.footer-note {
  text-align: center; margin-top: 20px;
  font-size: 10px; color: var(--muted);
  letter-spacing: .08em;
  text-transform: uppercase;
  font-family: var(--mono);
}

/* ── Bubbles ── */
.bubble-wrap {
  position: fixed;
  bottom: calc(16px + env(safe-area-inset-bottom)); right: 16px;
  display: flex; flex-direction: column-reverse; gap: 8px;
  z-index: 9999; width: 320px; pointer-events: none;
}
.bubble {
  border-radius: var(--radius); padding: 10px 32px 10px 14px;
  font-size: .8rem; line-height: 1.5; border: 1px solid;
  display: flex; align-items: flex-start; gap: 8px;
  pointer-events: all; animation: bubbleIn .25s ease;
  box-shadow: var(--elev-3); position: relative;
  backdrop-filter: blur(20px) saturate(160%);
  -webkit-backdrop-filter: blur(20px) saturate(160%);
}
@keyframes bubbleIn {
  from { opacity: 0; transform: translateY(10px); }
  to   { opacity: 1; transform: translateY(0); }
}
.bubble.danger  { background: rgba(239,68,68,.12); border-color: rgba(239,68,68,.3); color: #fca5a5; }
.bubble.warning { background: var(--warning-bg); border-color: var(--warning-bdr); color: #fde68a; }
[data-theme="bright"] .bubble.warning { color: #92400e; }
[data-theme="bright"] .bubble.danger  { color: #991b1b; }
.bubble i { font-size: 16px; flex-shrink: 0; margin-top: 1px; }
.bubble-body { flex: 1; min-width: 0; }
.bubble-title { font-weight: 700; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 2px; font-size: .72rem; font-family: var(--mono); }
.bubble-desc  { font-size: .78rem; opacity: .9; }
.bubble-close {
  position: absolute; top: 6px; right: 8px;
  cursor: pointer; opacity: .5; font-size: 16px;
  color: inherit; background: none; border: none; padding: 0; line-height: 1;
  -webkit-tap-highlight-color: transparent;
}
.bubble-close:hover { opacity: 1; }
.bubble-progress {
  position: absolute; bottom: 0; left: 0;
  height: 2px; background: currentColor; opacity: .35;
  border-radius: 0 0 var(--radius) var(--radius);
  animation: bubbleProgress 6s linear forwards;
}
@keyframes bubbleProgress { from { width: 100%; } to { width: 0%; } }

/* ── Responsive ── */
@media (max-width: 480px) {
  .bubble-wrap { bottom: calc(12px + env(safe-area-inset-bottom)); right: 8px; left: 8px; width: auto; }
  body { padding: 1rem .75rem; align-items: flex-start; padding-top: calc(60px + env(safe-area-inset-top)); }
  .container { max-width: 100%; }
  .card { padding: 20px 16px; }
  input[type="text"],
  input[type="email"],
  input[type="password"] { padding: 11px 12px; font-size: 16px; }
  .remember-row label { font-size: 12px; }
  .btn-submit { padding: 13px; font-size: 13px; }
  .theme-fab { top: calc(10px + env(safe-area-inset-top)); right: 10px; }
  .logo-shield { width: 48px; height: 48px; }
  .logo-shield i { font-size: 24px; }
  .logo-title { font-size: 16px; }
  .logo-acronym { font-size: 9px; }
}

/* ── Reduced motion ── */
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after { transition: none !important; animation: none !important; }
}

/* ── Success message ── */
.success-msg {
  background: var(--success-bg);
  border: 1px solid var(--success-bdr);
  color: var(--success);
  border-radius: var(--radius-sm);
  padding: 12px 14px;
  font-size: 13px;
  margin-bottom: 14px;
  font-family: var(--mono);
  box-shadow: 0 0 16px rgba(34,197,94,.08), var(--elev-1);
}
.success-msg a { color: var(--success); }
.note {
  font-size: 11px; color: var(--muted);
  margin-top: 8px; letter-spacing: .03em; line-height: 1.6;
  font-family: var(--mono);
}
.note strong { color: var(--muted-hi); }
#pw-hint {
  font-size: 10px; line-height: 1.9;
  color: var(--muted); font-family: var(--mono);
  margin-top: 8px; padding: 10px 12px;
  background: rgba(0,0,0,.15);
  border-radius: var(--radius-sm);
  border: 1px solid var(--glass-border);
}
[data-theme="bright"] #pw-hint { background: rgba(0,0,0,.03); }
.bubble.success { background: rgba(34,197,94,.1); border-color: rgba(34,197,94,.3); color: #86efac; }
[data-theme="bright"] .bubble.success { color: #166534; }
</style>
</head>
<body>

<button class="theme-fab" id="theme-fab-btn" aria-label="Ganti tema">
  <i class="ti ti-sun theme-toggle-icon" aria-hidden="true"></i>
</button>

<div class="container">
  <div class="logo-area">
    <div class="logo-shield"><i class="ti ti-shield-check" aria-hidden="true"></i></div>
    <div class="logo-title">
      <span class="gudeg">GUDEG</span> <span class="jogja">JOGJA</span>
    </div>
    <div class="logo-acronym">
      General Unified Database And Employee Gateway
      <strong>Balai Monitor Spektrum Frekuensi Radio Kelas I Yogyakarta</strong>
    </div>
  </div>

  <div class="card">
    <?php if ($success): ?>
      <div class="success-msg">
        Akun berhasil dibuat! <a href="/login.php">Login sekarang →</a>
      </div>
      <hr class="divider">
      <p class="link-row">Sudah punya akun? <a href="/login.php">Login di sini</a></p>

    <?php else: ?>
      <form method="POST">
        <?= csrf_field() ?>
        <div class="form-group">
          <label for="name">Nama Lengkap</label>
          <input type="text" id="name" name="name"
                 placeholder="Nama lengkap"
                 value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                 required autofocus autocomplete="name">
        </div>

        <div class="form-group">
          <label for="email">Email</label>
          <input type="email" id="email" name="email"
                 placeholder="user@example.com"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                 required autocomplete="email">
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password"
                 placeholder="Min. 8 karakter"
                 required autocomplete="new-password"
                 oninput="checkStrength(this.value)">
          <div id="pw-hint">
            <span id="pw-len">○ Min. 8 karakter</span><br>
            <span id="pw-upper">○ Huruf besar (A-Z)</span><br>
            <span id="pw-lower">○ Huruf kecil (a-z)</span><br>
            <span id="pw-num">○ Angka (0-9)</span><br>
            <span id="pw-sym">○ Karakter spesial (!, @, #, dll)</span>
          </div>
        </div>

        <div class="form-group">
          <label for="confirm">Konfirmasi Password</label>
          <input type="password" id="confirm" name="confirm"
                 placeholder="Ulangi password"
                 required autocomplete="new-password">
        </div>

        <p class="note">Akun baru akan berstatus <strong>Guest</strong> sampai diaktivasi admin.</p>
        <button type="submit" class="btn-submit">Buat Akun</button>
      </form>

      <hr class="divider">
      <p class="link-row">Sudah punya akun? <a href="/login.php">Login di sini</a></p>
    <?php endif; ?>
  </div>

  <p class="footer-note">© 2026 QOS Balmon Jogja Team</p>
</div>

<div class="bubble-wrap" id="bubbleWrap"></div>

<script nonce="<?= csp_nonce() ?>">
(function() {
  var THEME_KEY = 'gudeg_theme';
  function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    try { localStorage.setItem(THEME_KEY, theme); } catch(e) {}
    document.querySelectorAll('.theme-toggle-icon').forEach(function(ic) {
      ic.className = 'ti ' + (theme === 'bright' ? 'ti-moon' : 'ti-sun') + ' theme-toggle-icon';
    });
  }
  function getTheme() {
    try { return localStorage.getItem(THEME_KEY) || 'dark'; } catch(e) { return 'dark'; }
  }
  window.toggleTheme = function() { applyTheme(getTheme() === 'dark' ? 'bright' : 'dark'); };
  applyTheme(getTheme());
})();

function checkStrength(v) {
  function set(id, ok, label) {
    var el = document.getElementById(id);
    el.textContent = (ok ? '● ' : '○ ') + label;
    el.style.color = ok ? 'var(--success)' : 'var(--muted)';
  }
  set('pw-len',   v.length >= 8,           'Min. 8 karakter');
  set('pw-upper', /[A-Z]/.test(v),         'Huruf besar (A-Z)');
  set('pw-lower', /[a-z]/.test(v),         'Huruf kecil (a-z)');
  set('pw-num',   /[0-9]/.test(v),         'Angka (0-9)');
  set('pw-sym',   /[^a-zA-Z0-9]/.test(v), 'Karakter spesial (!, @, #, dll)');
}

function showBubble(type, icon, title, desc, duration) {
  duration = duration || 6000;
  var wrap = document.getElementById('bubbleWrap');
  var b = document.createElement('div');
  b.className = 'bubble ' + type;
  b.innerHTML =
    '<i class="ti ti-' + icon + '" aria-hidden="true"></i>' +
    '<div class="bubble-body"><div class="bubble-title">' + title + '</div>' +
    '<div class="bubble-desc">' + desc + '</div></div>' +
    '<button class="bubble-close" aria-label="Tutup" onclick="closeBubble(this.parentNode)">&#215;</button>' +
    '<div class="bubble-progress" style="animation-duration:' + (duration/1000) + 's"></div>';
  wrap.appendChild(b);
  setTimeout(function() { closeBubble(b); }, duration);
}
function closeBubble(b) {
  if (!b || !b.parentNode) return;
  b.style.transition = 'opacity .3s, transform .3s';
  b.style.opacity = '0';
  b.style.transform = 'translateY(10px)';
  setTimeout(function() { if (b.parentNode) b.remove(); }, 320);
}

<?php if ($error): ?>
showBubble('danger', 'alert-circle', 'Gagal Mendaftar', <?= json_encode(htmlspecialchars($error)) ?>);
<?php endif; ?>

// Bind event listeners - pengganti onclick= yang diblokir CSP nonce
(function() {
  var fab = document.getElementById('theme-fab-btn');
  if (fab) fab.addEventListener('click', function() { if (window.toggleTheme) toggleTheme(); });
})();
</script>
</body>
</html>