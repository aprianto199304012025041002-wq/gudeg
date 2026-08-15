<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();

$user = get_current_user_data();
$sb   = getSupabase();

// ── Auto-cleanup ─────────────────────────────────────────────────────────────
// Cleanup token blacklist expired
try {
    $sb->delete('token_blacklist', ['exp' => 'lt.' . time()]);
} catch (\Throwable $e) {
    gudeg_log('WARNING', 'admin/logs.php', 'Gagal cleanup token_blacklist: ' . $e->getMessage());
}

// Cleanup log lama > 90 hari (probabilistik 5%, tidak perlu cron)
cleanup_old_logs();

$page = max(1, (int)($_GET['page'] ?? 1));
$per  = 50;
$off  = ($page - 1) * $per;
$tab  = ($_GET['tab'] ?? 'user') === 'admin' ? 'admin' : 'user';

// ── Filter params ─────────────────────────────────────────────────────────────
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

// ── Build filter dengan syntax PostgREST ────────────────────────────────────
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
]));
$safe_qs = htmlspecialchars($filter_qs);

layout_head('Admin · Log', 'admin-logs');
?>
<style nonce="<?= csp_nonce() ?>">
*, *::before, *::after { font-weight: inherit; }

.tab-bar { display: flex; gap: 5px; margin-bottom: 12px; overflow-x: auto; -webkit-overflow-scrolling: touch; flex-wrap: nowrap; }
.tab-bar a {
  padding: 5px 12px;
  border: 1px solid var(--border);
  border-radius: 5px;
  font-size: 11px; font-weight: 700;
  text-transform: uppercase; letter-spacing: .07em;
  color: var(--muted-hi); font-family: var(--mono);
  transition: border-color var(--transition-fast), color var(--transition-fast), box-shadow var(--transition-fast);
  white-space: nowrap; flex-shrink: 0;
}
.tab-bar a.active,
.tab-bar a:hover { border-color: var(--accent); color: var(--accent); box-shadow: 0 0 8px var(--accent-glow); }

.filter-bar {
  display: flex; gap: 6px; margin-bottom: 10px; flex-wrap: wrap; align-items: center;
}
.filter-bar input[type="text"],
.filter-bar input[type="date"] {
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 5px;
  padding: 5px 9px;
  font-size: 11px; color: var(--text);
  font-family: var(--mono); outline: none;
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
  .log-item-top { flex-direction: column; gap: 2px; }
  .log-time { font-size: 10px; }
  .log-action { font-size: 11px; }
  .log-meta span { display: block; margin-right: 0; }
  .pagination { gap: 3px; }
  .pagination a { padding: 4px 8px; font-size: 10px; }
}

.filter-bar button {
  background: var(--bg);
  border: 1px solid var(--border); border-radius: 5px;
  padding: 5px 12px;
  font-size: 11px; font-weight: 700;
  color: var(--muted-hi); font-family: var(--mono);
  text-transform: uppercase; letter-spacing: .06em;
  cursor: pointer;
  transition: border-color var(--transition-fast), color var(--transition-fast), box-shadow var(--transition-fast);
}
.filter-bar button:hover { border-color: var(--accent); color: var(--accent); box-shadow: 0 0 8px var(--accent-glow); }
.filter-bar .btn-reset { color: var(--danger); border-color: var(--danger-bdr); text-decoration: none; padding: 5px 12px; border: 1px solid var(--danger-bdr); border-radius: 5px; font-size: 11px; font-weight: 700; font-family: var(--mono); text-transform: uppercase; letter-spacing: .06em; cursor: pointer; transition: border-color var(--transition-fast), box-shadow var(--transition-fast); }
.filter-bar .btn-reset:hover { border-color: var(--danger); box-shadow: 0 0 8px rgba(239,68,68,.2); }
.filter-active {
  font-size: 10px; color: var(--accent); font-family: var(--mono);
  letter-spacing: .06em; text-transform: uppercase; padding: 5px 0;
}

.log-list { display: flex; flex-direction: column; gap: 5px; margin-bottom: 12px; }
.log-item {
  background: var(--glass-hi);
  backdrop-filter: blur(10px) saturate(140%);
  border: 1px solid var(--border-hi);
  border-radius: 6px; padding: 9px 12px;
  transition: border-color var(--transition-fast);
}
.log-item:hover { border-color: var(--accent-bdr); }
.log-item-top {
  display: flex; justify-content: space-between; align-items: flex-start;
  gap: 6px; margin-bottom: 4px;
}
.log-action { font-size: 12px; font-weight: 700; color: var(--text); font-family: var(--mono); text-transform: uppercase; letter-spacing: .04em; }
.log-time { font-size: 11px; color: var(--muted-hi); white-space: nowrap; flex-shrink: 0; font-family: var(--mono); }
.log-meta { font-size: 11px; color: var(--muted-hi); line-height: 1.6; font-family: var(--mono); }
.log-meta span { margin-right: 8px; }
.log-id { font-size: 10px; color: var(--muted); font-family: var(--mono); }
.log-ip {
  font-family: var(--mono); font-size: 10px;
  background: var(--accent-bg); border: 1px solid var(--accent-bdr);
  border-radius: 3px; padding: 0 5px; color: var(--accent);
}
.log-empty {
  text-align: center; padding: 2rem;
  font-size: 12px; color: var(--muted-hi);
  font-family: var(--mono); text-transform: uppercase; letter-spacing: .06em;
}

.pagination { display: flex; gap: 4px; justify-content: center; flex-wrap: wrap; }
.pagination a {
  padding: 4px 10px; border: 1px solid var(--border);
  border-radius: 4px; font-size: 11px; color: var(--muted-hi);
  font-family: var(--mono);
  transition: border-color var(--transition-fast), color var(--transition-fast), box-shadow var(--transition-fast);
}
.pagination a.current { border-color: var(--accent); color: var(--accent); box-shadow: 0 0 6px var(--accent-glow); }
.pagination a:hover   { border-color: var(--accent); color: var(--accent); box-shadow: 0 0 6px var(--accent-glow); }
.pagination-sep { font-size: 11px; color: var(--muted); padding: 4px 2px; font-family: var(--mono); }
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
      <a href="?tab=<?= htmlspecialchars($tab) ?>" class="btn-reset">Reset</a>
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
    <span class="log-time"><?= date('d M Y H:i:s', strtotime($log['created_at'])) ?></span>
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
    <a href="?<?= $safe_qs ?>&page=<?= $page - 1 ?>">‹</a>
  <?php endif; ?>
  <?php
  $start = max(1, $page - 2);
  $end   = min($pages, $page + 2);
  if ($start > 1) echo '<a href="?' . $safe_qs . '&page=1">1</a>';
  if ($start > 2) echo '<span class="pagination-sep">…</span>';
  for ($i = $start; $i <= $end; $i++):
  ?>
    <a href="?<?= $safe_qs ?>&page=<?= $i ?>" class="<?= $i === $page ? 'current' : '' ?>"><?= $i ?></a>
  <?php endfor; ?>
  <?php if ($end < $pages - 1) echo '<span class="pagination-sep">…</span>'; ?>
  <?php if ($end < $pages) echo '<a href="?' . $safe_qs . '&page=' . $pages . '">' . $pages . '</a>'; ?>
  <?php if ($page < $pages): ?>
    <a href="?<?= $safe_qs ?>&page=<?= $page + 1 ?>">›</a>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php layout_foot(); ?>