<?php
require_once __DIR__ . '/includes/functions.php';
session_start_safe();

if (!empty($_SESSION['user_id'])) {
    header("Location: /dashboard.php");
    exit;
}

$error   = '';
$success = false;

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
<title>Daftar — GUDEG JOGJA</title>
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
  --success:  #34c97e;
}
html { -webkit-text-size-adjust: 100%; }
body {
  background: var(--bg); color: var(--text);
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
    radial-gradient(ellipse 80% 55% at 85% 85%, rgba(47,128,237,.09) 0%, transparent 60%),
    radial-gradient(ellipse 60% 45% at 15% 15%, rgba(245,197,24,.07) 0%, transparent 55%);
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

.wrap { width: 100%; max-width: 400px; position: relative; z-index: 1; }

.brand { text-align: center; margin-bottom: 24px; }
.brand-name { line-height: 1; margin-bottom: 8px; }
.brand-gudeg { font-family:'Syne',sans-serif; font-size:1.85rem; font-weight:800; color:var(--accent); letter-spacing:.04em; }
.brand-jogja { font-family:'Syne',sans-serif; font-size:1.85rem; font-weight:800; color:#fff; letter-spacing:.04em; }
.brand-tagline { font-size:.7rem; color:var(--muted); letter-spacing:.04em; line-height:1.6; text-transform:uppercase; }

.card {
  background: var(--surface);
  border: 1px solid var(--border-hi);
  border-radius: 14px;
  padding: 26px 22px;
  box-shadow: 0 20px 60px rgba(0,0,0,.5);
}
.card-title { font-family:'Syne',sans-serif; font-size:1rem; font-weight:700; margin-bottom:18px; }

.alert-danger  { background:rgba(224,82,82,.1);  border:1px solid rgba(224,82,82,.25);  color:var(--danger);  border-radius:8px; padding:9px 13px; font-size:.84rem; margin-bottom:16px; }
.alert-success { background:rgba(52,201,126,.1); border:1px solid rgba(52,201,126,.25); color:var(--success); border-radius:8px; padding:9px 13px; font-size:.84rem; margin-bottom:16px; }
.alert-success a { color:var(--success); font-weight:600; }

.form-group { margin-bottom: 13px; }
.form-label {
  display:block; font-size:.72rem; font-weight:600;
  color:var(--muted); text-transform:uppercase; letter-spacing:.07em; margin-bottom:5px;
}
.form-control {
  width:100%; background:var(--surface2);
  border:1px solid var(--border-hi); border-radius:8px;
  padding:10px 13px; color:var(--text);
  font-family:'DM Sans',sans-serif; font-size:1rem;
  outline:none; transition:border-color .15s, box-shadow .15s;
  -webkit-appearance:none;
}
.form-control:focus { border-color:var(--blue); box-shadow:0 0 0 3px var(--blue-dim); }

.note { font-size:.75rem; color:var(--muted); margin-top:.3rem; }

.btn-submit {
  width:100%; padding:11px; border:none; border-radius:8px;
  background:var(--blue); color:#fff;
  font-family:'Syne',sans-serif; font-size:.95rem; font-weight:700;
  letter-spacing:.03em; cursor:pointer; margin-top:8px;
  transition:opacity .15s, transform .1s;
  -webkit-tap-highlight-color:transparent;
}
.btn-submit:hover { opacity:.88; }
.btn-submit:active { transform:scale(.97); }

.divider { border:none; border-top:1px solid var(--border); margin:18px 0; }
.card-foot { text-align:center; font-size:.83rem; color:var(--muted); }
.card-foot a { color:var(--blue); font-weight:500; }
.card-foot a:hover { text-decoration:underline; }
</style>
</head>
<body>
<div class="wrap">
  <div class="brand">
    <div class="brand-name">
      <span class="brand-gudeg">GUDEG</span><span class="brand-jogja"> JOGJA</span>
    </div>
    <div class="brand-tagline">General Unified Database<br>and Employee Gateway</div>
  </div>

  <div class="card">
    <div class="card-title">Buat Akun Baru</div>

    <?php if ($error): ?>
      <div class="alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert-success">Akun berhasil dibuat! <a href="/login.php">Login sekarang →</a></div>
      <hr class="divider">
      <p class="card-foot">Sudah punya akun? <a href="/login.php">Login di sini</a></p>
    <?php else: ?>
    <form method="POST">
      <div class="form-group">
        <label class="form-label" for="name">Nama Lengkap</label>
        <input class="form-control" type="text" id="name" name="name"
               placeholder="Nama lengkap"
               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
               required autofocus autocomplete="name">
      </div>
      <div class="form-group">
        <label class="form-label" for="email">Email</label>
        <input class="form-control" type="email" id="email" name="email"
               placeholder="user@example.com"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               required autocomplete="email">
      </div>
      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <input class="form-control" type="password" id="password" name="password"
               placeholder="Min. 8 karakter" required autocomplete="new-password">
      </div>
      <div class="form-group">
        <label class="form-label" for="confirm">Konfirmasi Password</label>
        <input class="form-control" type="password" id="confirm" name="confirm"
               placeholder="Ulangi password" required autocomplete="new-password">
      </div>
      <p class="note">Akun baru akan berstatus <strong>Guest</strong> sampai diaktivasi admin.</p>
      <button type="submit" class="btn-submit">BUAT AKUN</button>
    </form>

    <hr class="divider">
    <p class="card-foot">Sudah punya akun? <a href="/login.php">Login di sini</a></p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>