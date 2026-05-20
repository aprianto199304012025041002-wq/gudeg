<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$user = get_current_user_data();
$db   = getDB();

$page = max(1, (int)($_GET['page'] ?? 1));
$per  = 50;
$off  = ($page - 1) * $per;

$tab = $_GET['tab'] ?? 'user';

if ($tab === 'admin') {
    $total = $db->query("SELECT COUNT(*) FROM admin_logs")->fetchColumn();
    $logs  = $db->query("SELECT * FROM admin_logs ORDER BY id DESC LIMIT $per OFFSET $off")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $total = $db->query("SELECT COUNT(*) FROM user_logs")->fetchColumn();
    $logs  = $db->query("SELECT * FROM user_logs ORDER BY id DESC LIMIT $per OFFSET $off")->fetchAll(PDO::FETCH_ASSOC);
}

$pages = ceil($total / $per);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin — Logs</title>
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
  .subtitle{color:var(--text-dim);font-size:.88rem;margin-bottom:1.5rem}
  .tab-links{display:flex;gap:.5rem;margin-bottom:1.5rem}
  .tab-links a{padding:.4rem .9rem;border-radius:7px;font-size:.83rem;text-decoration:none;border:1px solid var(--border);color:var(--text-dim);transition:border-color .2s,color .2s}
  .tab-links a.active,.tab-links a:hover{border-color:var(--accent);color:var(--accent)}
  table{width:100%;border-collapse:collapse;background:var(--surface);border-radius:12px;overflow:hidden;border:1px solid var(--border)}
  th{text-align:left;font-size:.72rem;font-family:'DM Mono',monospace;text-transform:uppercase;letter-spacing:.08em;color:var(--text-dim);padding:.7rem 1rem;border-bottom:1px solid var(--border);background:var(--surface2)}
  td{padding:.65rem 1rem;border-bottom:1px solid var(--border);font-size:.83rem;vertical-align:middle}
  tr:last-child td{border-bottom:none}
  .mono{font-family:'DM Mono',monospace;font-size:.75rem;color:var(--text-dim)}
  .pagination{display:flex;gap:.4rem;margin-top:1.5rem;justify-content:center}
  .pagination a{padding:.35rem .75rem;border-radius:6px;text-decoration:none;font-size:.83rem;border:1px solid var(--border);color:var(--text-dim)}
  .pagination a.current{border-color:var(--accent);color:var(--accent)}
  .pagination a:hover{border-color:var(--accent)}
</style>
</head>
<body>
<nav>
  <div class="nav-brand">GUDEG Admin</div>
  <div class="nav-links">
    <a href="/admin/">Users</a>
    <a href="/admin/apps.php">Aplikasi SSO</a>
    <a href="/admin/logs.php" class="active">Logs</a>
  </div>
  <div class="nav-right">
    <a href="/dashboard.php">← Portal</a>
    &nbsp;&nbsp;
    <a href="/logout.php">Keluar</a>
  </div>
</nav>

<main>
  <h1>Activity Logs</h1>
  <p class="subtitle">Total <?= number_format($total) ?> entri</p>

  <div class="tab-links">
    <a href="?tab=user" class="<?= $tab==='user'?'active':'' ?>">User Logs</a>
    <a href="?tab=admin" class="<?= $tab==='admin'?'active':'' ?>">Admin Logs</a>
  </div>

  <table>
    <thead>
    <?php if ($tab === 'user'): ?>
      <tr><th>#</th><th>User</th><th>Aktivitas</th><th>IP</th><th>Waktu</th></tr>
    <?php else: ?>
      <tr><th>#</th><th>Admin</th><th>Aksi</th><th>Target</th><th>Waktu</th></tr>
    <?php endif; ?>
    </thead>
    <tbody>
    <?php foreach ($logs as $log): ?>
    <tr>
      <td class="mono"><?= $log['id'] ?></td>
      <?php if ($tab === 'user'): ?>
      <td>
        <span><?= htmlspecialchars($log['user_name']) ?></span><br>
        <span class="mono"><?= htmlspecialchars($log['user_email']) ?></span>
      </td>
      <td><?= htmlspecialchars($log['activity']) ?></td>
      <td class="mono"><?= htmlspecialchars($log['ip_address']) ?></td>
      <?php else: ?>
      <td><?= htmlspecialchars($log['admin_name'] ?? '') ?></td>
      <td><?= htmlspecialchars($log['action']) ?></td>
      <td><?= htmlspecialchars($log['target_user']) ?></td>
      <?php endif; ?>
      <td class="mono"><?= date('d M y H:i', strtotime($log['created_at'])) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <?php if ($pages > 1): ?>
  <div class="pagination">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
      <a href="?tab=<?= $tab ?>&page=<?= $i ?>" class="<?= $i===$page?'current':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</main>
</body>
</html>
