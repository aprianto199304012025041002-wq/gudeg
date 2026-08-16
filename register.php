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