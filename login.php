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
<title>Login — GUDEG JOGJA</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --bg:       #080f1e;
  --surface:  #0d1730;
  --surface2: #111f3a;
  --border:   rgba(255,255,255,.08);
  --border-hi:rgba(255,255,255,.13);
  --text:     #e2eaf8;
  --muted:    #7a94b8;
  --accent:   #f5c518;
  --blue:     #2f80ed;
  --blue-dim: rgba(47,128,237,.18);
  --danger:   #e05252;
}
html { -webkit-text-size-adjust: 100%; }
body {
  background: var(--bg);
  color: var(--text);
  font-family: 'DM Sans', sans-serif;
  min-height: 100vh; min-height: 100dvh;
  display: flex; align-items: center; justify-content: center;
  padding: 1.25rem 1rem;
  -webkit-font-smoothing: antialiased;
  position: relative; overflow: hidden;
}
body::before {
  content: '';
  position: fixed; inset: 0;
  background:
    radial-gradient(ellipse 80% 55% at 15% 85%, rgba(47,128,237,.09) 0%, transparent 60%),
    radial-gradient(ellipse 60% 45% at 85% 15%, rgba(245,197,24,.07) 0%, transparent 55%);
  pointer-events: none;
}
body::after {
  content: '';
  position: fixed; inset: 0;
  background-image:
    linear-gradient(rgba(255,255,255,.022) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,.022) 1px, transparent 1px);
  background-size: 52px 52px;
  pointer-events: none;
  mask-image: radial-gradient(ellipse 65% 65% at 50% 50%, black, transparent);
}

.wrap { width: 100%; max-width: 380px; position: relative; z-index: 1; }

/* brand */
.brand { text-align: center; margin-bottom: 28px; }
.brand-lock {
  width: 48px; height: 48px;
  background: linear-gradient(135deg, #1a3a6e, #0d1f40);
  border: 1px solid rgba(47,128,237,.3);
  border-radius: 14px;
  display: inline-flex; align-items: center; justify-content: center;
  margin-bottom: 12px;
  box-shadow: 0 0 24px rgba(47,128,237,.15);
}
.brand-lock svg { width: 22px; height: 22px; color: var(--blue); }
.brand-name { line-height: 1; margin-bottom: 8px; }
.brand-gudeg { font-family:'Syne',sans-serif; font-size:2rem; font-weight:800; color:var(--accent); letter-spacing:.04em; }
.brand-jogja { font-family:'Syne',sans-serif; font-size:2rem; font-weight:800; color:#fff; letter-spacing:.04em; }
.brand-tagline { font-size:.7rem; color:var(--muted); letter-spacing:.04em; line-height:1.6; text-transform:uppercase; }

/* card */
.card {
  background: var(--surface);
  border: 1px solid var(--border-hi);
  border-radius: 14px;
  padding: 26px 22px;
  box-shadow: 0 20px 60px rgba(0,0,0,.5);
}
.card-title { font-family:'Syne',sans-serif; font-size:1rem; font-weight:700; margin-bottom:18px; }

.alert-danger {
  background: rgba(224,82,82,.1); border: 1px solid rgba(224,82,82,.25);
  color: var(--danger); border-radius: 8px;
  padding: 9px 13px; font-size: .84rem; margin-bottom: 16px;
}

.form-group { margin-bottom: 14px; }
.form-label {
  display: block; font-size: .72rem; font-weight: 600;
  color: var(--muted); text-transform: uppercase;
  letter-spacing: .07em; margin-bottom: 5px;
}
.form-control {
  width: 100%; background: var(--surface2);
  border: 1px solid var(--border-hi); border-radius: 8px;
  padding: 10px 13px; color: var(--text);
  font-family: 'DM Sans', sans-serif; font-size: 1rem;
  outline: none; transition: border-color .15s, box-shadow .15s;
  -webkit-appearance: none;
}
.form-control:focus { border-color: var(--blue); box-shadow: 0 0 0 3px var(--blue-dim); }

.btn-submit {
  width: 100%; padding: 11px; border: none; border-radius: 8px;
  background: var(--blue); color: #fff;
  font-family: 'Syne', sans-serif; font-size: .95rem; font-weight: 700;
  letter-spacing: .03em; cursor: pointer; margin-top: 6px;
  transition: opacity .15s, transform .1s;
  -webkit-tap-highlight-color: transparent;
}
.btn-submit:hover { opacity: .88; }
.btn-submit:active { transform: scale(.97); }

.divider { border: none; border-top: 1px solid var(--border); margin: 18px 0; }
.card-foot { text-align: center; font-size: .83rem; color: var(--muted); }
.card-foot a { color: var(--blue); font-weight: 500; }
.card-foot a:hover { text-decoration: underline; }
</style>
</head>
<body>
<div class="wrap">
  <div class="brand">
    <div class="brand-lock">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
      </svg>
    </div>
    <div class="brand-name">
      <span class="brand-gudeg">GUDEG</span><span class="brand-jogja"> JOGJA</span>
    </div>
    <div class="brand-tagline">General Unified Database<br>and Employee Gateway</div>
  </div>

  <div class="card">
    <div class="card-title">Masuk ke Akun Anda</div>

    <?php if ($error): ?>
      <div class="alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

      <div class="form-group">
        <label class="form-label" for="email">Email</label>
        <input class="form-control" type="email" id="email" name="email"
               placeholder="user@example.com"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               required autofocus autocomplete="email">
      </div>
      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <input class="form-control" type="password" id="password" name="password"
               placeholder="••••••••" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn-submit">MASUK</button>
    </form>

    <hr class="divider">
    <p class="card-foot">Belum punya akun? <a href="/register.php">Daftar di sini</a></p>
  </div>
</div>
</body>
</html>