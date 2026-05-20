<?php
require_once __DIR__ . '/includes/functions.php';
session_start_safe();

// Already logged in
if (!empty($_SESSION['user_id'])) {
    header("Location: /dashboard.php");
    exit;
}

$error = '';
$redirect = $_GET['redirect'] ?? '/dashboard.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            if (!$user['is_active']) {
                $error = 'Akun Anda tidak aktif. Hubungi administrator.';
            } else {
                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name']    = $user['name'];
                $_SESSION['email']   = $user['email'];
                $_SESSION['role']    = $user['role'];

                // Update last login
                $db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$user['id']]);

                // Log
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
  :root {
    --bg: #0a0c10;
    --surface: #12151c;
    --border: #1e2330;
    --accent: #c8a96e;
    --accent-dim: #8a7249;
    --text: #e8e4dc;
    --text-dim: #7a7a8a;
    --error: #c0392b;
    --success: #27ae60;
  }

  * { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    background: var(--bg);
    color: var(--text);
    font-family: 'DM Sans', sans-serif;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
    background-image:
      radial-gradient(ellipse 80% 50% at 50% -20%, rgba(200,169,110,0.07), transparent),
      radial-gradient(ellipse 60% 40% at 80% 100%, rgba(200,169,110,0.04), transparent);
  }

  .container {
    width: 100%;
    max-width: 420px;
  }

  .logo-area {
    text-align: center;
    margin-bottom: 2.5rem;
  }

  .logo-icon {
    width: 52px;
    height: 52px;
    background: linear-gradient(135deg, var(--accent), var(--accent-dim));
    border-radius: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1rem;
    font-size: 1.4rem;
  }

  h1 {
    font-family: 'DM Serif Display', serif;
    font-size: 1.6rem;
    color: var(--text);
    line-height: 1.2;
  }

  .subtitle {
    color: var(--text-dim);
    font-size: 0.82rem;
    margin-top: 0.4rem;
    font-family: 'DM Mono', monospace;
    letter-spacing: 0.08em;
    text-transform: uppercase;
  }

  .card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 2rem;
  }

  .form-group {
    margin-bottom: 1.2rem;
  }

  label {
    display: block;
    font-size: 0.78rem;
    font-weight: 500;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.07em;
    margin-bottom: 0.5rem;
  }

  input {
    width: 100%;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 0.75rem 1rem;
    color: var(--text);
    font-family: 'DM Sans', sans-serif;
    font-size: 0.95rem;
    outline: none;
    transition: border-color 0.2s;
  }

  input:focus {
    border-color: var(--accent);
  }

  .error-msg {
    background: rgba(192,57,43,0.12);
    border: 1px solid rgba(192,57,43,0.35);
    color: #e57373;
    border-radius: 8px;
    padding: 0.7rem 1rem;
    font-size: 0.85rem;
    margin-bottom: 1.2rem;
  }

  .btn {
    width: 100%;
    background: var(--accent);
    color: #0a0c10;
    border: none;
    border-radius: 8px;
    padding: 0.85rem;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: opacity 0.2s, transform 0.1s;
    margin-top: 0.4rem;
  }

  .btn:hover { opacity: 0.88; }
  .btn:active { transform: scale(0.98); }

  .divider {
    border: none;
    border-top: 1px solid var(--border);
    margin: 1.5rem 0;
  }

  .register-link {
    text-align: center;
    font-size: 0.85rem;
    color: var(--text-dim);
  }

  .register-link a {
    color: var(--accent);
    text-decoration: none;
    font-weight: 500;
  }

  .register-link a:hover { text-decoration: underline; }

  .footer-note {
    text-align: center;
    margin-top: 2rem;
    font-size: 0.75rem;
    color: var(--text-dim);
    font-family: 'DM Mono', monospace;
    opacity: 0.6;
  }
</style>
</head>
<body>
<div class="container">
  <div class="logo-area">
    <div class="logo-icon">🔐</div>
    <h1>Portal SSO</h1>
    <p class="subtitle">Single Sign-On · Balmon Yogyakarta</p>
  </div>

  <div class="card">
    <?php if ($error): ?>
      <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" placeholder="user@example.com"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" placeholder="••••••••" required>
      </div>

      <button type="submit" class="btn">Masuk</button>
    </form>

    <hr class="divider">
    <p class="register-link">
      Belum punya akun? <a href="/register.php">Daftar di sini</a>
    </p>
  </div>

  <p class="footer-note">GUDEG SSO · Sistem Autentikasi Terpusat</p>
</div>
</body>
</html>
