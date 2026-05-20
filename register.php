<?php
require_once __DIR__ . '/includes/functions.php';
session_start_safe();

if (!empty($_SESSION['user_id'])) {
    header("Location: /dashboard.php");
    exit;
}

$error = '';
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
        $db = getDB();
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
            $success = 'Akun berhasil dibuat! Silakan login.';
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
  :root {
    --bg: #0a0c10; --surface: #12151c; --border: #1e2330;
    --accent: #c8a96e; --accent-dim: #8a7249;
    --text: #e8e4dc; --text-dim: #7a7a8a;
    --error: #c0392b; --success: #27ae60;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    background: var(--bg); color: var(--text);
    font-family: 'DM Sans', sans-serif; min-height: 100vh;
    display: flex; align-items: center; justify-content: center; padding: 1.5rem;
    background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(200,169,110,0.07), transparent);
  }
  .container { width: 100%; max-width: 420px; }
  .logo-area { text-align: center; margin-bottom: 2rem; }
  .logo-icon { width: 52px; height: 52px; background: linear-gradient(135deg, var(--accent), var(--accent-dim)); border-radius: 14px; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1rem; font-size: 1.4rem; }
  h1 { font-family: 'DM Serif Display', serif; font-size: 1.5rem; }
  .subtitle { color: var(--text-dim); font-size: 0.82rem; margin-top: 0.4rem; font-family: 'DM Mono', monospace; letter-spacing: 0.08em; text-transform: uppercase; }
  .card { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 2rem; }
  .form-group { margin-bottom: 1.1rem; }
  label { display: block; font-size: 0.78rem; font-weight: 500; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.07em; margin-bottom: 0.5rem; }
  input { width: 100%; background: var(--bg); border: 1px solid var(--border); border-radius: 8px; padding: 0.75rem 1rem; color: var(--text); font-family: 'DM Sans', sans-serif; font-size: 0.95rem; outline: none; transition: border-color 0.2s; }
  input:focus { border-color: var(--accent); }
  .error-msg { background: rgba(192,57,43,0.12); border: 1px solid rgba(192,57,43,0.35); color: #e57373; border-radius: 8px; padding: 0.7rem 1rem; font-size: 0.85rem; margin-bottom: 1.2rem; }
  .success-msg { background: rgba(39,174,96,0.12); border: 1px solid rgba(39,174,96,0.35); color: #69d98c; border-radius: 8px; padding: 0.7rem 1rem; font-size: 0.85rem; margin-bottom: 1.2rem; }
  .btn { width: 100%; background: var(--accent); color: #0a0c10; border: none; border-radius: 8px; padding: 0.85rem; font-family: 'DM Sans', sans-serif; font-size: 0.95rem; font-weight: 600; cursor: pointer; transition: opacity 0.2s; margin-top: 0.4rem; }
  .btn:hover { opacity: 0.88; }
  hr { border: none; border-top: 1px solid var(--border); margin: 1.5rem 0; }
  .login-link { text-align: center; font-size: 0.85rem; color: var(--text-dim); }
  .login-link a { color: var(--accent); text-decoration: none; font-weight: 500; }
  .footer-note { text-align: center; margin-top: 2rem; font-size: 0.75rem; color: var(--text-dim); font-family: 'DM Mono', monospace; opacity: 0.6; }
  .note { font-size: 0.78rem; color: var(--text-dim); margin-top: 0.35rem; }
</style>
</head>
<body>
<div class="container">
  <div class="logo-area">
    <div class="logo-icon">📝</div>
    <h1>Daftar Akun</h1>
    <p class="subtitle">Portal SSO · Balmon Yogyakarta</p>
  </div>

  <div class="card">
    <?php if ($error): ?>
      <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="success-msg"><?= htmlspecialchars($success) ?> <a href="/login.php" style="color:inherit;font-weight:600;">Login sekarang →</a></div>
    <?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST">
      <div class="form-group">
        <label for="name">Nama Lengkap</label>
        <input type="text" id="name" name="name" placeholder="Nama lengkap" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required autofocus>
      </div>
      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" placeholder="user@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" placeholder="Min. 8 karakter" required>
      </div>
      <div class="form-group">
        <label for="confirm">Konfirmasi Password</label>
        <input type="password" id="confirm" name="confirm" placeholder="Ulangi password" required>
      </div>
      <p class="note">Akun baru akan berstatus <strong>Guest</strong> sampai diaktivasi admin.</p>
      <button type="submit" class="btn">Buat Akun</button>
    </form>
    <?php endif; ?>

    <hr>
    <p class="login-link">Sudah punya akun? <a href="/login.php">Login di sini</a></p>
  </div>
  <p class="footer-note">GUDEG SSO · Sistem Autentikasi Terpusat</p>
</div>
</body>
</html>
