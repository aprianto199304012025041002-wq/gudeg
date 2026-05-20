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
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
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
<title>Daftar — Portal SSO</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Mono:wght@400;500&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --bg: #0a0c10; --surface: #12151c; --border: #1e2330;
  --accent: #c8a96e; --accent-dim: #8a7249;
  --text: #e8e4dc; --text-dim: #7a7a8a;
  --danger: #e57373; --success: #69d98c;
}
html { -webkit-text-size-adjust: 100%; }
body {
  background: var(--bg); color: var(--text);
  font-family: 'DM Sans', sans-serif;
  min-height: 100vh; min-height: 100dvh;
  display: flex; align-items: center; justify-content: center;
  padding: 1.25rem 1rem;
  background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(200,169,110,.07), transparent);
}
.container { width: 100%; max-width: 400px; }

.logo-area { text-align: center; margin-bottom: 1.75rem; }
.logo-icon {
  width: 50px; height: 50px;
  background: linear-gradient(135deg, var(--accent), var(--accent-dim));
  border-radius: 14px;
  display: inline-flex; align-items: center; justify-content: center;
  font-size: 1.3rem; margin-bottom: .85rem;
}
.logo-area h1 { font-family: 'DM Serif Display', serif; font-size: 1.5rem; }
.logo-area .sub {
  color: var(--text-dim); font-size: .72rem;
  font-family: 'DM Mono', monospace;
  letter-spacing: .1em; text-transform: uppercase; margin-top: .35rem;
}

.card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 16px; padding: 1.75rem 1.5rem;
}

.form-group { margin-bottom: 1.1rem; }
label {
  display: block; font-size: .72rem; font-weight: 500;
  color: var(--text-dim); text-transform: uppercase;
  letter-spacing: .08em; margin-bottom: .4rem;
}
input[type="text"],
input[type="email"],
input[type="password"] {
  width: 100%; background: var(--bg);
  border: 1px solid var(--border); border-radius: 8px;
  padding: .78rem 1rem; color: var(--text);
  font-family: 'DM Sans', sans-serif; font-size: 1rem;
  outline: none; transition: border-color .2s;
  -webkit-appearance: none;
}
input:focus { border-color: var(--accent); }

.error-msg {
  background: rgba(229,115,115,.08); border: 1px solid rgba(229,115,115,.25);
  color: var(--danger); border-radius: 8px;
  padding: .65rem 1rem; font-size: .83rem; margin-bottom: 1.1rem;
}
.success-msg {
  background: rgba(105,217,140,.08); border: 1px solid rgba(105,217,140,.25);
  color: var(--success); border-radius: 8px;
  padding: .65rem 1rem; font-size: .83rem; margin-bottom: 1.1rem;
}
.success-msg a { color: var(--success); font-weight: 600; }

.note { font-size: .75rem; color: var(--text-dim); margin-top: .3rem; }

.btn-submit {
  width: 100%; background: var(--accent); color: #0a0c10;
  border: none; border-radius: 8px; padding: .88rem;
  font-family: 'DM Sans', sans-serif; font-size: .95rem; font-weight: 600;
  cursor: pointer; transition: opacity .2s, transform .1s;
  margin-top: .5rem; -webkit-tap-highlight-color: transparent;
}
.btn-submit:hover { opacity: .88; }
.btn-submit:active { transform: scale(.98); }

.divider { border: none; border-top: 1px solid var(--border); margin: 1.35rem 0; }
.link-row { text-align: center; font-size: .83rem; color: var(--text-dim); }
.link-row a { color: var(--accent); font-weight: 500; }
.link-row a:hover { text-decoration: underline; }

.footer-note {
  text-align: center; margin-top: 1.75rem;
  font-size: .68rem; color: var(--text-dim);
  font-family: 'DM Mono', monospace; opacity: .5; letter-spacing: .06em;
}
</style>
</head>
<body>
<div class="container">
  <div class="logo-area">
    <div class="logo-icon">📝</div>
    <h1>Daftar Akun</h1>
    <p class="sub">Portal SSO · Balmon Yogyakarta</p>
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

  <p class="footer-note">GUDEG SSO · Sistem Autentikasi Terpusat</p>
</div>
</body>
</html>