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
$redirect = $_POST['redirect'] ?? $_GET['redirect'] ?? '/dashboard.php';
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
                            setcookie('gudeg_remember_email', $email, time() + (30 * 24 * 3600), '/', '', true, true);
                        } else {
                            setcookie('gudeg_remember_email', '', time() - 3600, '/', '', true, true);
                        }

                        try { $sb->update('users', ['id' => $user['id']], ['last_login_at' => date('Y-m-d H:i:s')]); } catch (\Throwable $e) { function_exists('gudeg_log') ? gudeg_log('WARNING', 'login.php::update_last_login', $e->getMessage()) : error_log('update last_login_at gagal: ' . $e->getMessage()); }

                        $current_ip = get_ip();
                        $is_new = is_new_ip($user['id'], $current_ip);

                        log_activity($user['id'], $user['name'], $user['email'], ACTIVITY_LOGIN);

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

                            // Hapus session expired (format datetime) -- kecuali session sekarang
                            $sb->delete('sessions', [
                                'user_id'       => (int)$user['id'],
                                'id'            => 'neq.' . $sess_id,
                                'last_activity' => 'lt.' . $expired_cutoff,
                            ]);

                            // Hapus juga session lama dengan format unix timestamp
                            // (data lama sebelum fix format last_activity)
                            $old_sessions = $sb->select('sessions', [
                                'user_id' => (int)$user['id'],
                                'id'      => 'neq.' . $sess_id,
                            ], 'id,last_activity');
                            $expired_ids = [];
                            foreach ($old_sessions as $s) {
                                $la = $s['last_activity'] ?? '';
                                if (is_numeric($la) && (int)$la < $expired_ts) {
                                    $expired_ids[] = $s['id'];
                                }
                            }
                            if (!empty($expired_ids)) {
                                $sb->delete('sessions', ['id' => 'in.(' . implode(',', $expired_ids) . ')']);
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
                            // sessionStorage -- langsung ke EncryptedSharedPreferences.
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

// -- Halaman bridge APK -------------------------------------------------------
// Hanya dirender saat login sukses dari APK.
// Halaman ini tidak terlihat user (blank putih sesaat) -- tugasnya hanya:
// 1. Panggil GudegNative.saveCredentials() -> tersimpan ke EncryptedSharedPreferences
// 2. JS redirect ke dashboard
if ($login_success) {
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Masuk...</title>
<style nonce="<?= csp_nonce() ?>">
  body { background: #07090f; margin: 0; }
</style>
</head>
<body>
<script nonce="<?= csp_nonce() ?>">
(function () {
  try {
    if (window.GudegNative && window.GudegNative.saveCredentials) {
      window.GudegNative.saveCredentials(
        <?= json_encode($login_email) ?>,
        <?= json_encode($login_password) ?>
      );
    }
  } catch (e) {}
  window.location.replace(<?= json_encode($login_dest) ?>);
})();
</script>
</body>
</html>
<?php
    exit;
}
// -----------------------------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>GUDEG - Login</title>
<meta name="description" content="Masuk ke GUDEG -- sistem SSO terpadu Balai Monitor Spektrum Frekuensi Radio Yogyakarta.">
<meta name="robots" content="index, follow">
<meta property="og:type" content="website">
<meta property="og:title" content="GUDEG - Login">
<meta property="og:description" content="Masuk ke GUDEG -- sistem SSO terpadu Balai Monitor Spektrum Frekuensi Radio Yogyakarta.">
<meta property="og:url" content="https://gudeg.xo.je/login.php">
<meta property="og:site_name" content="GUDEG JOGJA">
<meta property="og:image" content="https://gudeg.xo.je/assets/og-image.png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="GUDEG - Login">
<meta name="twitter:description" content="Masuk ke GUDEG -- sistem SSO terpadu Balai Monitor Spektrum Frekuensi Radio Yogyakarta.">
<meta name="twitter:image" content="https://gudeg.xo.je/assets/og-image.png">
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
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

/* -- Tokens -- */
:root,
[data-theme="dark"] {
  --bg:             #07090f;
  --bg-mesh:        radial-gradient(ellipse at 15% 15%, rgba(19,81,180,.18) 0%, transparent 55%),
                    radial-gradient(ellipse at 85% 88%, rgba(13,61,140,.12) 0%, transparent 55%),
                    radial-gradient(ellipse at 60% 2%,  rgba(10,45,110,.10) 0%, transparent 45%),
                    #07090f;

  /* Glass */
  --glass:          rgba(8,12,24,.74);
  --glass-hi:       rgba(10,16,30,.88);
  --glass-border:   rgba(255,255,255,.06);
  --glass-border-hi:rgba(255,255,255,.11);

  /* Elevation */
  --elev-1: 0 1px 3px rgba(0,0,0,.45), 0 1px 2px rgba(0,0,0,.5);
  --elev-2: 0 4px 12px rgba(0,0,0,.5),  0 1px 4px rgba(0,0,0,.4);
  --elev-3: 0 8px 32px rgba(0,0,0,.55), 0 2px 8px rgba(0,0,0,.4);
  --elev-4: 0 20px 60px rgba(0,0,0,.65), 0 4px 20px rgba(0,0,0,.45);
  --elev-inset: inset 0 1px 0 rgba(255,255,255,.05), inset 0 -1px 0 rgba(0,0,0,.15);

  /* Accent -- biru BSSN/Komdigi */
  --accent:         #4A90D9;
  --accent-deep:    #1351B4;
  --accent-glow:    rgba(74,144,217,.22);
  --accent-glow-sm: rgba(74,144,217,.10);
  --accent-dim:     rgba(74,144,217,.75);
  --accent-bg:      rgba(19,81,180,.08);
  --accent-bdr:     rgba(74,144,217,.25);

  /* Text */
  --text:           #e8edf5;
  --muted-hi:       #8da8cc;
  --muted:          #3d5470;

  /* Status */
  --danger:         #e05252;
  --danger-glow:    rgba(224,82,82,.18);
  --warning-bg:     rgba(234,179,8,.10);
  --warning-bdr:    rgba(234,179,8,.28);

  /* Type -- formal, readable */
  --sans:           'Segoe UI', 'Noto Sans', system-ui, -apple-system, Arial, sans-serif;
  --mono:           'Consolas', 'Courier New', monospace;

  /* Misc */
  --radius:         8px;
  --radius-sm:      6px;
  --radius-lg:      12px;
  --transition-fast:.13s cubic-bezier(.4,0,.2,1);
  --transition-med: .22s cubic-bezier(.4,0,.2,1);
  --autofill-bg:    #07090f;
  --autofill-txt:   #e8edf5;
}

[data-theme="bright"] {
  --bg:             #f2f5fb;
  --bg-mesh:        radial-gradient(ellipse at 15% 15%, rgba(19,81,180,.10) 0%, transparent 55%),
                    radial-gradient(ellipse at 85% 88%, rgba(19,81,180,.06) 0%, transparent 55%),
                    #f2f5fb;
  --glass:          rgba(230,238,252,.82);
  --glass-hi:       rgba(255,255,255,.95);
  --glass-border:   rgba(19,81,180,.10);
  --glass-border-hi:rgba(19,81,180,.16);
  --elev-1: 0 1px 3px rgba(0,0,0,.07),  0 1px 2px rgba(0,0,0,.05);
  --elev-2: 0 4px 12px rgba(0,0,0,.09), 0 1px 4px rgba(0,0,0,.06);
  --elev-3: 0 8px 32px rgba(0,0,0,.11), 0 2px 8px rgba(0,0,0,.07);
  --elev-4: 0 20px 60px rgba(0,0,0,.14), 0 4px 20px rgba(0,0,0,.09);
  --elev-inset: inset 0 1px 0 rgba(255,255,255,.8), inset 0 -1px 0 rgba(0,0,0,.04);
  --accent:         #1351B4;
  --accent-deep:    #0D3D8C;
  --accent-glow:    rgba(19,81,180,.22);
  --accent-glow-sm: rgba(19,81,180,.12);
  --accent-dim:     rgba(19,81,180,.80);
  --accent-bg:      rgba(19,81,180,.07);
  --accent-bdr:     rgba(19,81,180,.28);
  --text:           #0d1829;
  --muted-hi:       #1e3a6a;
  --muted:          #5570a0;
  --danger:         #c0392b;
  --danger-glow:    rgba(192,57,43,.15);
  --warning-bg:     rgba(245,158,11,.10);
  --warning-bdr:    rgba(245,158,11,.28);
  --autofill-bg:    #f2f5fb;
  --autofill-txt:   #0d1829;
}

html { -webkit-text-size-adjust: 100%; }

/* -- Scrollbar -- */
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

/* Ambient glow orbs -- biru saja, tanpa cyan */
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
  background: radial-gradient(circle, rgba(19,81,180,.10) 0%, transparent 70%);
}
body::after {
  width: 400px; height: 400px;
  bottom: -100px; right: -50px;
  background: radial-gradient(circle, rgba(13,61,140,.08) 0%, transparent 70%);
}
[data-theme="bright"] body::before { background: radial-gradient(circle, rgba(19,81,180,.07) 0%, transparent 70%); }
[data-theme="bright"] body::after  { background: radial-gradient(circle, rgba(13,61,140,.05) 0%, transparent 70%); }

.container { width: 100%; max-width: 400px; position: relative; z-index: 1; }

/* -- Theme FAB -- */
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

/* -- Logo -- */
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
.logo-shield::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 1px;
  background: linear-gradient(90deg, transparent, rgba(74,144,217,.30), transparent);
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
  background: linear-gradient(135deg, var(--accent), #90bef0);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
.logo-title .jogja { color: var(--muted-hi); -webkit-text-fill-color: var(--muted-hi); }
.logo-acronym {
  font-size: 10px; font-weight: 600; color: var(--muted);
  text-transform: uppercase; letter-spacing: .08em;
  margin-top: 10px; line-height: 1.8;
  font-family: var(--sans);
}
.logo-acronym strong {
  display: block; color: var(--muted-hi);
  font-size: 10px; letter-spacing: .05em;
  margin-top: 3px;
}

/* -- Card (glass) -- */
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
.card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 1px;
  background: linear-gradient(90deg, transparent, var(--glass-border-hi), transparent);
}

/* -- Section title -- */
.card-title {
  font-size: 13px; font-weight: 700;
  color: var(--text);
  letter-spacing: .02em;
  margin-bottom: 18px;
  font-family: var(--sans);
}

/* -- Form -- */
.form-group { margin-bottom: 16px; }
label {
  display: block;
  font-size: 11px; font-weight: 600;
  color: var(--muted-hi);
  letter-spacing: .04em;
  margin-bottom: 7px;
  font-family: var(--sans);
}
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
  font-weight: 500;
  outline: none;
  transition: border-color var(--transition-fast), box-shadow var(--transition-fast), background var(--transition-fast);
  -webkit-appearance: none;
  box-shadow: var(--elev-inset);
}
[data-theme="bright"] input[type="email"],
[data-theme="bright"] input[type="password"] {
  background: rgba(255,255,255,.7);
}
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

