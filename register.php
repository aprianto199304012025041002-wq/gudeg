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
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if (!$name || !$email || !$password || !$confirm) {
        $error = 'Semua field wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif (strlen($password) < 8) {
        $error = 'Password minimal 8 karakter.';
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
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; font-weight: 700; }
:root {
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
  --mono:        'Courier New', Courier, monospace;
}
html { -webkit-text-size-adjust: 100%; }
body {
  background: var(--bg);
  color: var(--text);
  font-family: var(--mono);
  font-size: 11px;
  font-weight: 700;
  min-height: 100vh;
  min-height: 100dvh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.25rem 1rem;
}
.container { width: 100%; max-width: 380px; }
.logo-area { text-align: center; margin-bottom: 18px; }
.logo-shield {
  width: 44px; height: 44px;
  background: var(--accent-bg);
  border: 1px solid var(--accent-bdr);
  border-radius: 10px;
  display: inline-flex; align-items: center; justify-content: center;
  margin-bottom: 10px;
}
.logo-shield i { font-size: 24px; color: var(--accent-dim); }
.logo-title { font-size: 16px; font-weight: 900; letter-spacing: .03em; line-height: 1.1; }
.logo-title .gudeg { color: #ffffff; }
.logo-title .jogja { color: #f0c040; }
.logo-acronym {
  font-size: 8px; color: var(--text-muted);
  text-transform: uppercase; letter-spacing: .07em;
  margin-top: 5px; line-height: 1.6; font-weight: 700;
}
.logo-acronym strong {
  display: block; color: #3a5272;
  font-size: 8px; letter-spacing: .05em;
  margin-top: 2px; font-weight: 700;
}
.card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 6px;
  padding: 16px 14px;
}
.form-group { margin-bottom: 10px; }
label {
  display: block;
  font-size: 8px; font-weight: 700;
  color: var(--text-dim);
  text-transform: uppercase; letter-spacing: .08em;
  margin-bottom: 4px;
}
input[type="text"],
input[type="email"],
input[type="password"] {
  width: 100%;
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 4px;
  padding: 7px 9px;
  color: var(--text);
  font-family: var(--mono);
  font-size: 11px;
  font-weight: 700;
  outline: none;
  transition: border-color .15s;
  -webkit-appearance: none;
}
input[type="text"]:focus,
input[type="email"]:focus,
input[type="password"]:focus { border-color: var(--accent-dim); }
input:-webkit-autofill,
input:-webkit-autofill:hover,
input:-webkit-autofill:focus {
  -webkit-box-shadow: 0 0 0 1000px #080a0e inset !important;
  -webkit-text-fill-color: #a0b4cc !important;
  caret-color: #a0b4cc;
  border-color: var(--border) !important;
}
.error-msg {
  background: rgba(192,64,64,.08);
  border: 1px solid #3a1818;
  color: #a04040;
  border-radius: 4px;
  padding: 6px 9px;
  font-size: 9px; font-weight: 700;
  margin-bottom: 10px;
}
.success-msg {
  background: var(--success-bg);
  border: 1px solid var(--success-bdr);
  color: var(--success);
  border-radius: 4px;
  padding: 6px 9px;
  font-size: 9px; font-weight: 700;
  margin-bottom: 10px;
}
.success-msg a { color: var(--success); font-weight: 700; }
.note {
  font-size: 8px; color: var(--text-muted);
  margin-top: 8px; letter-spacing: .04em;
  font-weight: 700; line-height: 1.5;
}
.note strong { color: #3a5272; font-weight: 700; }
.btn-submit {
  width: 100%;
  background: var(--accent-bg);
  color: var(--accent);
  border: 1px solid var(--accent-bdr);
  border-radius: 4px;
  padding: 8px;
  font-family: var(--mono);
  font-size: 10px; font-weight: 700;
  text-transform: uppercase; letter-spacing: .08em;
  cursor: pointer;
  margin-top: 8px;
  transition: background .15s, border-color .15s;
  -webkit-tap-highlight-color: transparent;
  display: block; text-align: center; text-decoration: none;
}
.btn-submit:hover { background: #122030; border-color: var(--accent-dim); }
.btn-submit:active { transform: scale(.98); }
.divider { border: none; border-top: 1px solid var(--border); margin: 12px 0; }
.link-row {
  text-align: center; font-size: 9px;
  color: var(--text-muted); font-weight: 700;
}
.link-row a { color: var(--accent-dim); font-weight: 700; }
.link-row a:hover { color: var(--accent); }
.footer-note {
  text-align: center; margin-top: 14px;
  font-size: 8px; color: var(--text-muted);
  font-weight: 700; letter-spacing: .05em;
  text-transform: uppercase;
}
</style>
</head>
<body>
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
    <?php if ($error): ?>
      <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="success-msg">
        Akun berhasil dibuat! <a href="/login.php">Login sekarang →</a>
      </div>
      <hr class="divider">
      <p class="link-row">Sudah punya akun? <a href="/login.php">Login di sini</a></p>

    <?php else: ?>
      <form method="POST">
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
                 required autocomplete="new-password">
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
</body>
</html>