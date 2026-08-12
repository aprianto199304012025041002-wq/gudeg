<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/RateLimiter.php';
require_once __DIR__ . '/includes/LocalCache.php';
session_start_safe();

if (!empty($_SESSION['user_id'])) {
    header("Location: /dashboard.php");
    exit;
}

$error    = '';
$redirect = $_GET['redirect'] ?? '/dashboard.php';
$timeout  = ($_GET['reason'] ?? '') === 'timeout';

if (parse_url($redirect, PHP_URL_HOST) !== null) {
    $redirect = '/dashboard.php';
}

$remembered_email = $_COOKIE['gudeg_remember_email'] ?? '';

// Flag: apakah login berhasil dan perlu render bridge APK?
$login_success   = false;
$login_email     = '';
$login_password  = '';
$login_dest      = '/dashboard.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email    = sanitize_input($_POST['email'] ?? '', 150);
    $password = substr($_POST['password'] ?? '', 0, 200);
    $remember = !empty($_POST['remember']);

    if ($email && $password) {
        $ip = get_ip();
        if (RateLimiter::throttled($ip, $email)) {
            $secs = RateLimiter::secondsLeft($ip, $email);
            $mins = (int)ceil($secs / 60);
            $error = 'Terlalu banyak percobaan login. Coba lagi dalam ' . $mins . ' menit.';
        } else {
            try {
                $sb   = getSupabase();
                $user = $sb->selectOne('users', ['email' => $email, 'deleted_at' => 'is.null']);

                if ($user && password_verify($password, $user['password'])) {
                    if (!$user['is_active']) {
                        $error = 'Akun Anda tidak aktif. Hubungi administrator.';
                        RateLimiter::fail($ip, $email);
                    } else {
                        session_regenerate_id(true);

                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['name']    = $user['name'];
                        $_SESSION['email']   = $user['email'];
                        $_SESSION['role']    = $user['role'];

                        RateLimiter::reset($ip, $email);
                        LocalCache::setUser((string)$user['id'], [
                            'id'        => $user['id'],
                            'name'      => $user['name'],
                            'email'     => $user['email'],
                            'role'      => $user['role'],
                            'is_active' => $user['is_active'],
                        ]);

                        if ($remember) {
                            setcookie('gudeg_remember_email', $email, time() + (30 * 24 * 3600), '/', '', false, true);
                        } else {
                            setcookie('gudeg_remember_email', '', time() - 3600, '/', '', false, true);
                        }

                        try { $sb->update('users', ['id' => $user['id']], ['last_login_at' => date('Y-m-d H:i:s')]); } catch (\Throwable $e) { function_exists('gudeg_log') ? gudeg_log('WARNING', 'login.php::update_last_login', $e->getMessage()) : error_log('update last_login_at gagal: ' . $e->getMessage()); }

                        $current_ip = get_ip();
                        $is_new = is_new_ip($user['id'], $current_ip);

                        log_activity($user['id'], $user['name'], $user['email'], 'Login');

                        if ($is_new) {
                            $_SESSION['new_ip_warning'] = $current_ip;
                            log_admin_action(0, 'SYSTEM', 'Login dari IP Baru: ' . $current_ip, $user['name'] . ' (' . $user['email'] . ')');
                        }

                        try {
                            $sess_id  = session_id();
                            $ua       = $_SERVER['HTTP_USER_AGENT'] ?? '';
                            $ip_addr  = get_ip();
                            $now_str  = date('Y-m-d H:i:s');
                            $existing_session = $sb->selectOne('sessions', ['id' => $sess_id], 'id');
                            $session_data = [
                                'user_id'       => (string)$user['id'],
                                'ip_address'    => $ip_addr,
                                'user_agent'    => $ua,
                                'last_activity' => $now_str,
                            ];
                            if ($existing_session) {
                                $sb->update('sessions', ['id' => $sess_id], $session_data);
                            } else {
                                $session_data['id']         = $sess_id;
                                $session_data['payload']    = '';
                                $session_data['created_at'] = $now_str;
                                $sb->insert('sessions', $session_data);
                            }
                        } catch (\Throwable $e) {
                            function_exists('gudeg_log') ? gudeg_log('WARNING', 'login.php::upsert_session', 'login tetap lanjut: ' . $e->getMessage()) : error_log('upsert session gagal: ' . $e->getMessage());
                        }

                        // Cleanup session expired milik user ini
                        // Session dianggap expired jika last_activity > SESSION_TIMEOUT (30 menit) lalu
                        // Dijalankan setiap login agar tidak menumpuk
                        try {
                            $expired_ts      = time() - SESSION_TIMEOUT;
                            $expired_cutoff  = date('Y-m-d H:i:s', $expired_ts);

                            // Hapus session expired (format datetime) — kecuali session sekarang
                            $sb->delete('sessions', [
                                'user_id'       => (int)$user['id'],
                                'id'            => 'neq.' . $sess_id,
                                'last_activity' => 'lt.' . $expired_cutoff,
                            ]);

                            // Hapus juga session lama dengan format unix timestamp
                            // (data lama sebelum fix format last_activity)
                            // Unix timestamp yang expired: angka < $expired_ts sebagai string
                            $old_sessions = $sb->select('sessions', [
                                'user_id' => (int)$user['id'],
                                'id'      => 'neq.' . $sess_id,
                            ], 'id,last_activity');
                            foreach ($old_sessions as $s) {
                                $la = $s['last_activity'] ?? '';
                                if (is_numeric($la) && (int)$la < $expired_ts) {
                                    $sb->delete('sessions', ['id' => $s['id']]);
                                }
                            }
                        } catch (\Throwable $e) {
                            function_exists('gudeg_log') ? gudeg_log('WARNING', 'login.php::cleanup_session', $e->getMessage()) : error_log('cleanup session expired gagal: ' . $e->getMessage());
                        }

                        $dest = (parse_url($redirect, PHP_URL_HOST) === null && str_starts_with($redirect, '/'))
                            ? $redirect
                            : '/dashboard.php';

                        // Deteksi request dari APK berdasarkan User-Agent
                        $is_apk = is_from_apk();

                        if ($is_apk) {
                            // APK: jangan redirect 302 langsung.
                            // Render halaman bridge tipis yang memanggil
                            // GudegNative.saveCredentials() terlebih dahulu,
                            // lalu JS redirect ke dashboard.
                            // Dengan cara ini password TIDAK pernah mampir di
                            // sessionStorage — langsung ke EncryptedSharedPreferences.
                            $login_success  = true;
                            $login_email    = $email;
                            $login_password = $password;
                            $login_dest     = $dest;
                            // Lanjut render halaman bridge di bawah (tidak exit di sini)
                        } else {
                            // Browser biasa: redirect normal
                            header("Location: $dest");
                            exit;
                        }
                    }
                } else {
                    RateLimiter::fail($ip, $email);
                    $error = 'Email atau password salah.';
                }
            } catch (\Throwable $e) {
                function_exists('gudeg_log') ? gudeg_log('ERROR', 'login.php', 'Supabase error: ' . $e->getMessage()) : error_log('login.php Supabase error: ' . $e->getMessage());
                $error = 'Layanan sedang gangguan, coba beberapa saat lagi.';
            }
        }
    } else {
        $error = 'Email dan password wajib diisi.';
    }
}

