<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: /login.php'); exit; }

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
$isAdmin    = ($_SESSION['role'] ?? '') === 'admin';

include __DIR__ . '/includes/layout.php';

// [ambil data dari DB sesuai logic asli — contoh dummy:]
$totalApps   = 5;
$totalUsers  = 42;
$totalLogs   = 1280;
$recentLogs  = [
    ['time'=>'2025-04-10 08:12','app'=>'SIMPEG','user'=>'budi','status'=>'success'],
    ['time'=>'2025-04-10 07:55','app'=>'E-Office','user'=>'siti','status'=>'success'],
    ['time'=>'2025-04-10 07:30','app'=>'SIMPEG','user'=>'doni','status'=>'failed'],
];
?>

<div class="page-header">
  <h1>Dashboard</h1>
  <p>Selamat datang, <?= htmlspecialchars($_SESSION['name'] ?? 'Pengguna') ?></p>
</div>

<!-- Stat cards -->
<div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:12px; margin-bottom:20px;">
  <div class="card" style="text-align:center;">
    <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.07em;color:var(--text-muted);font-weight:600;margin-bottom:6px;">Aplikasi SSO</div>
    <div style="font-family:var(--font-head);font-size:2rem;font-weight:800;color:var(--accent);"><?= $totalApps ?></div>
  </div>
  <div class="card" style="text-align:center;">
    <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.07em;color:var(--text-muted);font-weight:600;margin-bottom:6px;">Total Pengguna</div>
    <div style="font-family:var(--font-head);font-size:2rem;font-weight:800;color:var(--blue);"><?= $totalUsers ?></div>
  </div>
  <div class="card" style="text-align:center;">
    <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.07em;color:var(--text-muted);font-weight:600;margin-bottom:6px;">Log Akses</div>
    <div style="font-family:var(--font-head);font-size:2rem;font-weight:800;color:var(--text);"><?= number_format($totalLogs) ?></div>
  </div>
</div>

<!-- Recent logs -->
<div class="card">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
    <div style="font-family:var(--font-head);font-size:.9rem;font-weight:700;">Akses Terbaru</div>
    <?php if ($isAdmin): ?>
    <a href="/admin/logs.php" style="font-size:.8rem;color:var(--blue);">Lihat semua →</a>
    <?php endif; ?>
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
          <td style="color:var(--text-muted);font-size:.82rem;"><?= htmlspecialchars($log['time']) ?></td>
          <td><?= htmlspecialchars($log['app']) ?></td>
          <td><?= htmlspecialchars($log['user']) ?></td>
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

<?php include __DIR__ . '/includes/layout_end.php'; ?>