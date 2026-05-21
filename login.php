<?php
require_once __DIR__ . '/includes/functions.php';
session_start_safe();

if (!empty($_SESSION['user_id'])) {
    header("Location: /dashboard.php");
    exit;
}

$error    = '';
$redirect = $_GET['redirect'] ?? '/dashboard.php';

$remembered_email = $_COOKIE['gudeg_remember_email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember']);

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

                if ($remember) {
                    setcookie('gudeg_remember_email', $email, time() + (30 * 24 * 3600), '/', '', true, true);
                } else {
                    setcookie('gudeg_remember_email', '', time() - 3600, '/', '', true, true);
                }

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
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; font-weight: 700; }
:root {
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
  --mono:       'Courier New', Courier, monospace;
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
.remember-row {
  display: flex; align-items: center; gap: 7px;
  margin: 10px 0 4px;
}
.remember-row input[type="checkbox"] {
  width: 13px; height: 13px;
  accent-color: var(--accent-dim);
  cursor: pointer; flex-shrink: 0;
}
.remember-row label {
  font-size: 9px; color: var(--text-dim); cursor: pointer;
  text-transform: uppercase; letter-spacing: .07em;
  margin-bottom: 0; font-weight: 700;
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
  margin-top: 4px;
  transition: background .15s, border-color .15s;
  -webkit-tap-highlight-color: transparent;
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

    <form method="POST">
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
</body>
</html>