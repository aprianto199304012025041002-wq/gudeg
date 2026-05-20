<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$user = get_current_user_data();
$db   = getDB();
$msg  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_app') {
        $name        = trim($_POST['name'] ?? '');
        $redirect    = trim($_POST['redirect'] ?? '');
        $desc        = trim($_POST['description'] ?? '');
        $signed      = isset($_POST['use_signed_token']) ? 1 : 0;
        if ($name && $redirect) {
            $cid = bin2hex(random_bytes(8));
            $sec = bin2hex(random_bytes(32));
            $db->prepare("INSERT INTO sso_applications (name, redirect, client_id, client_secret, description, use_signed_token, is_active, created_at, updated_at) VALUES (?,?,?,?,?,?,1,NOW(),NOW())")
               ->execute([$name, $redirect, $cid, $sec, $desc, $signed]);
            log_admin_action($user['id'], $user['name'],
                'Registrasi Aplikasi Baru' . ($signed ? ' (Signed Token)' : ''), $name);
            $msg = "Aplikasi <strong>$name</strong> berhasil didaftarkan. Client ID: <code>$cid</code>";
        }
    } elseif ($action === 'toggle_status') {
        $id  = (int)$_POST['app_id'];
        $row = $db->prepare("SELECT * FROM sso_applications WHERE id = ? LIMIT 1");
        $row->execute([$id]);
        $app = $row->fetch(PDO::FETCH_ASSOC);
        if ($app) {
            $new = $app['is_active'] ? 0 : 1;
            $db->prepare("UPDATE sso_applications SET is_active = ? WHERE id = ?")->execute([$new, $id]);
            log_admin_action($user['id'], $user['name'],
                'Update Status Aplikasi', $app['name'] . ' (' . ($new ? 'Aktif' : 'Deaktif') . ')');
            $msg = "Status aplikasi diperbarui.";
        }
    } elseif ($action === 'toggle_signed') {
        $id  = (int)$_POST['app_id'];
        $row = $db->prepare("SELECT * FROM sso_applications WHERE id = ? LIMIT 1");
        $row->execute([$id]);
        $app = $row->fetch(PDO::FETCH_ASSOC);
        if ($app) {
            $new = $app['use_signed_token'] ? 0 : 1;
            $db->prepare("UPDATE sso_applications SET use_signed_token = ? WHERE id = ?")->execute([$new, $id]);
            log_admin_action($user['id'], $user['name'],
                'Toggle Signed Token: ' . ($new ? 'ON' : 'OFF'), $app['name']);
            $msg = "Signed Token diubah ke " . ($new ? 'ON' : 'OFF') . ".";
        }
    } elseif ($action === 'delete_app') {
        $id  = (int)$_POST['app_id'];
        $row = $db->prepare("SELECT name FROM sso_applications WHERE id = ? LIMIT 1");
        $row->execute([$id]);
        $app = $row->fetch(PDO::FETCH_ASSOC);
        if ($app) {
            $db->prepare("DELETE FROM sso_applications WHERE id = ?")->execute([$id]);
            $db->prepare("DELETE FROM user_applications WHERE application_id = ?")->execute([$id]);
            log_admin_action($user['id'], $user['name'], 'Hapus Aplikasi', $app['name']);
            $msg = "Aplikasi berhasil dihapus.";
        }
    }
}

