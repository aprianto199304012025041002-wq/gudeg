<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';
require_login();

$user  = get_current_user_data();
$db    = getDB();
$error = '';
$ok    = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // FIX: Validasi CSRF token
    csrf_verify();

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

layout_head('Pengaturan', 'password');
?>
<style>
.pw-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 4px;
  padding: 1.1rem 1rem;
  max-width: 460px;
}

.form-group { margin-bottom: 1rem; }

.form-group label {
  display: block;
  font-size: .72rem;
  font-weight: 600;
  color: var(--text-dim);
  text-transform: uppercase;
  letter-spacing: .07em;
  margin-bottom: .35rem;
  font-family: var(--sans);
}

.form-group input[type="password"] {
  width: 100%;
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 2px;
  padding: .65rem .85rem;
  color: var(--text);
  font-family: var(--sans);
  font-size: .9rem;
  outline: none;
  transition: border-color .2s;
  -webkit-appearance: none;
  -webkit-box-shadow: 0 0 0 1000px var(--bg) inset;
  -webkit-text-fill-color: var(--text);
}
.form-group input[type="password"]:focus { border-color: var(--accent); }

.btn-save {
  width: 100%;
  background: rgba(122,162,212,.1);
  border: 1px solid var(--accent);
  color: var(--accent);
  border-radius: 2px;
  padding: .75rem;
  font-family: var(--sans);
  font-size: .82rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .06em;
  cursor: pointer;
  margin-top: .5rem;
  transition: background .2s;
  -webkit-tap-highlight-color: transparent;
}
.btn-save:hover { background: rgba(122,162,212,.18); }
.btn-save:active { opacity: .85; }

.success-banner {
  background: rgba(74,222,128,.06);
  border: 1px solid rgba(74,222,128,.2);
  color: var(--success);
  border-radius: 4px;
  padding: .75rem .9rem;
  font-size: .82rem;
  font-weight: 600;
  font-family: var(--sans);
  text-transform: uppercase;
  letter-spacing: .04em;
  display: flex;
  align-items: center;
  gap: .5rem;
  max-width: 460px;
  margin-bottom: 1rem;
}
</style>

<div class="page-header">
  <h1 class="page-title">Pengaturan</h1>
  <p class="page-subtitle">Masuk sebagai <?= htmlspecialchars($user['email']) ?></p>
</div>

<?php if ($ok): ?>
  <div class="success-banner">
    <span>✓</span> Password berhasil diubah!
  </div>
<?php endif; ?>

<?php if ($error): ?>
  <div class="msg-box error" style="max-width:440px"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (!$ok): ?>
<div class="pw-card">
  <form method="POST">
    <?= csrf_field() ?>
    <div class="form-group">
      <label for="old_password">Password Lama</label>
      <input type="password" id="old_password" name="old_password"
             required autocomplete="current-password">
    </div>
    <div class="form-group">
      <label for="new_password">Password Baru</label>
      <input type="password" id="new_password" name="new_password"
             placeholder="Min. 8 karakter" required autocomplete="new-password">
    </div>
    <div class="form-group">
      <label for="confirm">Konfirmasi Password Baru</label>
      <input type="password" id="confirm" name="confirm"
             required autocomplete="new-password">
    </div>
    <button type="submit" class="btn-save">Simpan Password</button>
  </form>
</div>
<?php endif; ?>

<?php layout_foot(); ?>
