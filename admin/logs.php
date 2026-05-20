<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();

$user = get_current_user_data();
$db   = getDB();

$page = max(1, (int)($_GET['page'] ?? 1));
$per  = 50;
$off  = ($page - 1) * $per;
$tab  = ($_GET['tab'] ?? 'user') === 'admin' ? 'admin' : 'user';

if ($tab === 'admin') {
    $total = $db->query("SELECT COUNT(*) FROM admin_logs")->fetchColumn();
    $logs  = $db->query("SELECT * FROM admin_logs ORDER BY id DESC LIMIT $per OFFSET $off")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $total = $db->query("SELECT COUNT(*) FROM user_logs")->fetchColumn();
    $logs  = $db->query("SELECT * FROM user_logs ORDER BY id DESC LIMIT $per OFFSET $off")->fetchAll(PDO::FETCH_ASSOC);
}

$pages = ceil($total / $per);

layout_head('Logs', 'admin-logs');
?>
<style>
/* Tabs */
.tab-bar { display: flex; gap: .4rem; margin-bottom: 1.5rem; }
.tab-bar a {
  padding: .4rem .9rem;
  border-radius: 7px; font-size: .83rem;
  border: 1px solid var(--border);
  color: var(--text-dim); transition: border-color .15s, color .15s;
}
.tab-bar a.active, .tab-bar a:hover { border-color: var(--accent); color: var(--accent); }

/* Log list (mobile-first) */
.log-list { display: flex; flex-direction: column; gap: .5rem; margin-bottom: 1.5rem; }
.log-item {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: .8rem 1rem;
}
.log-item-top {
  display: flex; justify-content: space-between; align-items: flex-start;
  gap: .5rem; margin-bottom: .3rem;
}
.log-action { font-size: .88rem; font-weight: 500; }
.log-time {
  font-family: 'DM Mono', monospace; font-size: .7rem;
  color: var(--text-dim); white-space: nowrap; flex-shrink: 0;
}
.log-meta { font-size: .75rem; color: var(--text-dim); line-height: 1.5; }
.log-meta span { margin-right: .75rem; }
.log-id {
  font-family: 'DM Mono', monospace; font-size: .68rem;
  color: var(--text-dim); opacity: .5;
}

/* Pagination */
.pagination {
  display: flex; gap: .35rem; justify-content: center; flex-wrap: wrap;
}
.pagination a {
  padding: .35rem .7rem; border-radius: 6px;
  font-size: .8rem; border: 1px solid var(--border);
  color: var(--text-dim); transition: border-color .15s, color .15s;
}
.pagination a.current { border-color: var(--accent); color: var(--accent); }
.pagination a:hover { border-color: var(--accent); }
</style>

<div class="page-header">
  <h1 class="page-title">Activity Logs</h1>
  <p class="page-subtitle">Total <?= number_format($total) ?> entri</p>
</div>

<div class="tab-bar">
  <a href="?tab=user" class="<?= $tab === 'user' ? 'active' : '' ?>">User Logs</a>
  <a href="?tab=admin" class="<?= $tab === 'admin' ? 'active' : '' ?>">Admin Logs</a>
</div>

<div class="log-list">
<?php foreach ($logs as $log): ?>
<div class="log-item">
  <div class="log-item-top">
    <?php if ($tab === 'user'): ?>
      <span class="log-action"><?= htmlspecialchars($log['activity']) ?></span>
    <?php else: ?>
      <span class="log-action"><?= htmlspecialchars($log['action']) ?></span>
    <?php endif; ?>
    <span class="log-time"><?= date('d M y H:i', strtotime($log['created_at'])) ?></span>
  </div>
  <div class="log-meta">
    <?php if ($tab === 'user'): ?>
      <span><?= htmlspecialchars($log['user_name']) ?></span>
      <span><?= htmlspecialchars($log['user_email']) ?></span>
      <span><?= htmlspecialchars($log['ip_address']) ?></span>
    <?php else: ?>
      <span><?= htmlspecialchars($log['admin_name'] ?? '') ?></span>
      <?php if (!empty($log['target_user']) && $log['target_user'] !== '-'): ?>
        <span>→ <?= htmlspecialchars($log['target_user']) ?></span>
      <?php endif; ?>
    <?php endif; ?>
    <span class="log-id">#<?= $log['id'] ?></span>
  </div>
</div>
<?php endforeach; ?>
</div>

<?php if ($pages > 1): ?>
<div class="pagination">
  <?php if ($page > 1): ?>
    <a href="?tab=<?= $tab ?>&page=<?= $page-1 ?>">‹</a>
  <?php endif; ?>
  <?php
  // Show limited page numbers around current
  $start = max(1, $page - 2);
  $end   = min($pages, $page + 2);
  if ($start > 1) echo '<a href="?tab='.$tab.'&page=1">1</a>';
  if ($start > 2) echo '<span style="color:var(--text-dim);padding:.35rem .3rem">…</span>';
  for ($i = $start; $i <= $end; $i++):
  ?>
    <a href="?tab=<?= $tab ?>&page=<?= $i ?>" class="<?= $i === $page ? 'current' : '' ?>"><?= $i ?></a>
  <?php endfor; ?>
  <?php if ($end < $pages - 1) echo '<span style="color:var(--text-dim);padding:.35rem .3rem">…</span>'; ?>
  <?php if ($end < $pages) echo '<a href="?tab='.$tab.'&page='.$pages.'">'.$pages.'</a>'; ?>
  <?php if ($page < $pages): ?>
    <a href="?tab=<?= $tab ?>&page=<?= $page+1 ?>">›</a>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php layout_foot(); ?>