<?php
require_once __DIR__ . '/includes/functions.php';
session_start_safe();

if (!empty($_SESSION['user_id'])) {
    header("Location: /dashboard.php");
    exit;
}

$error    = '';
$redirect = $_GET['redirect'] ?? '/dashboard.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            if (!$user['is_active']) {
                $error = 'Akun Anda tidak aktif. Hubungi administrator.';
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name']    = $user['name'];
                $_SESSION['email']   = $user['email'];
                $_SESSION['role']    = $user['role'];

                $db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$user['id']]);
                log_activity($user['id'], $user['name'], $user['email'], 'Login');

                $dest = filter_var($redirect, FILTER_VALIDATE_URL) ? $redirect : '/dashboard.php';
                header("Location: $dest");
                exit;
            }
        } else {
            $error = 'Email atau password salah.';
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
<title>Login — Portal SSO</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Mono:wght@400;500&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --bg: #0a0c10; --surface: #12151c; --border: #1e2330;
  --accent: #c8a96e; --accent-dim: #8a7249;
  --text: #e8e4dc; --text-dim: #7a7a8a;
  --danger: #e57373;
}

html { -webkit-text-size-adjust: 100%; }
body {
  background: var(--bg);
  color: var(--text);
  font-family: 'DM Sans', sans-serif;
  min-height: 100vh;
  min-height: 100dvh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.25rem 1rem;
  background-image:
    radial-gradient(ellipse 80% 50% at 50% -20%, rgba(200,169,110,.07), transparent),
    radial-gradient(ellipse 60% 40% at 80% 100%, rgba(200,169,110,.04), transparent);
}

.container {
  width: 100%;
  max-width: 400px;
}

/* Logo */
.logo-area { text-align: center; margin-bottom: 2rem; }
.logo-icon {
  width: 50px; height: 50px;
  background: linear-gradient(135deg, var(--accent), var(--accent-dim));
  border-radius: 14px;
  display: inline-flex; align-items: center; justify-content: center;
  font-size: 1.3rem; margin-bottom: .9rem;
}
.logo-area h1 {
  font-family: 'DM Serif Display', serif;
  font-size: 1.55rem; color: var(--text);
}
.logo-area .sub {
  color: var(--text-dim); font-size: .72rem;
  font-family: 'DM Mono', monospace;
  letter-spacing: .1em; text-transform: uppercase;
  margin-top: .35rem;
}

/* Card */
.card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 1.75rem 1.5rem;
}

/* Form */
.form-group { margin-bottom: 1.1rem; }
label {
  display: block;
  font-size: .72rem; font-weight: 500;
  color: var(--text-dim);
  text-transform: uppercase; letter-spacing: .08em;
  margin-bottom: .4rem;
}
input[type="email"],
input[type="password"] {
  width: 100%;
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: .78rem 1rem;
  color: var(--text);
  font-family: 'DM Sans', sans-serif;
  font-size: 1rem;
  outline: none;
  transition: border-color .2s;
  -webkit-appearance: none;
}
input:focus { border-color: var(--accent); }

.error-msg {
  background: rgba(229,115,115,.08);
  border: 1px solid rgba(229,115,115,.25);
  color: var(--danger);
  border-radius: 8px;
  padding: .65rem 1rem;
  font-size: .83rem;
  margin-bottom: 1.1rem;
}

.btn-submit {
  width: 100%;
  background: var(--accent);
  color: #0a0c10;
  border: none;
  border-radius: 8px;
  padding: .88rem;
  font-family: 'DM Sans', sans-serif;
  font-size: .95rem; font-weight: 600;
  cursor: pointer;
  transition: opacity .2s, transform .1s;
  margin-top: .35rem;
  -webkit-tap-highlight-color: transparent;
}
.btn-submit:hover { opacity: .88; }
.btn-submit:active { transform: scale(.98); }

.divider {
  border: none; border-top: 1px solid var(--border);
  margin: 1.35rem 0;
}
.link-row {
  text-align: center; font-size: .83rem; color: var(--text-dim);
}
.link-row a { color: var(--accent); font-weight: 500; }
.link-row a:hover { text-decoration: underline; }

.footer-note {
  text-align: center; margin-top: 1.75rem;
  font-size: .68rem; color: var(--text-dim);
  font-family: 'DM Mono', monospace;
  opacity: .5; letter-spacing: .06em;
}
</style>
</head>
<body>
<div class="container">
  <div class="logo-area">
    <div class="logo-icon">🔐</div>
    <h1>Portal SSO</h1>
    <p class="sub">Single Sign-On · Balmon Yogyakarta</p>
  </div>

  <div class="card">
    <?php if ($error): ?>
      <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email"
               placeholder="user@example.com"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               required autofocus autocomplete="email">
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password"
               placeholder="••••••••" required autocomplete="current-password">
      </div>

      <button type="submit" class="btn-submit">Masuk</button>
    </form>

    <hr class="divider">
    <p class="link-row">Belum punya akun? <a href="/register.php">Daftar di sini</a></p>
  </div>

  <p class="footer-note">GUDEG SSO · Sistem Autentikasi Terpusat</p>
</div>
</body>
</html>