<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();

$user = get_current_user_data();
$db = getDB();

// Logic Pagination & Fetch Logs Asli
$perPage = 50;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$total = $db->query("SELECT COUNT(*) FROM logs")->fetchColumn();
$pages = (int)ceil($total / $perPage);

$logsStmt = $db->prepare("
    SELECT l.*, u.name as user_name, u.email, a.name as app_name
    FROM logs l
    LEFT JOIN users u ON l.user_id = u.id
    LEFT JOIN apps a ON l.app_id = a.id
    ORDER BY l.created_at DESC
    LIMIT ? OFFSET ?
");
$logsStmt->bindValue(1, $perPage, PDO::PARAM_INT);
$logsStmt->bindValue(2, $offset, PDO::PARAM_INT);
$logsStmt->execute();
$logs = $logsStmt->fetchAll();

function pagLinks($page, $pages) {
    $links = []; $range = [];
    for ($i = max(1, $page-2); $i <= min($pages, $page+2); $i++) $range[] = $i;
    if (!in_array(1, $range)) { $links[] = 1; if ($range[0] > 2) $links[] = '...'; }
    foreach ($range as $r) $links[] = $r;
    if (!in_array($pages, $range)) {
        if (end($range) < $pages-1) $links[] = '...';
        $links[] = $pages;
    }
    return $links;
}

layout_head('Log Akses', 'admin-logs');
?>

<div class="page-header">
  <h1>Log Aktivitas SSO</h1>
  <p>Total rekam jejak sistem: <strong><?= number_format($total) ?></strong> entri</p>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Waktu Akses</th>
          <th>Pengguna</th>
          <th>Aplikasi Tujuan</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($logs as $log): ?>
        <tr>
          <td style="font-size:.85rem;color:var(--text-muted);white-space:nowrap;">
            <?= htmlspecialchars($log['created_at']) ?>
          </td>
          <td>
            <div style="font-weight:600;"><?= htmlspecialchars($log['user_name'] ?? 'Sistem') ?></div>
            <div style="font-size:.75rem;color:var(--text-muted);"><?= htmlspecialchars($log['email'] ?? '-') ?></div>
          </td>
          <td>
             <span class="badge badge-blue"><?= htmlspecialchars($log['app_name'] ?? '-') ?></span>
          </td>
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

  <?php if ($pages > 1): ?>
  <div class="pagination">
    <?php if ($page > 1): ?>
      <a href="?page=<?= $page-1 ?>" title="Sebelumnya">‹</a>
    <?php else: ?>
      <span class="disabled">‹</span>
    <?php endif; ?>

    <?php foreach (pagLinks($page, $pages) as $link): ?>
      <?php if ($link === '...'): ?>
        <span style="border:none;background:none;color:var(--text-muted);pointer-events:none;">…</span>
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

<?php layout_foot(); ?>