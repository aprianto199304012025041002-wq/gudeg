<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();

$user = get_current_user_data();
$db   = getDB();

// ── Auto-cleanup ─────────────────────────────────────────────────────────────
// Cleanup token blacklist expired
try {
    $db->prepare("DELETE FROM token_blacklist WHERE exp < ?")->execute([time()]);
} catch (\Throwable $e) {}

// Cleanup log lama > 90 hari (probabilistik 5%, tidak perlu cron)
cleanup_old_logs();

$page = max(1, (int)($_GET['page'] ?? 1));
$per  = 50;
$off  = ($page - 1) * $per;
$tab  = ($_GET['tab'] ?? 'user') === 'admin' ? 'admin' : 'user';

// ── Filter params ─────────────────────────────────────────────────────────────
$search     = trim($_GET['search'] ?? '');
$filter_ip  = trim($_GET['ip'] ?? '');
$filter_date = trim($_GET['date'] ?? '');

// ── Build query dengan filter ─────────────────────────────────────────────────
$where  = [];
$params = [];

if ($tab === 'admin') {
    if ($search) {
        $where[]  = "(action LIKE ? OR admin_name LIKE ? OR target_user LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($filter_ip) {
        $where[]  = "ip_address LIKE ?";
        $params[] = "%$filter_ip%";
    }
    if ($filter_date) {
        $where[]  = "DATE(created_at) = ?";
        $params[] = $filter_date;
    }

    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $count_stmt = $db->prepare("SELECT COUNT(*) FROM admin_logs $where_sql");
    $count_stmt->execute($params);
    $total = $count_stmt->fetchColumn();

    $log_stmt = $db->prepare("SELECT * FROM admin_logs $where_sql ORDER BY id DESC LIMIT $per OFFSET $off");
    $log_stmt->execute($params);
    $logs = $log_stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    if ($search) {
        $where[]  = "(activity LIKE ? OR user_name LIKE ? OR user_email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($filter_ip) {
        $where[]  = "ip_address LIKE ?";
        $params[] = "%$filter_ip%";
    }
    if ($filter_date) {
        $where[]  = "DATE(created_at) = ?";
        $params[] = $filter_date;
    }

    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $count_stmt = $db->prepare("SELECT COUNT(*) FROM user_logs $where_sql");
    $count_stmt->execute($params);
    $total = $count_stmt->fetchColumn();

    $log_stmt = $db->prepare("SELECT * FROM user_logs $where_sql ORDER BY id DESC LIMIT $per OFFSET $off");
    $log_stmt->execute($params);
    $logs = $log_stmt->fetchAll(PDO::FETCH_ASSOC);
}

$pages = ceil($total / $per);

// Build filter query string untuk pagination
$filter_qs = http_build_query(array_filter([
    'tab'    => $tab,
    'search' => $search,
    'ip'     => $filter_ip,
    'date'   => $filter_date,
]));

layout_head('Log Aktivitas', 'admin-logs');
?>
<style>
*, *::before, *::after { font-weight: inherit; }

.tab-bar { display: flex; gap: 5px; margin-bottom: 12px; overflow-x: auto; -webkit-overflow-scrolling: touch; flex-wrap: nowrap; }
.tab-bar a {
  padding: 5px 12px;
  border: 1px solid var(--border);
  border-radius: 3px;
  font-size: 12px; font-weight: 500;
  text-transform: uppercase; letter-spacing: .05em;
  color: var(--text-dim);
  transition: border-color .12s, color .12s;
  white-space: nowrap; flex-shrink: 0;
}
.tab-bar a.active,
.tab-bar a:hover { border-color: var(--accent-dim); color: var(--accent); }

.filter-bar {
  display: flex; gap: 6px; margin-bottom: 10px; flex-wrap: wrap; align-items: center;
}
.filter-bar input[type="text"],
.filter-bar input[type="date"] {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 3px;
  padding: 5px 9px;
  font-size: 12px;
  color: var(--text);
  font-family: var(--sans);
  outline: none;
  transition: border-color .12s;
}
.filter-bar input[type="text"] { width: 180px; }
.filter-bar input[type="date"] { width: 140px; }
.filter-bar input:focus { border-color: var(--accent-dim); }