/* -- Remember row -- */
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
  font-size: 12px; color: var(--muted-hi); cursor: pointer;
  letter-spacing: .02em;
  margin-bottom: 0;
  font-family: var(--sans);
}

/* -- Submit button -- */
.btn-submit {
  width: 100%;
  background: var(--accent-deep);
  color: #ffffff;
  border: 1px solid rgba(74,144,217,.35);
  border-radius: var(--radius-sm);
  padding: 11px;
  font-family: var(--sans);
  font-size: 14px; font-weight: 600;
  letter-spacing: .03em;
  cursor: pointer;
  margin-top: 8px;
  box-shadow: 0 0 20px var(--accent-glow-sm), var(--elev-2);
  transition: background var(--transition-fast), border-color var(--transition-fast),
              box-shadow var(--transition-fast), transform var(--transition-fast);
  -webkit-tap-highlight-color: transparent;
}
.btn-submit:hover {
  background: var(--accent);
  border-color: var(--accent);
  box-shadow: 0 0 28px var(--accent-glow), var(--elev-3);
}
.btn-submit:active { transform: scale(.98); }
[data-theme="bright"] .btn-submit {
  background: var(--accent-deep);
  color: #ffffff;
}
[data-theme="bright"] .btn-submit:hover {
  background: #1a5fcc;
}

