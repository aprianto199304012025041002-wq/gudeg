<?php
require_once __DIR__ . '/includes/functions.php';
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email    = sanitize_input($_POST['email'] ?? '', 150);
    $password = substr($_POST['password'] ?? '', 0, 200);
    $remember = !empty($_POST['remember']);

    if ($email && $password) {
        if (login_throttled($email)) {
            $error = 'Terlalu banyak percobaan login. Coba lagi dalam 10 menit.';
        } else {
            $db   = getDB();
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                if (!$user['is_active']) {
                    $error = 'Akun Anda tidak aktif. Hubungi administrator.';
                    login_fail($email);
                } else {
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['name']    = $user['name'];
                    $_SESSION['email']   = $user['email'];
                    $_SESSION['role']    = $user['role'];

                    login_reset($email);

                    if ($remember) {
                        setcookie('gudeg_remember_email', $email, time() + (30 * 24 * 3600), '/', '', false, true);
                    } else {
                        setcookie('gudeg_remember_email', '', time() - 3600, '/', '', false, true);
                    }

                    $db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$user['id']]);

                    $current_ip = get_ip();
                    $is_new = is_new_ip($user['id'], $current_ip);

                    log_activity($user['id'], $user['name'], $user['email'], 'Login');

                    if ($is_new) {
                        $_SESSION['new_ip_warning'] = $current_ip;
                        log_admin_action(0, 'SYSTEM', 'Login dari IP Baru: ' . $current_ip, $user['name'] . ' (' . $user['email'] . ')');
                    }

                    $sess_id = session_id();
                    $ua      = $_SERVER['HTTP_USER_AGENT'] ?? '';
                    $ip_addr = get_ip();
                    $now_str = date('Y-m-d H:i:s');
                    $db->prepare("INSERT INTO sessions (id, user_id, ip_address, user_agent, payload, last_activity, created_at)
                                  VALUES (?,?,?,?,?,?,?)
                                  ON DUPLICATE KEY UPDATE ip_address=VALUES(ip_address), user_agent=VALUES(user_agent), last_activity=VALUES(last_activity)")
                       ->execute([$sess_id, $user['id'], $ip_addr, $ua, '', $now_str, $now_str]);

                    $dest = (parse_url($redirect, PHP_URL_HOST) === null && str_starts_with($redirect, '/'))
                        ? $redirect
                        : '/dashboard.php';
                    header("Location: $dest");
                    exit;
                }
            } else {
                login_fail($email);
                $error = 'Email atau password salah.';
            }
        }
    } else {
        $error = 'Email dan password wajib diisi.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — GUDEG JOGJA</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
<!-- Apply theme before paint (no flash) -->
<script>
(function(){
  try {
    var t = localStorage.getItem('gudeg_theme') || 'dark';
    document.documentElement.setAttribute('data-theme', t);
  } catch(e) {}
})();
</script>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

/* DARK (default) */
:root,
[data-theme="dark"] {
  --bg:         #080a0e;
  --surface:    #0c0e13;
  --border:     #161b25;
  --accent:     #7aa2d4;
  --accent-dim: #4a72a0;
  --accent-bg:  #0e1a2a;
  --accent-bdr: #1e2e44;
  --text:       #a0b4cc;
  --text-dim:   #2e4060;
  --text-muted: #1e2836;
  --danger:     #c04040;
  --sans:       'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
  --mono:       'Courier New', Courier, monospace;
  --autofill-bg:  #080a0e;
  --autofill-txt: #a0b4cc;
}

/* LIGHT */
[data-theme="light"] {
  --bg:         #f0f4f8;
  --surface:    #ffffff;
  --border:     #d0dae6;
  --accent:     #2a6ab4;
  --accent-dim: #1e52a0;
  --accent-bg:  #e8f0fb;
  --accent-bdr: #b0c8e8;
  --text:       #1e2e44;
  --text-dim:   #4a6080;
  --text-muted: #8099b8;
  --danger:     #c03030;
  --autofill-bg:  #ffffff;
  --autofill-txt: #1e2e44;
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
  transition: background .2s, color .2s;
}
.container { width: 100%; max-width: 400px; }

/* ── Theme toggle (pojok kanan atas) ───────────────────── */
.theme-fab {
  position: fixed;
  top: 14px; right: 14px;
  z-index: 999;
  display: flex; align-items: center; justify-content: center;
  width: 36px; height: 36px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 8px;
  cursor: pointer;
  color: var(--text-dim);
  transition: border-color .15s, color .15s, background .2s;
  -webkit-tap-highlight-color: transparent;
}
.theme-fab:hover { border-color: var(--accent-dim); color: var(--accent); }
.theme-fab i { font-size: 17px; }

/* ── Logo ───────────────────────────────────────────────── */
.logo-area { text-align: center; margin-bottom: 20px; }
.logo-shield {
  width: 52px; height: 52px;
  background: var(--accent-bg);
  border: 1px solid var(--accent-bdr);
  border-radius: 12px;
  display: inline-flex; align-items: center; justify-content: center;
  margin-bottom: 12px;
}
.logo-shield i { font-size: 28px; color: var(--accent-dim); }
.logo-title { font-size: 20px; font-weight: 800; letter-spacing: .03em; line-height: 1.1; }
.logo-title .gudeg { color: var(--text); }
.logo-title .jogja { color: #d4a800; }
[data-theme="dark"] .logo-title .gudeg { color: #ffffff; }
.logo-acronym {
  font-size: 11px; color: var(--text-muted);
  text-transform: uppercase; letter-spacing: .06em;
  margin-top: 6px; line-height: 1.6;
}
.logo-acronym strong {
  display: block; color: var(--text-dim);
  font-size: 11px; letter-spacing: .04em;
  margin-top: 2px;
}

/* ── Card & form ────────────────────────────────────────── */
.card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 6px;
  padding: 20px 18px;
  transition: background .2s, border-color .2s;
}
.form-group { margin-bottom: 14px; }
label {
  display: block;
  font-size: 11px; font-weight: 600;
  color: var(--text-dim);
  text-transform: uppercase; letter-spacing: .07em;
  margin-bottom: 5px;
}
input[type="email"],
input[type="password"] {
  width: 100%;
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 4px;
  padding: 9px 11px;
  color: var(--text);
  font-family: var(--sans);
  font-size: 14px;
  outline: none;
  transition: border-color .15s, background .2s, color .2s;
  -webkit-appearance: none;
}
input[type="email"]:focus,
input[type="password"]:focus { border-color: var(--accent-dim); }
input:-webkit-autofill,
input:-webkit-autofill:hover,
input:-webkit-autofill:focus {
  -webkit-box-shadow: 0 0 0 1000px var(--autofill-bg) inset !important;
  -webkit-text-fill-color: var(--autofill-txt) !important;
  caret-color: var(--autofill-txt);
  border-color: var(--border) !important;
}
.remember-row {
  display: flex; align-items: center; gap: 8px;
  margin: 12px 0 6px;
}
.remember-row input[type="checkbox"] {
  width: 15px; height: 15px;
  accent-color: var(--accent-dim);
  cursor: pointer; flex-shrink: 0;
}
.remember-row label {
  font-size: 12px; color: var(--text-dim); cursor: pointer;
  text-transform: uppercase; letter-spacing: .06em;
  margin-bottom: 0;
}
.btn-submit {
  width: 100%;
  background: var(--accent-bg);
  color: var(--accent);
  border: 1px solid var(--accent-bdr);
  border-radius: 4px;
  padding: 10px;
  font-family: var(--sans);
  font-size: 13px; font-weight: 600;
  text-transform: uppercase; letter-spacing: .07em;
  cursor: pointer;
  margin-top: 4px;
  transition: background .15s, border-color .15s;
  -webkit-tap-highlight-color: transparent;
}
.btn-submit:hover { background: var(--accent-bg); filter: brightness(1.15); border-color: var(--accent-dim); }
.btn-submit:active { transform: scale(.98); }
.divider { border: none; border-top: 1px solid var(--border); margin: 14px 0; }
.link-row {
  text-align: center; font-size: 13px;
  color: var(--text-muted);
}
.link-row a { color: var(--accent-dim); }
.link-row a:hover { color: var(--accent); }
.footer-note {
  text-align: center; margin-top: 16px;
  font-size: 11px; color: var(--text-muted);
  letter-spacing: .05em;
  text-transform: uppercase;
}

/* ── Floating bubble ────────────────────────────────────── */
.bubble-wrap {
  position: fixed;
  bottom: 16px; right: 16px;
  display: flex; flex-direction: column-reverse; gap: 8px;
  z-index: 9999; width: 320px; pointer-events: none;
}
@media (max-width: 480px) {
  .bubble-wrap { bottom: 12px; right: 8px; left: 8px; width: auto; }
}

/* ── Mobile optimizations ─────────────────────────────── */
@media (max-width: 480px) {
  body { padding: 1rem .75rem; align-items: flex-start; padding-top: 60px; }
  .container { max-width: 100%; }
  .card { padding: 16px 14px; }
  input[type="email"],
  input[type="password"] { padding: 11px 11px; font-size: 16px; } /* 16px cegah zoom iOS */
  .remember-row label { font-size: 13px; }
  .btn-submit { padding: 12px; font-size: 14px; }
  .theme-fab { top: 10px; right: 10px; }
  .logo-shield { width: 44px; height: 44px; }
  .logo-shield i { font-size: 24px; }
  .logo-title { font-size: 18px; }
  .logo-acronym { font-size: 10px; }
}
.bubble {
  border-radius: 6px; padding: 10px 32px 10px 14px;
  font-size: .8rem; line-height: 1.5; border: 1px solid;
  display: flex; align-items: flex-start; gap: 8px;
  pointer-events: all; animation: bubbleIn .25s ease;
  box-shadow: 0 4px 20px rgba(0,0,0,.3); position: relative;
}
@keyframes bubbleIn {
  from { opacity: 0; transform: translateY(10px); }
  to   { opacity: 1; transform: translateY(0); }
}
.bubble.danger  { background: rgba(192,64,64,.12); border-color: rgba(192,64,64,.35); color: #e08080; }
.bubble.warning { background: rgba(192,140,64,.12); border-color: rgba(192,140,64,.35); color: #e0b060; }
.bubble i { font-size: 16px; flex-shrink: 0; margin-top: 1px; }
.bubble-body { flex: 1; min-width: 0; }
.bubble-title { font-weight: 700; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 2px; font-size: .75rem; }
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
  height: 2px; background: currentColor; opacity: .3;
  border-radius: 0 0 6px 6px;
  animation: bubbleProgress 6s linear forwards;
}
@keyframes bubbleProgress { from { width: 100%; } to { width: 0%; } }
</style>
</head>
<body>

<!-- Theme toggle FAB pojok kanan atas -->
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
/* ── Theme ── */
(function() {
  var THEME_KEY = 'gudeg_theme';
  function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    try { localStorage.setItem(THEME_KEY, theme); } catch(e) {}
    document.querySelectorAll('.theme-toggle-icon').forEach(function(ic) {
      ic.className = 'ti ' + (theme === 'light' ? 'ti-moon' : 'ti-sun') + ' theme-toggle-icon';
    });
  }
  function getTheme() {
    try { return localStorage.getItem(THEME_KEY) || 'dark'; } catch(e) { return 'dark'; }
  }
  window.toggleTheme = function() { applyTheme(getTheme() === 'dark' ? 'light' : 'dark'); };
  applyTheme(getTheme());
})();

/* ── Bubble ── */
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
</script>
</body>
</html>