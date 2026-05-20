<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: /login.php'); exit; }

$pageTitle  = 'Ganti Password';
$activePage = 'change-password';
$isAdmin    = ($_SESSION['role'] ?? '') === 'admin';

$error   = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // [fungsi ganti password tidak diubah — koneksikan ke logic asli]
}

include __DIR__ . '/includes/layout.php';
?>

<div class="page-header">
  <h1>Ganti Password</h1>
  <p>Perbarui password akun Anda</p>
</div>

<div class="card" style="max-width:440px;">
  <?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

  <form method="POST">
    <div class="form-group">
      <label class="form-label" for="old_password">Password Lama</label>
      <input class="form-control" type="password" id="old_password" name="old_password"
             placeholder="Password saat ini" required/>
    </div>
    <div class="form-group">
      <label class="form-label" for="new_password">Password Baru</label>
      <input class="form-control" type="password" id="new_password" name="new_password"
             placeholder="Min. 8 karakter" required/>
    </div>
    <div class="form-group">
      <label class="form-label" for="confirm_password">Konfirmasi Password Baru</label>
      <input class="form-control" type="password" id="confirm_password" name="confirm_password"
             placeholder="Ulangi password baru" required/>
    </div>
    <button type="submit" class="btn btn-primary btn-block" style="margin-top:4px;">Simpan Perubahan</button>
  </form>
</div>

<?php include __DIR__ . '/includes/layout_end.php'; ?>