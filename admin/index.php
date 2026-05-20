<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();

$user = get_current_user_data();
$db = getDB();

// Handle logika update role/access jika ada (sesuaikan dengan script aslimu)
// ...

$users = $db->query("SELECT * FROM users ORDER BY id DESC")->fetchAll();
$apps = $db->query("SELECT * FROM apps ORDER BY name ASC")->fetchAll();

// Map hak akses aplikasi per user
$userAppsRaw = $db->query("SELECT user_id, app_id FROM user_apps")->fetchAll();
$userApps = [];
foreach ($userAppsRaw as $row) {
    $userApps[$row['user_id']][] = $row['app_id'];
}

layout_head('Manajemen Pengguna', 'admin-index');
?>

<div class="page-header">
  <h1>Manajemen Pengguna</h1>
  <p>Kelola data dan hak akses SSO seluruh pegawai</p>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Nama & Email</th>
          <th>Role</th>
          <th>Terdaftar</th>
          <th>Akses SSO</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): 
          $uApps = $userApps[$u['id']] ?? [];
        ?>
        <tr>
          <td>
            <div style="font-weight:600;font-size:1rem;"><?= htmlspecialchars($u['name']) ?></div>
            <div style="font-size:.8rem;color:var(--text-muted);margin-top:2px;"><?= htmlspecialchars($u['email']) ?></div>
          </td>
          <td>
            <?php if ($u['role'] === 'admin'): ?>
              <span class="badge badge-yellow">Admin</span>
            <?php else: ?>
              <span class="badge badge-muted">User</span>
            <?php endif; ?>
          </td>
          <td style="font-size:.85rem;color:var(--text-muted);">
            <?= htmlspecialchars($u['created_at'] ?? '-') ?>
          </td>
          <td>
            <button class="btn btn-ghost btn-sm" onclick="toggleAccess(<?= $u['id'] ?>)">
              <?= count($uApps) ?> Aplikasi
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
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
        
        <!-- Accordion Row: Edit Akses App -->
        <tr id="access-<?= $u['id'] ?>" style="display:none;">
          <td colspan="5" style="background:var(--bg2); padding:16px 20px;">
            <div style="font-size:.8rem;color:var(--text-muted);margin-bottom:10px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;">
              Hak Akses SSO: <?= htmlspecialchars($u['name']) ?>
            </div>
            <form method="POST" action="/admin/update-access.php" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>"/>
              <?php foreach ($apps as $app): ?>
              <label style="display:flex;align-items:center;gap:6px;font-size:.9rem;cursor:pointer;">
                <input type="checkbox" name="app_ids[]" value="<?= $app['id'] ?>"
                       <?= in_array($app['id'], $uApps) ? 'checked' : '' ?>
                       style="accent-color:var(--accent);width:16px;height:16px;cursor:pointer;"/>
                <?= htmlspecialchars($app['name']) ?>
              </label>
              <?php endforeach; ?>
              <div style="width:100%; height:4px;"></div>
              <button type="submit" class="btn btn-primary btn-sm">Simpan Hak Akses</button>
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

<?php layout_foot(); ?>