// ── Halaman bridge APK ────────────────────────────────────────────────────────
// Hanya dirender saat login sukses dari APK.
// Halaman ini tidak terlihat user (blank putih sesaat) — tugasnya hanya:
// 1. Panggil GudegNative.saveCredentials() → tersimpan ke EncryptedSharedPreferences
// 2. JS redirect ke dashboard
if ($login_success) {
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Masuk...</title>
<style>
  body { background: #080a0e; margin: 0; }
</style>
</head>
<body>
<script>
(function () {
  // Simpan credentials ke EncryptedSharedPreferences via JS bridge APK.
  // Ini dipanggil SEBELUM redirect ke dashboard sehingga:
  // - Password tidak pernah masuk sessionStorage
  // - Tidak ada null form saat cari #email/#password di halaman lain
  try {
    if (window.GudegNative && window.GudegNative.saveCredentials) {
      window.GudegNative.saveCredentials(
        <?= json_encode($login_email) ?>,
        <?= json_encode($login_password) ?>
      );
    }
  } catch (e) {}

  // Redirect ke dashboard (atau tujuan semula)
  window.location.replace(<?= json_encode($login_dest) ?>);
})();
</script>
</body>
</html>
<?php
    exit;
}
// ─────────────────────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>GUDEG - Login</title>
<meta name="description" content="Masuk ke GUDEG — sistem SSO terpadu Balai Monitor Spektrum Frekuensi Radio Yogyakarta.">
<meta name="robots" content="index, follow">
<meta property="og:type" content="website">
<meta property="og:title" content="GUDEG - Login">
<meta property="og:description" content="Masuk ke GUDEG — sistem SSO terpadu Balai Monitor Spektrum Frekuensi Radio Yogyakarta.">
<meta property="og:url" content="https://gudeg.xo.je/login.php">
<meta property="og:site_name" content="GUDEG JOGJA">
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="GUDEG - Login">
<meta name="twitter:description" content="Masuk ke GUDEG — sistem SSO terpadu Balai Monitor Spektrum Frekuensi Radio Yogyakarta.">
<link rel="icon" type="image/x-icon" href="/assets/favicon.ico">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<link rel="apple-touch-icon" href="/assets/favicon.png">
<link rel="stylesheet" href="/assets/tabler/tabler-icons.min.css">
<script>
(function(){
  try {
    var t = localStorage.getItem('gudeg_theme') || 'dark';
    if (t === 'light') { t = 'bright'; localStorage.setItem('gudeg_theme', 'bright'); }
    document.documentElement.setAttribute('data-theme', t);
  } catch(e) {}
})();
</script>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=DM+Sans:opsz,wght@9..40,300;400;500;600&display=swap" rel="stylesheet">
<style>
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
  --danger-glow:  rgba(239,68,68,.18);
  --sans:         'DM Sans', 'Segoe UI', system-ui, -apple-system, sans-serif;
  --mono:         'Space Mono', 'Courier New', monospace;
  --glass:        rgba(8,11,16,.88);
  --glass-hi:     rgba(15,21,32,.95);
  --shadow-float: 0 8px 32px rgba(0,0,0,.7),0 2px 8px rgba(0,0,0,.5),0 0 0 1px rgba(255,255,255,.04);
  --radius:       8px;
  --radius-sm:    5px;
  --radius-lg:    12px;
  --transition-fast:       .12s cubic-bezier(.4,0,.2,1);
  --transition-med:        .22s cubic-bezier(.4,0,.2,1);
  --autofill-bg:  #080b10;
  --autofill-txt: #f1f5f9;
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
  --danger-glow:  rgba(220,38,38,.12);
  --sans:         'DM Sans', 'Segoe UI', system-ui, -apple-system, sans-serif;
  --mono:         'Space Mono', 'Courier New', monospace;
  --glass:        rgba(240,244,248,.92);
  --glass-hi:     rgba(255,255,255,.97);
  --shadow-float: 0 4px 20px rgba(0,0,0,.1),0 1px 4px rgba(0,0,0,.07),0 0 0 1px rgba(0,0,0,.05);
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
  min-height: 100dvh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.25rem 1rem;
  transition: background var(--transition-med), color var(--transition-med);
}
.container { width: 100%; max-width: 400px; }

