<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';
require_login();

$user = get_current_user_data();
$db = getDB();
$isAdmin = ($user['role'] === 'admin');

// 1. Ambil Apps yang user punya akses
$apps = [];
if ($isAdmin) {
    $apps = $db->query("SELECT * FROM apps")->fetchAll();
} else {
    $stmt = $db->prepare("SELECT a.* FROM apps a JOIN user_apps ua ON a.id = ua.app_id WHERE ua.user_id = ?");
    $stmt->execute([$user['id']]);
    $apps = $stmt->fetchAll();
}

// 2. Ambil Statistik (contoh query standar, sesuaikan jika beda)
$totalApps = $db->query("SELECT COUNT(*) FROM apps")->fetchColumn();
$totalUsers = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalLogs = $db->query("SELECT COUNT(*) FROM logs")->fetchColumn();

// 3. Ambil Log Terbaru (5 terakhir)
$recentLogs = $db->query("
    SELECT l.*, u.name as user_name, a.name as app_name 
    FROM logs l 
    LEFT JOIN users u ON l.user_id = u.id 
    LEFT JOIN apps a ON l.app_id = a.id 
    ORDER BY l.created_at DESC LIMIT 5
")->fetchAll();

layout_head('Dashboard', 'dashboard');
?>

<div class="page-header">
  <h1>Dashboard</h1>
  <p>Selamat datang kembali, <?= htmlspecialchars($user['name']) ?></p>
</div>

<!-- Stat cards (Khusus admin, user biasa tampil app miliknya) -->
<?php if ($isAdmin): ?>
<div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:16px; margin-bottom:24px;">
  <div class="card" style="text-align:center;">
    <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);font-weight:600;margin-bottom:8px;">Aplikasi SSO</div>
    <div style="font-family:var(--font-head);font-size:2.5rem;font-weight:800;color:var(--accent);"><?= number_format($totalApps) ?></div>
  </div>
  <div class="card" style="text-align:center;">
    <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);font-weight:600;margin-bottom:8px;">Total Pengguna</div>
    <div style="font-family:var(--font-head);font-size:2.5rem;font-weight:800;color:var(--blue);"><?= number_format($totalUsers) ?></div>
  </div>
  <div class="card" style="text-align:center;">
    <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);font-weight:600;margin-bottom:8px;">Total Log Akses</div>
    <div style="font-family:var(--font-head);font-size:2.5rem;font-weight:800;color:var(--text);"><?= number_format($totalLogs) ?></div>
  </div>
</div>
<?php endif; ?>

<!-- Daftar Aplikasi User -->
<div class="page-header" style="margin-top: 30px;">
  <h2 style="font-family:var(--font-head);font-size:1.2rem;">Aplikasi Saya</h2>
</div>
<div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(250px,1fr)); gap:16px; margin-bottom:24px;">
  <?php if(empty($apps)): ?>
    <div class="card" style="color:var(--text-muted); font-size: .9rem;">Anda belum memiliki akses ke aplikasi manapun.</div>
  <?php else: ?>
    <?php foreach($apps as $app): ?>
      <div class="card" style="display:flex; flex-direction:column; justify-content:space-between;">
        <div>
          <h3 style="font-family:var(--font-head); font-size:1.1rem; margin-bottom:4px;"><?= htmlspecialchars($app['name']) ?></h3>
          <p style="font-size:.8rem; color:var(--text-muted); word-break:break-all;"><?= htmlspecialchars($app['redirect_url']) ?></p>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- Recent logs (Khusus Admin) -->
<?php if ($isAdmin && !empty($recentLogs)): ?>
<div class="card">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
    <div style="font-family:var(--font-head);font-size:1.1rem;font-weight:700;">Akses SSO Terbaru</div>
    <a href="/admin/logs.php" style="font-size:.85rem;color:var(--accent);font-weight:600;">Lihat Semua →</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Waktu</th>
          <th>Aplikasi</th>
          <th>Pengguna</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentLogs as $log): ?>
        <tr>
          <td style="color:var(--text-muted);font-size:.85rem;"><?= htmlspecialchars($log['created_at']) ?></td>
          <td><span class="badge badge-muted"><?= htmlspecialchars($log['app_name'] ?? '-') ?></span></td>
          <td style="font-weight:500;"><?= htmlspecialchars($log['user_name'] ?? '-') ?></td>
          <td>
            <?php if ($log['status'] === 'success'): ?>
              <span class="badge badge-green">Berhasil</span>
            <?php else: ?>
              <span class="badge badge-red">Gagal</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php layout_foot(); ?>