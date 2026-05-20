<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /dashboard.php'); exit;
}

$pageTitle  = 'Manajemen Pengguna';
$activePage = 'admin-index';
$isAdmin    = true;

include __DIR__ . '/../includes/layout.php';

// [ambil data dari DB — contoh dummy:]
$users = [
    ['id'=>1,'name'=>'Budi Santoso','username'=>'budi','email'=>'budi@example.com','role'=>'admin','created_at'=>'2025-01-05'],
    ['id'=>2,'name'=>'Siti Rahayu','username'=>'siti','email'=>'siti@example.com','role'=>'user','created_at'=>'2025-02-14'],
    ['id'=>3,'name'=>'Doni Prasetyo','username'=>'doni','email'=>'doni@example.com','role'=>'user','created_at'=>'2025-03-01'],
];
$apps = [
    ['id'=>1,'name'=>'SIMPEG'],
    ['id'=>2,'name'=>'E-Office'],
];
// user → apps map (dummy)
$userApps = [1=>[1,2], 2=>[1], 3=>[]];
?>

<div class="page-header">
  <h1>Manajemen Pengguna</h1>
  <p>Kelola akses pengguna dan hak aplikasi SSO</p>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Nama</th>
          <th>Username</th>
          <th>Role</th>
          <th>Terdaftar</th>
          <th>Akses Aplikasi</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $i => $u):
          $uApps = $userApps[$u['id']] ?? [];
        ?>
        <tr>
          <td style="color:var(--text-muted);"><?= $i+1 ?></td>
          <td>
            <div style="font-weight:500;"><?= htmlspecialchars($u['name']) ?></div>
            <div style="font-size:.78rem;color:var(--text-muted);"><?= htmlspecialchars($u['email']) ?></div>
          </td>
          <td style="font-family:monospace;font-size:.85rem;"><?= htmlspecialchars($u['username']) ?></td>
          <td>
            <?php if ($u['role']==='admin'): ?>
              <span class="badge badge-yellow">Admin</span>
            <?php else: ?>
              <span class="badge badge-muted">User</span>
            <?php endif; ?>
          </td>
          <td style="font-size:.82rem;color:var(--text-muted);"><?= htmlspecialchars($u['created_at']) ?></td>
          <td>
            <!-- accordion toggle -->
            <button class="btn btn-ghost btn-sm" onclick="toggleAccess(<?= $u['id'] ?>)">
              <?= count($uApps) ?> app
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
          </td>
          <td>
            <form method="POST" action="/admin/toggle-role.php" style="display:inline;">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>"/>
              <button type="submit" class="btn btn-ghost btn-sm">
                <?= $u['role']==='admin' ? 'Jadikan User' : 'Jadikan Admin' ?>
              </button>
            </form>
          </td>
        </tr>
        <!-- accordion row -->
        <tr id="access-<?= $u['id'] ?>" style="display:none;">
          <td colspan="7" style="background:var(--bg3);padding:12px 16px;">
            <div style="font-size:.78rem;color:var(--text-muted);margin-bottom:8px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;">Akses Aplikasi</div>
            <form method="POST" action="/admin/update-access.php" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>"/>
              <?php foreach ($apps as $app): ?>
              <label style="display:flex;align-items:center;gap:6px;font-size:.85rem;cursor:pointer;">
                <input type="checkbox" name="app_ids[]" value="<?= $app['id'] ?>"
                       <?= in_array($app['id'], $uApps) ? 'checked' : '' ?>
                       style="accent-color:var(--blue);width:14px;height:14px;"/>
                <?= htmlspecialchars($app['name']) ?>
              </label>
              <?php endforeach; ?>
              <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function toggleAccess(id) {
  const row = document.getElementById('access-' + id);
  row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
}
</script>

<?php include __DIR__ . '/../includes/layout_end.php'; ?>