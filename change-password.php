<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
$user  = get_current_user_data();
$db    = getDB();
$error = '';
$ok    = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old  = $_POST['old_password'] ?? '';
    $new  = $_POST['new_password'] ?? '';
    $conf = $_POST['confirm'] ?? '';

    $row = $db->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
    $row->execute([$user['id']]);
    $current = $row->fetchColumn();

    if (!password_verify($old, $current)) {
        $error = 'Password lama tidak cocok.';
    } elseif (strlen($new) < 8) {
        $error = 'Password baru minimal 8 karakter.';
    } elseif ($new !== $conf) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        $hash = password_hash($new, PASSWORD_BCRYPT);
        $db->prepare("UPDATE users SET password = ?, password_changed_at = NOW() WHERE id = ?")->execute([$hash, $user['id']]);
        log_activity($user['id'], $user['name'], $user['email'], 'Ubah Password Sendiri');
        $ok = true;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ubah Password — Portal SSO</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Mono:wght@400&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  :root { --bg:#0a0c10;--surface:#12151c;--border:#1e2330;--accent:#c8a96e;--text:#e8e4dc;--text-dim:#7a7a8a; }
  *{box-sizing:border-box;margin:0;padding:0}
  body{background:var(--bg);color:var(--text);font-family:'DM Sans',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1.5rem}
  .container{width:100%;max-width:400px}
  h1{font-family:'DM Serif Display',serif;font-size:1.5rem;margin-bottom:0.4rem}
  .subtitle{color:var(--text-dim);font-size:0.82rem;margin-bottom:2rem}
  .card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:2rem}
  .form-group{margin-bottom:1.1rem}
  label{display:block;font-size:0.78rem;font-weight:500;color:var(--text-dim);text-transform:uppercase;letter-spacing:.07em;margin-bottom:.5rem}
  input{width:100%;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:.75rem 1rem;color:var(--text);font-family:'DM Sans',sans-serif;font-size:.95rem;outline:none;transition:border-color .2s}
  input:focus{border-color:var(--accent)}
  .error-msg{background:rgba(192,57,43,.12);border:1px solid rgba(192,57,43,.35);color:#e57373;border-radius:8px;padding:.7rem 1rem;font-size:.85rem;margin-bottom:1.2rem}
  .success-msg{background:rgba(39,174,96,.12);border:1px solid rgba(39,174,96,.35);color:#69d98c;border-radius:8px;padding:.7rem 1rem;font-size:.85rem;margin-bottom:1.2rem}
  .btn{width:100%;background:var(--accent);color:#0a0c10;border:none;border-radius:8px;padding:.85rem;font-family:'DM Sans',sans-serif;font-size:.95rem;font-weight:600;cursor:pointer;transition:opacity .2s;margin-top:.4rem}
  .btn:hover{opacity:.88}
  .back{display:inline-block;margin-top:1.2rem;color:var(--text-dim);font-size:.85rem;text-decoration:none}
  .back:hover{color:var(--accent)}
</style>
</head>
<body>
<div class="container">
  <h1>Ubah Password</h1>
  <p class="subtitle">Masuk sebagai <strong><?= htmlspecialchars($user['email']) ?></strong></p>
  <div class="card">
    <?php if ($error): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($ok): ?><div class="success-msg">Password berhasil diubah!</div><?php endif; ?>

    <?php if (!$ok): ?>
    <form method="POST">
      <div class="form-group">
        <label>Password Lama</label>
        <input type="password" name="old_password" required>
      </div>
      <div class="form-group">
        <label>Password Baru</label>
        <input type="password" name="new_password" placeholder="Min. 8 karakter" required>
      </div>
      <div class="form-group">
        <label>Konfirmasi Password Baru</label>
        <input type="password" name="confirm" required>
      </div>
      <button type="submit" class="btn">Simpan Password</button>
    </form>
    <?php endif; ?>
  </div>
  <a href="/dashboard.php" class="back">← Kembali ke Dashboard</a>
</div>
</body>
</html>
