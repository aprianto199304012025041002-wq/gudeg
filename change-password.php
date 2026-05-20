<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';
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

layout_head('Ubah Password', 'password');
?>

<div class="page-header">
  <h1>Ubah Password</h1>
  <p>Akun: <?= htmlspecialchars($user['email']) ?></p>
</div>

<?php if ($ok): ?>
  <div class="alert alert-success" style="max-width:460px;">
    <strong>✓ Berhasil!</strong> Password Anda telah diperbarui.
  </div>
<?php endif; ?>

<?php if ($error): ?>
  <div class="alert alert-danger" style="max-width:460px;">
    <?= htmlspecialchars($error) ?>
  </div>
<?php endif; ?>

<?php if (!$ok): ?>
<div class="card" style="max-width: 460px;">
  <form method="POST">
    <div class="form-group">
      <label class="form-label" for="old_password">Password Lama</label>
      <input class="form-control" type="password" id="old_password" name="old_password" required autocomplete="current-password">
    </div>
    <div class="form-group">
      <label class="form-label" for="new_password">Password Baru</label>
      <input class="form-control" type="password" id="new_password" name="new_password" placeholder="Minimal 8 karakter" required autocomplete="new-password">
    </div>
    <div class="form-group">
      <label class="form-label" for="confirm">Konfirmasi Password Baru</label>
      <input class="form-control" type="password" id="confirm" name="confirm" placeholder="Ulangi password baru" required autocomplete="new-password">
    </div>
    <button type="submit" class="btn btn-primary btn-block" style="margin-top:10px;">Simpan Password</button>
  </form>
</div>
<?php endif; ?>

<?php layout_foot(); ?>