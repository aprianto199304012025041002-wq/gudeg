<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /dashboard.php'); exit;
}

$pageTitle  = 'Log Akses';
$activePage = 'admin-logs';
$isAdmin    = true;

// [ambil dari DB — logic tidak diubah]
$perPage  = 20;
$page     = max(1, (int)($_GET['page'] ?? 1));
$total    = 127; // ganti dengan COUNT(*) dari DB
$pages    = (int)ceil($total / $perPage);

// dummy logs
$logs = [];
for ($i = 1; $i <= 20; $i++) {
    $logs[] = [
        'time'    => '2025-04-10 ' . str_pad(rand(0,23),2,'0',STR_PAD_LEFT) . ':' . str_pad(rand(0,59),2,'0',STR_PAD_LEFT),
        'user'    => ['budi','siti','doni','rina','andi'][rand(0,4)],
        'app'     => ['SIMPEG','E-Office','Portal HR'][rand(0,2)],
        'ip'      => '10.10.' . rand(1,5) . '.' . rand(1,254),
        'status'  => rand(0,4) > 0 ? 'success' : 'failed',
    ];
}

include __DIR__ . '/../includes/layout.php';

// Smart pagination helper
function pagLinks($page, $pages) {
    $links = [];
    $delta = 2;
    $range = [];
    for ($i = max(1, $page-$delta); $i <= min($pages, $page+$delta); $i++) {
        $range[] = $i;
    }
    if (!in_array(1, $range)) { $links[] = 1; if ($range[0] > 2) $links[] = '...'; }
    foreach ($range as $r) $links[] = $r;
    if (!in_array($pages, $range)) {
        if ($range[count($range)-1] < $pages-1) $links[] = '...';
        $links[] = $pages;
    }
    return $links;
}
?>

<div class="page-header">
  <h1>Log Akses</h1>
  <p>Total <?= number_format($total) ?> entri</p>
</div>

<!-- Filter bar -->
<div class="card" style="margin-bottom:16px;">
  <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
    <div class="form-group" style="margin:0;flex:1;min-width:130px;">
      <label class="form-label">Pengguna</label>
      <input class="form-control" type="text" name="user" placeholder="username"
             value="<?= htmlspecialchars($_GET['user'] ?? '') ?>"/>
    </div>
    <div class="form-group" style="margin:0;flex:1;min-width:130px;">
      <label class="form-label">Aplikasi</label>
      <input class="form-control" type="text" name="app" placeholder="nama app"
             value="<?= htmlspecialchars($_GET['app'] ?? '') ?>"/>
    </div>
    <div class="form-group" style="margin:0;min-width:110px;">
      <label class="form-label">Status</label>
      <select class="form-control" name="status">
        <option value="">Semua</option>
        <option value="success" <?= ($_GET['status']??'')==='success'?'selected':'' ?>>Berhasil</option>
        <option value="failed"  <?= ($_GET['status']??'')==='failed' ?'selected':'' ?>>Gagal</option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Filter</button>
    <a href="/admin/logs.php" class="btn btn-ghost">Reset</a>
  </form>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Waktu</th>
          <th>Pengguna</th>
          <th>Aplikasi</th>
          <th>IP</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($logs as $log): ?>
        <tr>
          <td style="font-size:.82rem;color:var(--text-muted);white-space:nowrap;"><?= htmlspecialchars($log['time']) ?></td>
          <td style="font-weight:500;"><?= htmlspecialchars($log['user']) ?></td>
          <td><?= htmlspecialchars($log['app']) ?></td>
          <td style="font-family:monospace;font-size:.8rem;color:var(--text-muted);"><?= htmlspecialchars($log['ip']) ?></td>
          <td>
            <?php if ($log['status']==='success'): ?>
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

  <!-- Smart pagination -->
  <?php if ($pages > 1): ?>
  <div class="pagination" style="margin-top:16px;">
    <?php if ($page > 1): ?>
      <a href="?page=<?= $page-1 ?>" title="Sebelumnya">‹</a>
    <?php else: ?>
      <span class="disabled">‹</span>
    <?php endif; ?>

    <?php foreach (pagLinks($page, $pages) as $link): ?>
      <?php if ($link === '...'): ?>
        <span style="pointer-events:none;border:none;background:none;color:var(--text-muted);">…</span>
      <?php elseif ($link === $page): ?>
        <span class="active"><?= $link ?></span>
      <?php else: ?>
        <a href="?page=<?= $link ?>"><?= $link ?></a>
      <?php endif; ?>
    <?php endforeach; ?>

    <?php if ($page < $pages): ?>
      <a href="?page=<?= $page+1 ?>" title="Berikutnya">›</a>
    <?php else: ?>
      <span class="disabled">›</span>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/layout_end.php'; ?>