/* ── Mobile: filter inputs full-width ─────────────────── */
@media (max-width: 600px) {
  .filter-bar { flex-direction: column; align-items: stretch; }
  .filter-bar input[type="text"],
  .filter-bar input[type="date"] { width: 100%; }
  .filter-bar button { width: 100%; text-align: center; }
  .log-item-top { flex-direction: column; gap: 2px; }
  .log-time { font-size: 11px; }
  .log-action { font-size: 12px; }
  .log-meta span { display: block; margin-right: 0; }
  .pagination { gap: 3px; }
  .pagination a { padding: 4px 8px; font-size: 11px; }
}
.filter-bar button {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 3px;
  padding: 5px 12px;
  font-size: 12px; font-weight: 500;
  color: var(--text-dim);
  font-family: var(--sans);
  text-transform: uppercase; letter-spacing: .04em;
  cursor: pointer; transition: border-color .12s, color .12s;
}
.filter-bar button:hover { border-color: var(--accent-dim); color: var(--accent); }
.filter-bar .btn-reset {
  color: var(--danger); border-color: rgba(192,64,64,.2);
}
.filter-bar .btn-reset:hover { border-color: var(--danger); }
.filter-active {
  font-size: 11px; color: var(--accent-dim);
  font-family: var(--sans); letter-spacing: .04em;
  text-transform: uppercase; padding: 5px 0;
}

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
.log-time { font-size: 12px; color: var(--text-dim); white-space: nowrap; flex-shrink: 0; }
.log-meta { font-size: 12px; color: var(--text-dim); line-height: 1.6; }
.log-meta span { margin-right: 8px; }
.log-id { font-size: 11px; color: var(--text-muted); }
.log-ip {
  font-family: var(--mono); font-size: 11px;
  background: rgba(122,162,212,.06);
  border: 1px solid rgba(122,162,212,.12);
  border-radius: 3px; padding: 0 5px;
  color: var(--accent-dim);
}
.log-empty {
  text-align: center; padding: 2rem;
  font-size: 13px; color: var(--text-dim);
  font-family: var(--sans);
}

.pagination { display: flex; gap: 4px; justify-content: center; flex-wrap: wrap; }
.pagination a {
  padding: 4px 10px;
  border: 1px solid var(--border);
  border-radius: 3px; font-size: 12px;
  color: var(--text-dim);
  transition: border-color .12s, color .12s;
}
.pagination a.current { border-color: var(--accent-dim); color: var(--accent); }
.pagination a:hover   { border-color: var(--accent-dim); color: var(--accent); }
.pagination-sep { font-size: 12px; color: var(--text-muted); padding: 4px 2px; }
</style>

<div class="page-header">
  <div class="page-title">Log Aktivitas</div>
  <div class="page-subtitle">Total <?= number_format($total) ?> entri<?= ($search || $filter_ip || $filter_date) ? ' (difilter)' : '' ?></div>
</div>

<div class="tab-bar">
  <a href="?tab=user"  class="<?= $tab === 'user'  ? 'active' : '' ?>">User Logs</a>
  <a href="?tab=admin" class="<?= $tab === 'admin' ? 'active' : '' ?>">Admin Logs</a>
</div>

<!-- Filter bar -->
<form method="GET">
  <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
  <div class="filter-bar">
    <input type="text" name="search" placeholder="Cari aksi / nama / email..."
           value="<?= htmlspecialchars($search) ?>">
    <input type="text" name="ip" placeholder="Filter IP..."
           value="<?= htmlspecialchars($filter_ip) ?>">
    <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>">
    <button type="submit">Filter</button>
    <?php if ($search || $filter_ip || $filter_date): ?>
      <a href="?tab=<?= $tab ?>" class="filter-bar"><button type="button" class="btn-reset">Reset</button></a>
    <?php endif; ?>
  </div>
</form>

<div class="log-list">
<?php if ($logs): ?>
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
      <span class="log-ip"><?= htmlspecialchars($log['ip_address']) ?></span>
    <?php else: ?>
      <span><?= htmlspecialchars($log['admin_name'] ?? '') ?></span>
      <?php if (!empty($log['target_user']) && $log['target_user'] !== '-'): ?>
        <span>→ <?= htmlspecialchars($log['target_user']) ?></span>
      <?php endif; ?>
      <?php if (!empty($log['ip_address'])): ?>
        <span class="log-ip"><?= htmlspecialchars($log['ip_address']) ?></span>
      <?php endif; ?>
    <?php endif; ?>
    <span class="log-id">#<?= $log['id'] ?></span>
  </div>
</div>
<?php endforeach; ?>
<?php else: ?>
  <div class="log-empty">Tidak ada log yang cocok dengan filter.</div>
<?php endif; ?>
</div>

<?php if ($pages > 1): ?>
<div class="pagination">
  <?php if ($page > 1): ?>
    <a href="?<?= $filter_qs ?>&page=<?= $page - 1 ?>">‹</a>
  <?php endif; ?>
  <?php
  $start = max(1, $page - 2);
  $end   = min($pages, $page + 2);
  if ($start > 1) echo '<a href="?' . $filter_qs . '&page=1">1</a>';
  if ($start > 2) echo '<span class="pagination-sep">…</span>';
  for ($i = $start; $i <= $end; $i++):
  ?>
    <a href="?<?= $filter_qs ?>&page=<?= $i ?>" class="<?= $i === $page ? 'current' : '' ?>"><?= $i ?></a>
  <?php endfor; ?>
  <?php if ($end < $pages - 1) echo '<span class="pagination-sep">…</span>'; ?>
  <?php if ($end < $pages) echo '<a href="?' . $filter_qs . '&page=' . $pages . '">' . $pages . '</a>'; ?>
  <?php if ($page < $pages): ?>
    <a href="?<?= $filter_qs ?>&page=<?= $page + 1 ?>">›</a>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php layout_foot(); ?>