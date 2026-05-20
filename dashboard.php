<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';
require_login();

$user = get_current_user_data();
$db   = getDB();

// Apps user punya akses
$apps = [];
if ($user['role'] !== 'guest') {
    $stmt = $db->prepare("
        SELECT a.* FROM sso_applications a
        INNER JOIN user_applications ua ON ua.application_id = a.id
        WHERE ua.user_id = ? AND a.is_active = 1
    ");
    $stmt->execute([$user['id']]);
    $apps = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Aktivitas terakhir
$logs = $db->prepare("SELECT activity, ip_address, created_at FROM user_logs WHERE user_id = ? ORDER BY id DESC LIMIT 5");
$logs->execute([$user['id']]);
$recent = $logs->fetchAll(PDO::FETCH_ASSOC);

layout_head('Dashboard', 'dashboard');
?>
<style>
.greeting { margin-bottom: 1.75rem; }
.greeting h2 {
  font-family: 'DM Serif Display', serif;
  font-size: 1.6rem;
}
.greeting p { color: var(--text-dim); margin-top: .3rem; font-size: .88rem; }

/* Guest notice */
.guest-notice {
  background: rgba(200,169,110,.07);
  border: 1px solid rgba(200,169,110,.2);
  border-radius: 12px;
  padding: 1rem 1.25rem;
  margin-bottom: 1.5rem;
  display: flex; align-items: flex-start; gap: .75rem;
}
.guest-notice .gn-icon { font-size: 1.2rem; margin-top: .1rem; }
.guest-notice p { font-size: .85rem; color: var(--text-dim); line-height: 1.55; }

/* Apps grid */
.apps-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  gap: .85rem;
  margin-bottom: 2rem;
}
@media (min-width: 480px) {
  .apps-grid { grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); }
}
.app-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 1.1rem 1rem;
  display: block;
  transition: border-color .2s, transform .15s;
  -webkit-tap-highlight-color: transparent;
}
.app-card:hover, .app-card:focus {
  border-color: var(--accent);
  transform: translateY(-2px);
  outline: none;
}
.app-card:active { transform: scale(.98); }
.app-logo {
  width: 38px; height: 38px;
  border-radius: 10px;
  background: var(--border);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.1rem; margin-bottom: .75rem;
  overflow: hidden;
}
.app-logo img { width: 100%; height: 100%; object-fit: cover; }
.app-name { font-weight: 500; font-size: .9rem; margin-bottom: .2rem; }
.app-desc {
  font-size: .75rem; color: var(--text-dim); line-height: 1.4;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
  overflow: hidden;
}
.app-launch {
  margin-top: .65rem;
  font-size: .68rem; font-family: 'DM Mono', monospace;
  color: var(--accent); text-transform: uppercase; letter-spacing: .08em;
}

/* Empty apps */
.empty-apps {
  background: var(--surface);
  border: 1px dashed var(--border);
  border-radius: 12px;
  padding: 2rem 1.5rem;
  text-align: center;
  color: var(--text-dim);
  font-size: .85rem;
  margin-bottom: 2rem;
}

/* Log list */
.log-list { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; margin-bottom: 1.5rem; }
.log-row {
  display: flex; align-items: center; justify-content: space-between;
  padding: .75rem 1.1rem;
  border-bottom: 1px solid var(--border);
  gap: .5rem;
}
.log-row:last-child { border-bottom: none; }
.log-activity { font-size: .85rem; }
.log-meta {
  color: var(--text-dim);
  font-family: 'DM Mono', monospace;
  font-size: .7rem;
  text-align: right; flex-shrink: 0;
}
</style>

<div class="page-header greeting">
  <h2>Halo, <?= htmlspecialchars(explode(' ', $user['name'])[0]) ?>!</h2>
  <p><?= $user['role'] === 'guest' ? 'Akun Anda masih menunggu aktivasi admin.' : 'Pilih aplikasi yang ingin diakses.' ?></p>
</div>

<?php if ($user['role'] === 'guest'): ?>
<div class="guest-notice">
  <div class="gn-icon">⏳</div>
  <p>Akun Anda berstatus <strong>Guest</strong> dan belum memiliki akses ke aplikasi. Tunggu administrator mengaktifkan dan mengatur hak akses Anda.</p>
</div>
<?php endif; ?>

<?php if ($user['role'] !== 'guest'): ?>
  <div class="section-label">Aplikasi Anda</div>
  <?php if ($apps): ?>
  <div class="apps-grid">
    <?php foreach ($apps as $app): ?>
    <a href="/sso.php?client_id=<?= urlencode($app['client_id']) ?>" class="app-card">
      <div class="app-logo">
        <?php if (!empty($app['logo'])): ?>
          <img src="/<?= htmlspecialchars($app['logo']) ?>" alt="<?= htmlspecialchars($app['name']) ?>">
        <?php else: ?>
          🖥️
        <?php endif; ?>
      </div>
      <div class="app-name"><?= htmlspecialchars($app['name']) ?></div>
      <div class="app-desc"><?= htmlspecialchars($app['description'] ?? 'Klik untuk akses') ?></div>
      <div class="app-launch">Buka →</div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="empty-apps">Belum ada aplikasi yang ditugaskan ke akun Anda.<br>Hubungi administrator.</div>
  <?php endif; ?>
<?php endif; ?>

<div class="section-label">Aktivitas Terakhir</div>
<?php if ($recent): ?>
<div class="log-list">
  <?php foreach ($recent as $log): ?>
  <div class="log-row">
    <span class="log-activity"><?= htmlspecialchars($log['activity']) ?></span>
    <span class="log-meta">
      <?= htmlspecialchars($log['ip_address']) ?><br>
      <?= date('d M, H:i', strtotime($log['created_at'])) ?>
    </span>
  </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
<div class="empty-apps" style="margin-bottom:1.5rem">Belum ada aktivitas tercatat.</div>
<?php endif; ?>

<?php layout_foot(); ?>