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
*, *::before, *::after { font-weight: inherit; }

.tab-bar { display: flex; gap: 5px; margin-bottom: 12px; }
.tab-bar a {
  padding: 5px 12px;
  border: 1px solid var(--border);
  border-radius: 3px;
  font-size: 12px; font-weight: 500;
  text-transform: uppercase; letter-spacing: .05em;
  color: var(--text-dim);
  transition: border-color .12s, color .12s;
}
.tab-bar a.active,
.tab-bar a:hover { border-color: var(--accent-dim); color: var(--accent); }

.log-list { display: flex; flex-direction: column; gap: 5px; margin-bottom: 12px; }
.log-item {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 4px;
  padding: 9px 12px;
}
.log-item-top {
  display: flex; justify-content: space-between; align-items: flex-start;
  gap: 6px; margin-bottom: 4px;
}
.log-action { font-size: 13px; font-weight: 600; color: var(--text); }
.log-time {
  font-size: 12px; color: var(--text-dim);
  white-space: nowrap; flex-shrink: 0;
}
.log-meta { font-size: 12px; color: var(--text-dim); line-height: 1.6; }
.log-meta span { margin-right: 8px; }
.log-id { font-size: 11px; color: var(--text-muted); }

.pagination {
  display: flex; gap: 4px; justify-content: center; flex-wrap: wrap;
}
.pagination a {
  padding: 4px 10px;
  border: 1px solid var(--border);
  border-radius: 3px;
  font-size: 12px;
  color: var(--text-dim);
  transition: border-color .12s, color .12s;
}
.pagination a.current { border-color: var(--accent-dim); color: var(--accent); }
.pagination a:hover { border-color: var(--accent-dim); color: var(--accent); }
.pagination-sep { font-size: 12px; color: var(--text-muted); padding: 4px 2px; }
</style>

<div class="page-header">
  <div class="page-title">Activity Logs</div>
  <div class="page-subtitle">Total <?= number_format($total) ?> entri</div>
</div>

<div class="tab-bar">
  <a href="?tab=user"  class="<?= $tab === 'user'  ? 'active' : '' ?>">User Logs</a>
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
    <a href="?tab=<?= $tab ?>&page=<?= $page - 1 ?>">‹</a>
  <?php endif; ?>
  <?php
  $start = max(1, $page - 2);
  $end   = min($pages, $page + 2);
  if ($start > 1) echo '<a href="?tab=' . $tab . '&page=1">1</a>';
  if ($start > 2) echo '<span class="pagination-sep">…</span>';
  for ($i = $start; $i <= $end; $i++):
  ?>
    <a href="?tab=<?= $tab ?>&page=<?= $i ?>" class="<?= $i === $page ? 'current' : '' ?>"><?= $i ?></a>
  <?php endfor; ?>
  <?php if ($end < $pages - 1) echo '<span class="pagination-sep">…</span>'; ?>
  <?php if ($end < $pages) echo '<a href="?tab=' . $tab . '&page=' . $pages . '">' . $pages . '</a>'; ?>
  <?php if ($page < $pages): ?>
    <a href="?tab=<?= $tab ?>&page=<?= $page + 1 ?>">›</a>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php layout_foot(); ?>