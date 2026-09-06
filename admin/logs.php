<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();

$user = get_current_user_data();
$sb   = getSupabase();

// -- Auto-cleanup -------------------------------------------------------------
// Cleanup token blacklist expired
try {
    $sb->delete('token_blacklist', ['exp' => 'lt.' . time()]);
} catch (\Throwable $e) {
    gudeg_log('WARNING', 'admin/logs.php', 'Gagal cleanup token_blacklist: ' . $e->getMessage());
}

// Cleanup log lama > 90 hari (probabilistik 5%, tidak perlu cron)
cleanup_old_logs();

$page = max(1, (int)($_GET['page'] ?? 1));
$per  = in_array((int)($_GET['per'] ?? 20), [10, 20, 50, 100]) ? (int)$_GET['per'] : 20;
$off  = ($page - 1) * $per;
$tab  = ($_GET['tab'] ?? 'user') === 'admin' ? 'admin' : 'user';

// -- Filter params -------------------------------------------------------------
$search      = trim($_GET['search'] ?? '');
$filter_ip   = trim($_GET['ip'] ?? '');
$filter_date = trim($_GET['date'] ?? '');

/**
 * Escape karakter yang punya arti khusus di syntax or() PostgREST
 * (koma memisah kondisi, kurung buat grouping) supaya pencarian user
 * yang kebetulan mengandung karakter itu tidak merusak query.
 */
function pgrest_escape_search(string $v): string {
    return str_replace([',', '(', ')'], ['\,', '\(', '\)'], $v);
}

// -- Build filter dengan syntax PostgREST ------------------------------------
$table   = $tab === 'admin' ? 'admin_logs' : 'user_logs';
$filters = [];

if ($search) {
    $needle = '*' . pgrest_escape_search($search) . '*';
    if ($tab === 'admin') {
        $filters[] = 'or=(action.ilike.' . rawurlencode($needle)
                   . ',admin_name.ilike.' . rawurlencode($needle)
                   . ',target_user.ilike.' . rawurlencode($needle) . ')';
    } else {
        $filters[] = 'or=(activity.ilike.' . rawurlencode($needle)
                   . ',user_name.ilike.' . rawurlencode($needle)
                   . ',user_email.ilike.' . rawurlencode($needle) . ')';
    }
}
if ($filter_ip) {
    $filters[] = 'ip_address=ilike.' . rawurlencode('*' . pgrest_escape_search($filter_ip) . '*');
}
if ($filter_date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_date)) {
    $next = date('Y-m-d', strtotime($filter_date . ' +1 day'));
    $filters[] = 'created_at=gte.' . rawurlencode($filter_date . 'T00:00:00');
    $filters[] = 'created_at=lt.'  . rawurlencode($next . 'T00:00:00');
}

$result = $sb->selectRaw($table, $filters, '*', $per, $off, 'id.desc', true);
$logs   = $result['data'];
$total  = $result['total'] ?? count($logs);

$pages = ceil($total / $per);

// Build filter query string untuk pagination
$filter_qs = http_build_query(array_filter([
    'tab'    => $tab,
    'search' => $search,
    'ip'     => $filter_ip,
    'date'   => $filter_date,
    'per'    => $per !== 20 ? $per : null,
]));
$safe_qs = htmlspecialchars($filter_qs);

layout_head('Admin . Log', 'admin-logs');
?>
<style nonce="<?= csp_nonce() ?>">
*, *::before, *::after { font-weight: inherit; }

.tab-bar { display: flex; gap: 5px; margin-bottom: 12px; overflow-x: auto; -webkit-overflow-scrolling: touch; flex-wrap: nowrap; }
.tab-bar a {
  padding: 5px 12px;
  border: 1px solid var(--glass-border);
  border-radius: var(--radius-sm);
  font-size: 11px; font-weight: 700;
  letter-spacing: .02em;
  color: var(--muted-hi); font-family: var(--sans);
  transition: border-color var(--transition-fast), color var(--transition-fast), box-shadow var(--transition-fast);
  white-space: nowrap; flex-shrink: 0;
}
.tab-bar a.active,
.tab-bar a:hover { border-color: var(--accent); color: var(--accent); box-shadow: 0 0 8px var(--accent-glow); }

.per-page-bar {
  display: flex; align-items: center; gap: 6px;
  margin-bottom: 10px; font-size: 11px; color: var(--muted-hi);
  font-family: var(--sans);
}
.per-page-bar select {
  background: rgba(0,0,0,.2); border: 1px solid var(--glass-border-hi);
  color: var(--text); border-radius: var(--radius-sm);
  padding: 4px 8px; font-size: 11px; font-family: var(--sans);
  cursor: pointer;
}
[data-theme="bright"] .per-page-bar select { background: rgba(255,255,255,.7); }

