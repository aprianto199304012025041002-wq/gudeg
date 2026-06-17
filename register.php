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
        $db    = getDB();
        $check = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = 'Email sudah terdaftar.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("INSERT INTO users (name, email, password, role, is_active, password_changed_at, created_at, updated_at, last_login_at) VALUES (?,?,?,'guest',1,NOW(),NOW(),NOW(),NOW())");
            $stmt->execute([$name, $email, $hash]);
            $new_id = $db->lastInsertId();
            log_activity($new_id, $name, $email, 'Register Akun Baru (Guest)');
            $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daftar — GUDEG JOGJA</title>
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
  --bg:          #080a0e;
  --surface:     #0c0e13;
  --border:      #161b25;
  --accent:      #7aa2d4;
  --accent-dim:  #4a72a0;
  --accent-bg:   #0e1a2a;
  --accent-bdr:  #1e2e44;
  --text:        #a0b4cc;
  --text-dim:    #2e4060;
  --text-muted:  #1e2836;
  --danger:      #c04040;
  --success:     #3a8a60;
  --success-bg:  rgba(58,138,96,.08);
  --success-bdr: #1a3828;
  --sans:        'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
  --mono:        'Courier New', Courier, monospace;
  --autofill-bg:  #080a0e;
  --autofill-txt: #a0b4cc;
}

/* LIGHT */
[data-theme="light"] {
  --bg:          #f0f4f8;
  --surface:     #ffffff;
  --border:      #d0dae6;
  --accent:      #2a6ab4;
  --accent-dim:  #1e52a0;
  --accent-bg:   #e8f0fb;
  --accent-bdr:  #b0c8e8;
  --text:        #1e2e44;
  --text-dim:    #4a6080;
  --text-muted:  #8099b8;
  --danger:      #c03030;
  --success:     #2a7a50;
  --success-bg:  rgba(42,122,80,.08);
  --success-bdr: #a0d0b8;
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

/* ── Theme toggle FAB ───────────────────────────────────── */
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
input[type="text"],
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
input[type="text"]:focus,
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
.note {
  font-size: 12px; color: var(--text-muted);
  margin-top: 8px; letter-spacing: .03em; line-height: 1.5;
}
.note strong { color: var(--text-dim); }
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
  margin-top: 8px;
  transition: background .15s, border-color .15s;
  -webkit-tap-highlight-color: transparent;
  display: block; text-align: center; text-decoration: none;
}
.btn-submit:hover { filter: brightness(1.15); border-color: var(--accent-dim); }
.btn-submit:active { transform: scale(.98); }
.divider { border: none; border-top: 1px solid var(--border); margin: 14px 0; }
.link-row {
  text-align: center; font-size: 13px;
  color: var(--text-muted);
}
.link-row a { color: var(--accent-dim); }
.link-row a:hover { color: var(--accent); }
.success-msg {
  background: var(--success-bg);
  border: 1px solid var(--success-bdr);
  color: var(--success);
  border-radius: 4px;
  padding: 8px 11px;
  font-size: 13px;
  margin-bottom: 12px;
}
.success-msg a { color: var(--success); }
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
  input[type="text"],
  input[type="email"],
  input[type="password"] { padding: 11px 11px; font-size: 16px; } /* 16px cegah zoom iOS */
  .btn-submit { padding: 12px; font-size: 14px; }
  .theme-fab { top: 10px; right: 10px; }
  .logo-shield { width: 44px; height: 44px; }
  .logo-shield i { font-size: 24px; }
  .logo-title { font-size: 18px; }
  .logo-acronym { font-size: 10px; }
  #pw-hint { font-size: 10px; line-height: 1.7; }
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
.bubble.success { background: rgba(58,138,96,.12); border-color: rgba(58,138,96,.35); color: #60c890; }
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
          <div id="pw-hint" style="margin-top:6px;font-size:11px;line-height:1.8;color:var(--text-muted);font-family:var(--sans)">
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

/* ── Password strength ── */
function checkStrength(v) {
  function set(id, ok, label) {
    var el = document.getElementById(id);
    el.textContent = (ok ? '● ' : '○ ') + label;
    el.style.color = ok ? 'var(--success, #3a8a60)' : '';
  }
  set('pw-len',   v.length >= 8,           'Min. 8 karakter');
  set('pw-upper', /[A-Z]/.test(v),         'Huruf besar (A-Z)');
  set('pw-lower', /[a-z]/.test(v),         'Huruf kecil (a-z)');
  set('pw-num',   /[0-9]/.test(v),         'Angka (0-9)');
  set('pw-sym',   /[^a-zA-Z0-9]/.test(v), 'Karakter spesial (!, @, #, dll)');
}

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
showBubble('danger', 'alert-circle', 'Gagal Mendaftar', <?= json_encode(htmlspecialchars($error)) ?>);
<?php endif; ?>
</script>
</body>
</html>