$apps = $db->query("SELECT * FROM sso_applications ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin — Aplikasi SSO</title>
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
  main{max-width:1000px;margin:0 auto;padding:2rem 1.5rem}
  h1{font-family:'DM Serif Display',serif;font-size:1.8rem;margin-bottom:.4rem}
  .subtitle{color:var(--text-dim);font-size:.88rem;margin-bottom:2rem}
  .msg{background:rgba(200,169,110,.1);border:1px solid rgba(200,169,110,.25);color:var(--accent);border-radius:8px;padding:.75rem 1rem;font-size:.88rem;margin-bottom:1.5rem}
  .msg code{background:var(--border);padding:.15rem .35rem;border-radius:4px;font-family:'DM Mono',monospace}
  /* Add form */
  .add-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:1.5rem;margin-bottom:2rem}
  .add-card h3{font-size:1rem;margin-bottom:1rem;color:var(--accent)}
  .form-row{display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:.75rem}
  .form-row .fg{flex:1;min-width:200px}
  label{display:block;font-size:.73rem;font-weight:500;color:var(--text-dim);text-transform:uppercase;letter-spacing:.07em;margin-bottom:.4rem}
  input[type=text],input[type=url],textarea{width:100%;background:var(--bg);border:1px solid var(--border);border-radius:7px;padding:.65rem .9rem;color:var(--text);font-family:'DM Sans',sans-serif;font-size:.88rem;outline:none;transition:border-color .2s}
  input:focus,textarea:focus{border-color:var(--accent)}
  .check-label{display:flex;align-items:center;gap:.5rem;font-size:.85rem;color:var(--text-dim);cursor:pointer;margin-top:.5rem}
  .check-label input{accent-color:var(--accent)}
  .btn-add{background:var(--accent);color:#0a0c10;border:none;border-radius:8px;padding:.6rem 1.2rem;font-size:.88rem;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;margin-top:.75rem}
  .btn-add:hover{opacity:.88}
  /* Apps table */
  table{width:100%;border-collapse:collapse;background:var(--surface);border-radius:12px;overflow:hidden;border:1px solid var(--border)}
  th{text-align:left;font-size:.72rem;font-family:'DM Mono',monospace;text-transform:uppercase;letter-spacing:.08em;color:var(--text-dim);padding:.75rem 1rem;border-bottom:1px solid var(--border);background:var(--surface2)}
  td{padding:.75rem 1rem;border-bottom:1px solid var(--border);font-size:.85rem;vertical-align:middle}
  tr:last-child td{border-bottom:none}
  .badge{display:inline-block;padding:.2rem .6rem;border-radius:20px;font-size:.7rem;font-family:'DM Mono',monospace;text-transform:uppercase}
  .badge-on{background:rgba(39,174,96,.12);color:#69d98c}
  .badge-off{background:rgba(192,57,43,.12);color:#e57373}
  .mono{font-family:'DM Mono',monospace;font-size:.75rem;color:var(--text-dim);word-break:break-all}
  button{background:var(--surface2);border:1px solid var(--border);color:var(--text);border-radius:6px;padding:.28rem .6rem;font-size:.75rem;cursor:pointer;font-family:'DM Sans',sans-serif;transition:border-color .2s;margin:.15rem}
  button:hover{border-color:var(--accent)}
  .btn-danger{border-color:rgba(192,57,43,.4);color:#e57373}
  .btn-danger:hover{border-color:#e57373}
</style>
</head>
<body>
<nav>
  <div class="nav-brand">GUDEG Admin</div>
  <div class="nav-links">
    <a href="/admin/">Users</a>
    <a href="/admin/apps.php" class="active">Aplikasi SSO</a>
    <a href="/admin/logs.php">Logs</a>
  </div>
  <div class="nav-right">
    <a href="/dashboard.php">← Portal</a>
    &nbsp;&nbsp;
    <a href="/logout.php">Keluar</a>
  </div>
</nav>

<main>
  <h1>Aplikasi SSO</h1>
  <p class="subtitle">Daftarkan dan kelola aplikasi yang terintegrasi dengan SSO ini.</p>

  <?php if ($msg): ?><div class="msg"><?= $msg ?></div><?php endif; ?>

  <!-- Add App -->
  <div class="add-card">
    <h3>+ Daftarkan Aplikasi Baru</h3>
    <form method="POST">
      <input type="hidden" name="action" value="add_app">
      <div class="form-row">
        <div class="fg">
          <label>Nama Aplikasi</label>
          <input type="text" name="name" placeholder="Nama aplikasi" required>
        </div>
        <div class="fg">
          <label>Redirect URL (Callback)</label>
          <input type="text" name="redirect" placeholder="https://app.example.com/callback" required>
        </div>
      </div>
      <div class="form-row">
        <div class="fg" style="flex:2">
          <label>Deskripsi</label>
          <input type="text" name="description" placeholder="Deskripsi singkat (opsional)">
        </div>
      </div>
      <label class="check-label">
        <input type="checkbox" name="use_signed_token">
        Gunakan RSA Signed Token (lebih aman, memerlukan public key di sisi klien)
      </label>
      <br>
      <button type="submit" class="btn-add">Daftarkan</button>
    </form>
  </div>

  <!-- Apps Table -->
  <table>
    <thead>
      <tr><th>Nama</th><th>Client ID / Secret</th><th>Redirect</th><th>Status</th><th>Signed</th><th>Aksi</th></tr>
    </thead>
    <tbody>
    <?php foreach ($apps as $app): ?>
    <tr>
      <td>
        <strong><?= htmlspecialchars($app['name']) ?></strong>
        <?php if ($app['description']): ?>
          <br><span style="font-size:.75rem;color:var(--text-dim)"><?= htmlspecialchars($app['description']) ?></span>
        <?php endif; ?>
      </td>
      <td>
        <div class="mono"><?= htmlspecialchars($app['client_id']) ?></div>
        <div class="mono" style="margin-top:.25rem;filter:blur(3px);transition:filter .2s" onmouseover="this.style.filter='none'" onmouseout="this.style.filter='blur(3px)'"><?= htmlspecialchars($app['client_secret']) ?></div>
      </td>
      <td class="mono"><?= htmlspecialchars($app['redirect']) ?></td>
      <td>
        <?php if ($app['is_active']): ?>
          <span class="badge badge-on">Aktif</span>
        <?php else: ?>
          <span class="badge badge-off">Nonaktif</span>
        <?php endif; ?>
      </td>
      <td>
        <?php if ($app['use_signed_token']): ?>
          <span class="badge badge-on">RSA</span>
        <?php else: ?>
          <span class="badge badge-off">HMAC</span>
        <?php endif; ?>
      </td>
      <td>
        <form method="POST" style="display:inline">
          <input type="hidden" name="action" value="toggle_status">
          <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
          <button type="submit"><?= $app['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?></button>
        </form>
        <form method="POST" style="display:inline">
          <input type="hidden" name="action" value="toggle_signed">
          <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
          <button type="submit">Toggle RSA</button>
        </form>
        <form method="POST" style="display:inline" onsubmit="return confirm('Hapus aplikasi ini?')">
          <input type="hidden" name="action" value="delete_app">
          <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
          <button type="submit" class="btn-danger">Hapus</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</main>
</body>
</html>