.filter-bar {
  display: flex; gap: 6px; margin-bottom: 10px; flex-wrap: wrap; align-items: center;
}
.filter-bar input[type="text"],
.filter-bar input[type="date"] {
  background: var(--bg);
  border: 1px solid var(--glass-border);
  border-radius: var(--radius-sm);
  padding: 5px 9px;
  font-size: 11px; color: var(--text);
  font-family: var(--sans); outline: none;
  transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
}
.filter-bar input[type="text"] { width: 180px; }
.filter-bar input[type="date"] { width: 140px; }
.filter-bar input:focus { border-color: var(--accent); box-shadow: 0 0 0 2px var(--accent-glow); }

@media (max-width: 600px) {
  .filter-bar { flex-direction: column; align-items: stretch; }
  .filter-bar input[type="text"],
  .filter-bar input[type="date"] { width: 100%; }
  .filter-bar button { width: 100%; text-align: center; }
  .log-item { grid-template-columns: auto 1fr auto; padding: 7px 10px; }
  .log-time { font-size: 10px; }
  .log-action { font-size: 11px; }
  .log-meta { gap: 0 8px; }
  .pagination { gap: 3px; }
  .pagination a { padding: 4px 8px; font-size: 10px; }
}

.filter-bar button {
  background: var(--bg);
  border: 1px solid var(--glass-border); border-radius: var(--radius-sm);
  padding: 5px 12px;
  font-size: 11px; font-weight: 600;
  color: var(--muted-hi); font-family: var(--sans);
  cursor: pointer;
  transition: border-color var(--transition-fast), color var(--transition-fast), box-shadow var(--transition-fast);
}
.filter-bar button:hover { border-color: var(--accent); color: var(--accent); box-shadow: 0 0 8px var(--accent-glow); }
.filter-bar .btn-reset { color: var(--danger); border-color: var(--danger-bdr); text-decoration: none; padding: 5px 12px; border: 1px solid var(--danger-bdr); border-radius: var(--radius-sm); font-size: 11px; font-weight: 600; font-family: var(--sans); cursor: pointer; transition: border-color var(--transition-fast), box-shadow var(--transition-fast); }
.filter-bar .btn-reset:hover { border-color: var(--danger); box-shadow: 0 0 8px rgba(239,68,68,.2); }
.filter-active {
  font-size: 11px; color: var(--accent); font-family: var(--sans);
  padding: 5px 0;
}

.log-list {
  background: var(--glass-hi);
  backdrop-filter: blur(10px) saturate(140%);
  border: 1px solid var(--glass-border-hi);
  border-radius: var(--radius-sm);
  margin-bottom: 12px;
  overflow: hidden;
}
.log-num {
  font-size: 10px; color: var(--muted); font-family: var(--mono);
  white-space: nowrap; padding-top: 1px; flex-shrink: 0;
  min-width: 32px;
}
.log-item {
  display: grid;
  grid-template-columns: auto 1fr auto;
  grid-template-rows: auto auto;
  gap: 0 8px;
  padding: 8px 12px;
  border-bottom: 1px solid var(--glass-border);
  transition: background var(--transition-fast);
  align-items: start;
}
.log-item:last-child { border-bottom: none; }
.log-item:hover { background: rgba(74,144,217,.04); }
.log-item-top { display: contents; }
.log-action {
  font-size: 12px; font-weight: 600; color: var(--text);
  font-family: var(--sans); grid-column: 2; grid-row: 1;
}
.log-time {
  font-size: 10px; color: var(--muted-hi); white-space: nowrap;
  flex-shrink: 0; font-family: var(--sans);
  grid-column: 3; grid-row: 1; text-align: right; padding-top: 1px;
}
.log-meta {
  font-size: 11px; color: var(--muted-hi); line-height: 1.5;
  font-family: var(--sans); grid-column: 2 / -1; grid-row: 2;
  display: grid; grid-template-columns: auto 1fr;
  gap: 2px 8px; margin-top: 3px; align-items: center;
}
.log-meta-label {
  font-size: 10px; color: var(--muted); white-space: nowrap; font-family: var(--sans);
}
.log-meta-val { font-size: 11px; color: var(--muted-hi); }
.log-ip {
  font-family: var(--mono); font-size: 10px;
  background: var(--accent-bg); border: 1px solid var(--accent-bdr);
  border-radius: 3px; padding: 0 5px; color: var(--accent);
}
.log-empty {
  text-align: center; padding: 2rem;
  font-size: 12px; color: var(--muted-hi);
  font-family: var(--sans);
}