/* ── Theme FAB ── */
.theme-fab {
  position: fixed;
  top: calc(14px + env(safe-area-inset-top)); right: 14px;
  z-index: 999;
  display: flex; align-items: center; justify-content: center;
  width: 36px; height: 36px;
  background: var(--glass-hi);
  backdrop-filter: blur(16px) saturate(160%);
  border: 1px solid var(--border-hi);
  border-radius: var(--radius);
  cursor: pointer;
  color: var(--muted-hi);
  box-shadow: var(--shadow-float);
  transition: border-color var(--transition-fast), color var(--transition-fast), box-shadow var(--transition-fast);
  -webkit-tap-highlight-color: transparent;
}
.theme-fab:hover {
  border-color: var(--accent);
  color: var(--accent);
  box-shadow: var(--shadow-float), 0 0 12px var(--accent-glow);
}
.theme-fab i { font-size: 17px; }

/* ── Logo ── */
.logo-area { text-align: center; margin-bottom: 24px; }
.logo-shield {
  width: 52px; height: 52px;
  background: var(--accent-bg);
  border: 1px solid var(--accent-bdr);
  border-radius: var(--radius-lg);
  display: inline-flex; align-items: center; justify-content: center;
  margin-bottom: 14px;
  box-shadow: 0 0 24px var(--accent-glow);
  transition: box-shadow var(--transition-med);
}
.logo-shield:hover { box-shadow: 0 0 36px var(--accent-glow); }
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
  margin-top: 8px; line-height: 1.7;
  font-family: var(--mono);
}
.logo-acronym strong {
  display: block; color: var(--muted-hi);
  font-size: 10px; letter-spacing: .05em;
  margin-top: 3px;
}

/* ── Card ── */
.card {
  background: var(--glass-hi);
  backdrop-filter: blur(20px) saturate(160%);
  border: 1px solid var(--border-hi);
  border-radius: var(--radius-lg);
  padding: 24px 22px;
  box-shadow: var(--shadow-float);
  transition: border-color var(--transition-med);
}
.card:focus-within { border-color: var(--border-hi); }

