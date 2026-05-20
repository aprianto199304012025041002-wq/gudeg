<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$user = get_current_user_data();
$db   = getDB();

// Handle actions
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $target_id = (int)($_POST['target_id'] ?? 0);

    $row = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $row->execute([$target_id]);
    $target = $row->fetch(PDO::FETCH_ASSOC);

    if ($target && $target_id !== (int)$user['id']) {
        if ($action === 'toggle_active') {
            $new_status = $target['is_active'] ? 0 : 1;
            $db->prepare("UPDATE users SET is_active = ? WHERE id = ?")->execute([$new_status, $target_id]);
            $label = $new_status ? 'Status Akun Diaktifkan' : 'Status Akun Dinonaktifkan';
            log_admin_action($user['id'], $user['name'], $label, $target['name']);
            $msg = "Akun {$target['name']} berhasil " . ($new_status ? 'diaktifkan' : 'dinonaktifkan') . '.';
        } elseif ($action === 'change_role') {
            $new_role = $_POST['new_role'] ?? '';
            if (in_array($new_role, ['admin', 'user', 'guest'])) {
                $db->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$new_role, $target_id]);
                log_admin_action($user['id'], $user['name'], "Ubah Role menjadi " . strtoupper($new_role), $target['name']);
                $msg = "Role {$target['name']} diubah ke $new_role.";
            }
        } elseif ($action === 'reset_password') {
            $new_pass = bin2hex(random_bytes(6)); // random 12-char hex
            $hash = password_hash($new_pass, PASSWORD_BCRYPT);
            $db->prepare("UPDATE users SET password = ?, password_changed_at = NOW() WHERE id = ?")->execute([$hash, $target_id]);
            log_admin_action($user['id'], $user['name'], 'Reset Password', $target['email']);
            $msg = "Password {$target['name']} direset ke: <strong>$new_pass</strong>";
        } elseif ($action === 'update_apps') {
            $app_ids = array_map('intval', $_POST['app_ids'] ?? []);
            $db->prepare("DELETE FROM user_applications WHERE user_id = ?")->execute([$target_id]);
            foreach ($app_ids as $aid) {
                $db->prepare("INSERT INTO user_applications (user_id, application_id) VALUES (?,?)")->execute([$target_id, $aid]);
            }
            log_admin_action($user['id'], $user['name'], 'Update Akses Aplikasi SSO', $target['name']);
            $msg = "Akses aplikasi {$target['name']} diperbarui.";
        }
    }
}