/* -- Divider & links -- */
.divider { border: none; border-top: 1px solid var(--glass-border-hi); margin: 18px 0; }
.link-row {
  text-align: center; font-size: 13px;
  color: var(--muted);
  font-family: var(--sans);
}
.link-row a { color: var(--accent); text-decoration: none; transition: color var(--transition-fast); }
.link-row a:hover { color: var(--text); text-decoration: underline; }
.footer-note {
  text-align: center; margin-top: 20px;
  font-size: 11px; color: var(--muted);
  font-family: var(--sans);
}

/* -- Bubbles -- */
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
  font-family: var(--sans);
}
@keyframes bubbleIn {
  from { opacity: 0; transform: translateY(10px); }
  to   { opacity: 1; transform: translateY(0); }
}
.bubble.danger  { background: rgba(224,82,82,.12); border-color: rgba(224,82,82,.3); color: #fca5a5; }
.bubble.warning { background: var(--warning-bg); border-color: var(--warning-bdr); color: #fde68a; }
[data-theme="bright"] .bubble.warning { color: #92400e; }
[data-theme="bright"] .bubble.danger  { color: #991b1b; }
.bubble i { font-size: 16px; flex-shrink: 0; margin-top: 1px; }
.bubble-body { flex: 1; min-width: 0; }
.bubble-title { font-weight: 700; letter-spacing: .04em; margin-bottom: 2px; font-size: .72rem; }
.bubble-desc  { font-size: .78rem; font-weight: 500; opacity: .9; }
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

/* -- Responsive -- */
@media (max-width: 480px) {
  .bubble-wrap { bottom: calc(12px + env(safe-area-inset-bottom)); right: 8px; left: 8px; width: auto; }
  body { padding: 1rem .75rem; align-items: flex-start; padding-top: calc(60px + env(safe-area-inset-top)); }
  .container { max-width: 100%; }
  .card { padding: 20px 16px; }
  input[type="email"],
  input[type="password"] { padding: 11px 12px; font-size: 16px; }
  .btn-submit { padding: 13px; font-size: 14px; }
  .theme-fab { top: calc(10px + env(safe-area-inset-top)); right: 10px; }
  .logo-shield { width: 48px; height: 48px; }
  .logo-shield i { font-size: 24px; }
  .logo-title { font-size: 16px; }
  .logo-acronym { font-size: 9px; }
}

/* -- Reduced motion -- */
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after { transition: none !important; animation: none !important; }
}
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
    <p class="card-title">Masuk ke Akun Anda</p>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

      <div class="form-group">
        <label for="email">Alamat Email</label>
        <input type="email" id="email" name="email"
               placeholder="user@example.com"
               value="<?= htmlspecialchars($_POST['email'] ?? $remembered_email) ?>"
               required autofocus autocomplete="email">
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password"
               placeholder="Masukkan password"
               required autocomplete="current-password">
      </div>

      <div class="remember-row">
        <input type="checkbox" id="remember" name="remember"
               <?= $remembered_email ? 'checked' : '' ?>>
        <label for="remember">Ingat saya di perangkat ini</label>
      </div>

      <button type="submit" class="btn-submit">Masuk</button>
    </form>

    <hr class="divider">
    <p class="link-row">Belum punya akun? <a href="/register.php">Daftar di sini</a></p>
  </div>

  <p class="footer-note">&copy; 2026 QOS Balmon Jogja Team</p>
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

function showBubble(type, icon, title, desc, duration) {
  duration = duration || 6000;
  var wrap = document.getElementById('bubbleWrap');
  var b = document.createElement('div');
  b.className = 'bubble ' + type;
  b.innerHTML =
    '<i class="ti ti-' + icon + '" aria-hidden="true"></i>' +
    '<div class="bubble-body"><div class="bubble-title">' + title + '</div>' +
    '<div class="bubble-desc">' + desc + '</div></div>' +
    '<button class="bubble-close" aria-label="Tutup" data-action="close-bubble">&#215;</button>' +
    '<div class="bubble-progress" style="animation-duration:' + (duration/1000) + 's"></div>';
  wrap.appendChild(b);
  b.querySelector('[data-action="close-bubble"]').addEventListener('click', function() { closeBubble(b); });
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

(function() {
  var fab = document.getElementById('theme-fab-btn');
  if (fab) fab.addEventListener('click', function() { if (window.toggleTheme) toggleTheme(); });
})();
</script>
</body>
</html>