/* ── Form ── */
.form-group { margin-bottom: 16px; }
label {
  display: block;
  font-size: 10px; font-weight: 600;
  color: var(--muted-hi);
  text-transform: uppercase; letter-spacing: .1em;
  margin-bottom: 6px;
  font-family: var(--mono);
}
input[type="email"],
input[type="password"] {
  width: 100%;
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 9px 12px;
  color: var(--text);
  font-family: var(--sans);
  font-size: 14px;
  outline: none;
  transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
  -webkit-appearance: none;
}
input[type="email"]:focus,
input[type="password"]:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px var(--accent-glow);
}
input:-webkit-autofill,
input:-webkit-autofill:hover,
input:-webkit-autofill:focus {
  -webkit-box-shadow: 0 0 0 1000px var(--autofill-bg) inset !important;
  -webkit-text-fill-color: var(--autofill-txt) !important;
  caret-color: var(--autofill-txt);
  border-color: var(--border) !important;
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

/* ── Button ── */
.btn-submit {
  width: 100%;
  background: var(--accent-bg);
  color: var(--accent);
  border: 1px solid var(--accent-bdr);
  border-radius: var(--radius-sm);
  padding: 10px;
  font-family: var(--mono);
  font-size: 12px; font-weight: 700;
  text-transform: uppercase; letter-spacing: .1em;
  cursor: pointer;
  margin-top: 6px;
  transition: background var(--transition-fast), border-color var(--transition-fast), box-shadow var(--transition-fast), transform var(--transition-fast);
  -webkit-tap-highlight-color: transparent;
}
.btn-submit:hover {
  background: rgba(0,229,255,.12);
  border-color: var(--accent);
  box-shadow: 0 0 16px var(--accent-glow);
}
[data-theme="bright"] .btn-submit:hover {
  background: rgba(2,132,199,.1);
  box-shadow: 0 0 12px var(--accent-glow);
}
.btn-submit:active { transform: scale(.98); }

/* ── Divider & links ── */
.divider { border: none; border-top: 1px solid var(--border); margin: 16px 0; }
.link-row {
  text-align: center; font-size: 12px;
  color: var(--muted);
  font-family: var(--mono);
  letter-spacing: .03em;
}
.link-row a { color: var(--accent); transition: color var(--transition-fast); }
.link-row a:hover { color: var(--text); }
.footer-note {
  text-align: center; margin-top: 18px;
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
  box-shadow: 0 8px 32px rgba(0,0,0,.5); position: relative;
  backdrop-filter: blur(16px);
}
@keyframes bubbleIn {
  from { opacity: 0; transform: translateY(10px); }
  to   { opacity: 1; transform: translateY(0); }
}
.bubble.danger  { background: rgba(239,68,68,.1); border-color: rgba(239,68,68,.3); color: #fca5a5; }
.bubble.warning { background: rgba(234,179,8,.1); border-color: rgba(234,179,8,.3); color: #fde68a; }
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
  .card { padding: 18px 16px; }
  input[type="email"],
  input[type="password"] { padding: 11px 12px; font-size: 16px; }
  .remember-row label { font-size: 12px; }
  .btn-submit { padding: 12px; font-size: 13px; }
  .theme-fab { top: calc(10px + env(safe-area-inset-top)); right: 10px; }
  .logo-shield { width: 44px; height: 44px; }
  .logo-shield i { font-size: 24px; }
  .logo-title { font-size: 16px; }
  .logo-acronym { font-size: 9px; }
}
</style>
</head>
<body>

<button class="theme-fab" onclick="toggleTheme()" aria-label="Ganti tema">
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
    <!-- Form tanpa onsubmit sessionStorage — credentials tidak disimpan di browser -->
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email"
               placeholder="user@example.com"
               value="<?= htmlspecialchars($_POST['email'] ?? $remembered_email) ?>"
               required autofocus autocomplete="email">
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password"
               placeholder="••••••••"
               required autocomplete="current-password">
      </div>

      <div class="remember-row">
        <input type="checkbox" id="remember" name="remember"
               <?= $remembered_email ? 'checked' : '' ?>>
        <label for="remember">Ingat Saya</label>
      </div>

      <button type="submit" class="btn-submit">Masuk</button>
    </form>

    <hr class="divider">
    <p class="link-row">Belum punya akun? <a href="/register.php">Daftar di sini</a></p>
  </div>

  <p class="footer-note">© 2026 QOS Balmon Jogja Team</p>
</div>

<div class="bubble-wrap" id="bubbleWrap"></div>

<script>
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
showBubble('danger', 'alert-circle', 'Login Gagal', <?= json_encode(htmlspecialchars($error)) ?>);
<?php elseif ($timeout): ?>
showBubble('warning', 'clock', 'Sesi Berakhir', 'Sesi Anda telah berakhir karena tidak aktif. Silakan login kembali.');
<?php endif; ?>

<?php if (!empty($_GET['clear_biometric'])): ?>
try {
  if (window.GudegNative && window.GudegNative.clearCredentials) {
    window.GudegNative.clearCredentials();
  }
} catch (e) {}
<?php endif; ?>
</script>
</body>
</html>