// Fetch users
$users = $db->query("SELECT * FROM users WHERE deleted_at IS NULL ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$all_apps = $db->query("SELECT * FROM sso_applications ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

// Get user-app mapping
$ua_map = [];
$ua_rows = $db->query("SELECT * FROM user_applications")->fetchAll(PDO::FETCH_ASSOC);
foreach ($ua_rows as $r) {
    $ua_map[$r['user_id']][] = $r['application_id'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin — Kelola User</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Mono:wght@400;500&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  :root{--bg:#0a0c10;--surface:#12151c;--surface2:#181c26;--border:#1e2330;--accent:#c8a96e;--text:#e8e4dc;--text-dim:#7a7a8a}
  *{box-sizing:border-box;margin:0;padding:0}
  body{background:var(--bg);color:var(--text);font-family:'DM Sans',sans-serif;min-height:100vh}
  nav{background:var(--surface);border-bottom:1px solid var(--border);padding:0 2rem;display:flex;align-items:center;justify-content:space-between;height:56px;position:sticky;top:0;z-index:10}
  .nav-brand{font-family:'DM Serif Display',serif;font-size:1.1rem;color:var(--accent)}
  .nav-links{display:flex;gap:1.5rem;font-size:.88rem}
  .nav-links a{color:var(--text-dim);text-decoration:none;padding:.3rem 0;border-bottom:2px solid transparent;transition:color .2s,border-color .2s}
  .nav-links a.active,.nav-links a:hover{color:var(--text);border-bottom-color:var(--accent)}
  .nav-right a{color:var(--text-dim);text-decoration:none;font-size:.85rem}
  main{max-width:1100px;margin:0 auto;padding:2rem 1.5rem}
  h1{font-family:'DM Serif Display',serif;font-size:1.8rem;margin-bottom:.4rem}
  .subtitle{color:var(--text-dim);font-size:.88rem;margin-bottom:2rem}
  .msg{background:rgba(200,169,110,.1);border:1px solid rgba(200,169,110,.25);color:var(--accent);border-radius:8px;padding:.75rem 1rem;font-size:.88rem;margin-bottom:1.5rem}
  table{width:100%;border-collapse:collapse;background:var(--surface);border-radius:12px;overflow:hidden;border:1px solid var(--border)}
  th{text-align:left;font-size:.72rem;font-family:'DM Mono',monospace;text-transform:uppercase;letter-spacing:.08em;color:var(--text-dim);padding:.75rem 1rem;border-bottom:1px solid var(--border);background:var(--surface2)}
  td{padding:.75rem 1rem;border-bottom:1px solid var(--border);font-size:.88rem;vertical-align:top}
  tr:last-child td{border-bottom:none}
  tr:hover td{background:rgba(255,255,255,.015)}
  .badge{display:inline-block;padding:.2rem .6rem;border-radius:20px;font-size:.7rem;font-family:'DM Mono',monospace;text-transform:uppercase}
  .badge-admin{background:rgba(200,169,110,.15);color:var(--accent)}
  .badge-user{background:rgba(100,160,220,.15);color:#7ab5f0}
  .badge-guest{background:rgba(120,120,120,.15);color:var(--text-dim)}
  .badge-active{background:rgba(39,174,96,.12);color:#69d98c}
  .badge-inactive{background:rgba(192,57,43,.12);color:#e57373}
  .actions{display:flex;gap:.5rem;flex-wrap:wrap}
  button,select{background:var(--surface2);border:1px solid var(--border);color:var(--text);border-radius:6px;padding:.3rem .65rem;font-size:.78rem;cursor:pointer;font-family:'DM Sans',sans-serif;transition:border-color .2s}
  button:hover,select:hover{border-color:var(--accent)}
  .btn-danger{border-color:rgba(192,57,43,.4);color:#e57373}
  .btn-danger:hover{border-color:#e57373}
  .apps-check{display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.3rem}
  .apps-check label{display:flex;align-items:center;gap:.3rem;font-size:.78rem;color:var(--text-dim);cursor:pointer}
  .apps-check input[type=checkbox]{accent-color:var(--accent)}
  details summary{cursor:pointer;color:var(--accent);font-size:.78rem;margin-top:.3rem;list-style:none}
  details summary::-webkit-details-marker{display:none}
  details[open] summary{margin-bottom:.5rem}
</style>
</head>
<body>
<nav>
  <div class="nav-brand">GUDEG Admin</div>
  <div class="nav-links">
    <a href="/admin/" class="active">Users</a>
    <a href="/admin/apps.php">Aplikasi SSO</a>
    <a href="/admin/logs.php">Logs</a>
  </div>
  <div class="nav-right">
    <a href="/dashboard.php">← Portal</a>
    &nbsp;&nbsp;
    <a href="/logout.php">Keluar</a>
  </div>
</nav>

<main>
  <h1>Kelola User</h1>
  <p class="subtitle">Total <?= count($users) ?> pengguna terdaftar</p>

  <?php if ($msg): ?>
    <div class="msg"><?= $msg ?></div>
  <?php endif; ?>

  <table>
    <thead>
      <tr>
        <th>ID</th><th>Nama / Email</th><th>Role</th><th>Status</th><th>Last Login</th><th>Aksi</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($users as $u): ?>
    <tr>
      <td><span style="font-family:'DM Mono',monospace;color:var(--text-dim)"><?= $u['id'] ?></span></td>
      <td>
        <strong><?= htmlspecialchars($u['name']) ?></strong><br>
        <span style="color:var(--text-dim);font-size:.78rem"><?= htmlspecialchars($u['email']) ?></span>
      </td>
      <td><span class="badge badge-<?= $u['role'] ?>"><?= $u['role'] ?></span></td>
      <td>
        <?php if ($u['is_active']): ?>
          <span class="badge badge-active">Aktif</span>
        <?php else: ?>
          <span class="badge badge-inactive">Nonaktif</span>
        <?php endif; ?>
      </td>
      <td style="font-size:.78rem;color:var(--text-dim)">
        <?= $u['last_login_at'] ? date('d M y H:i', strtotime($u['last_login_at'])) : '—' ?>
      </td>
      <td>
        <?php if ($u['id'] !== (int)$user['id']): ?>
        <div class="actions">
          <!-- Toggle active -->
          <form method="POST" style="display:inline">
            <input type="hidden" name="action" value="toggle_active">
            <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
            <button type="submit" class="<?= $u['is_active'] ? 'btn-danger' : '' ?>">
              <?= $u['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
            </button>
          </form>

          <!-- Change role -->
          <form method="POST" style="display:inline;display:flex;gap:.3rem">
            <input type="hidden" name="action" value="change_role">
            <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
            <select name="new_role">
              <option value="admin" <?= $u['role']==='admin'?'selected':'' ?>>Admin</option>
              <option value="user"  <?= $u['role']==='user' ?'selected':'' ?>>User</option>
              <option value="guest" <?= $u['role']==='guest'?'selected':'' ?>>Guest</option>
            </select>
            <button type="submit">Set Role</button>
          </form>

          <!-- Reset password -->
          <form method="POST" style="display:inline" onsubmit="return confirm('Reset password <?= htmlspecialchars(addslashes($u['name'])) ?>?')">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
            <button type="submit" class="btn-danger">Reset PW</button>
          </form>
        </div>

        <!-- App access -->
        <details style="margin-top:.5rem">
          <summary>Akses Aplikasi (<?= count($ua_map[$u['id']] ?? []) ?>)</summary>
          <form method="POST">
            <input type="hidden" name="action" value="update_apps">
            <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
            <div class="apps-check">
              <?php foreach ($all_apps as $app): ?>
              <label>
                <input type="checkbox" name="app_ids[]" value="<?= $app['id'] ?>"
                  <?= in_array($app['id'], $ua_map[$u['id']] ?? []) ? 'checked' : '' ?>>
                <?= htmlspecialchars($app['name']) ?>
              </label>
              <?php endforeach; ?>
            </div>
            <button type="submit" style="margin-top:.5rem">Simpan Akses</button>
          </form>
        </details>
        <?php else: ?>
          <span style="color:var(--text-dim);font-size:.78rem">(Anda sendiri)</span>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</main>
</body>
</html>