.pagination { display: flex; gap: 4px; justify-content: center; flex-wrap: wrap; }
.pagination a {
  padding: 4px 10px; border: 1px solid var(--glass-border);
  border-radius: 4px; font-size: 11px; color: var(--muted-hi);
  font-family: var(--sans);
  transition: border-color var(--transition-fast), color var(--transition-fast), box-shadow var(--transition-fast);
}
.pagination a.current { border-color: var(--accent); color: var(--accent); box-shadow: 0 0 6px var(--accent-glow); }
.pagination a:hover   { border-color: var(--accent); color: var(--accent); box-shadow: 0 0 6px var(--accent-glow); }
.pagination-sep { font-size: 11px; color: var(--muted); padding: 4px 2px; font-family: var(--sans); }
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
  <form method="GET" class="per-page-bar" id="per-page-form">
  <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
  <?php if ($search): ?><input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
  <?php if ($filter_ip): ?><input type="hidden" name="ip" value="<?= htmlspecialchars($filter_ip) ?>"><?php endif; ?>
  <?php if ($filter_date): ?><input type="hidden" name="date" value="<?= htmlspecialchars($filter_date) ?>"><?php endif; ?>
  <input type="hidden" name="page" value="1">
  <span>Tampilkan:</span>
  <select name="per" id="per-select">
    <?php foreach ([10,20,50,100] as $opt): ?>
    <option value="<?= $opt ?>" <?= $per===$opt?'selected':'' ?>><?= $opt ?> / hal</option>
    <?php endforeach; ?>
  </select>
</form>
<script nonce="<?= csp_nonce() ?>">
document.getElementById('per-select').addEventListener('change', function() {
  document.getElementById('per-page-form').submit();
});
</script>
<div class="filter-bar">
    <input type="text" name="search" placeholder="Cari aksi / nama / email..."
           value="<?= htmlspecialchars($search) ?>">
    <input type="text" name="ip" placeholder="Filter IP..."
           value="<?= htmlspecialchars($filter_ip) ?>">
    <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>">
    <button type="submit">Filter</button>
    <?php if ($search || $filter_ip || $filter_date): ?>
      <a href="?tab=<?= htmlspecialchars($tab) ?>" class="btn-reset">Reset</a>
    <?php endif; ?>
  </div>
</form>

<div class="log-list">
<?php if ($logs): ?>
<?php foreach ($logs as $log): ?>
<div class="log-item">
  <span class="log-num">#<?= $log['id'] ?></span>
  <?php if ($tab === 'user'): ?>
    <span class="log-action"><?= htmlspecialchars($log['activity']) ?></span>
  <?php else: ?>
    <span class="log-action"><?= htmlspecialchars($log['action']) ?></span>
  <?php endif; ?>
  <span class="log-time"><?= date('d M H:i', strtotime($log['created_at'])) ?></span>
  <div class="log-meta">
    <?php if ($tab === 'user'): ?>
      <span class="log-meta-label">User</span>
      <span class="log-meta-val"><?= htmlspecialchars($log['user_name']) ?></span>
      <span class="log-meta-label">IP</span>
      <span class="log-ip"><?= htmlspecialchars($log['ip_address']) ?></span>
    <?php else: ?>
      <span class="log-meta-label">Admin</span>
      <span class="log-meta-val"><?= htmlspecialchars($log['admin_name'] ?? '') ?></span>
      <?php if (!empty($log['target_user']) && $log['target_user'] !== '-'): ?>
      <span class="log-meta-label">Target</span>
      <span class="log-meta-val"><?= htmlspecialchars($log['target_user']) ?></span>
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
    <a href="?<?= $safe_qs ?>&page=<?= $page - 1 ?>">&lsaquo;</a>
  <?php endif; ?>
  <?php
  $start = max(1, $page - 2);
  $end   = min($pages, $page + 2);
  if ($start > 1) echo '<a href="?' . $safe_qs . '&page=1">1</a>';
  if ($start > 2) echo '<span class="pagination-sep">...</span>';
  for ($i = $start; $i <= $end; $i++):
  ?>
    <a href="?<?= $safe_qs ?>&page=<?= $i ?>" class="<?= $i === $page ? 'current' : '' ?>"><?= $i ?></a>
  <?php endfor; ?>
  <?php if ($end < $pages - 1) echo '<span class="pagination-sep">...</span>'; ?>
  <?php if ($end < $pages) echo '<a href="?' . $safe_qs . '&page=' . $pages . '">' . $pages . '</a>'; ?>
  <?php if ($page < $pages): ?>
    <a href="?<?= $safe_qs ?>&page=<?= $page + 1 ?>">&rsaquo;</a>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php layout_foot(); ?>