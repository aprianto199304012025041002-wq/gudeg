<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';
require_login();

$user = get_current_user_data();
$db   = getDB();

// Apps user punya akses
$apps = [];
if ($user['role'] !== 'guest') {
    if ($user['role'] === 'admin') {
        $stmt = $db->prepare("SELECT * FROM sso_applications WHERE is_active = 1");
        $stmt->execute();
    } else {
        $stmt = $db->prepare("
            SELECT a.* FROM sso_applications a
            INNER JOIN user_applications ua ON ua.application_id = a.id
            WHERE ua.user_id = ? AND a.is_active = 1
        ");
        $stmt->execute([$user['id']]);
    }
    $apps = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Aktivitas terakhir
$logs = $db->prepare("SELECT activity, ip_address, created_at FROM user_logs WHERE user_id = ? ORDER BY id DESC LIMIT 5");
$logs->execute([$user['id']]);
$recent = $logs->fetchAll(PDO::FETCH_ASSOC);

layout_head('Dashboard', 'dashboard');
?>
<style>
.greeting { margin-bottom: 1rem; }

.greeting-name {
  font-size: .95rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .05em;
  font-family: var(--mono);
}

.greeting-sub {
  font-size: .7rem;
  color: var(--text-dim);
  margin-top: .2rem;
  text-transform: uppercase;
  letter-spacing: .05em;
  font-family: var(--mono);
}

.guest-notice {
  background: rgba(122,162,212,.06);
  border: 1px solid rgba(122,162,212,.2);
  border-radius: 4px;
  padding: .75rem .9rem;
  margin-bottom: .9rem;
  font-size: .72rem;
  font-weight: 700;
  font-family: var(--mono);
  color: var(--text-dim);
  line-height: 1.6;
}
.guest-notice strong { color: var(--accent); }

.apps-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: .5rem;
  margin-bottom: 1.2rem;
}

.app-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 4px;
  padding: .75rem .8rem;
  display: block;
  text-decoration: none;
  color: var(--text);
  transition: border-color .15s;
  -webkit-tap-highlight-color: transparent;
}
.app-card:hover,
.app-card:focus { border-color: var(--accent); outline: none; }
.app-card:active { opacity: .85; }

.app-logo {
  width: 28px;
  height: 28px;
  border-radius: 3px;
  background: var(--surface2);
  border: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: .9rem;
  margin-bottom: .6rem;
  overflow: hidden;
}
.app-logo img { width: 100%; height: 100%; object-fit: cover; }

.app-name {
  font-weight: 700;
  font-size: .75rem;
  font-family: var(--mono);
  text-transform: uppercase;
  letter-spacing: .04em;
  margin-bottom: .2rem;
  line-height: 1.3;
}

.app-desc {
  font-size: .68rem;
  color: var(--text-dim);
  font-family: var(--mono);
  line-height: 1.4;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.app-launch {
  margin-top: .5rem;
  font-size: .65rem;
  font-family: var(--mono);
  font-weight: 700;
  color: var(--accent);
  text-transform: uppercase;
  letter-spacing: .08em;
}

.empty-apps {
  background: var(--surface);
  border: 1px dashed var(--border);
  border-radius: 4px;
  padding: 1.25rem 1rem;
  text-align: center;
  color: var(--text-dim);
  font-size: .72rem;
  font-weight: 700;
  font-family: var(--mono);
  line-height: 1.6;
  margin-bottom: 1.2rem;
}

.log-list {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 4px;
  overflow: hidden;
  margin-bottom: 1rem;
}

.log-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: .65rem .9rem;
  border-bottom: 1px solid var(--border);
  gap: .6rem;
}
.log-row:last-child { border-bottom: none; }

.log-activity {
  font-size: .72rem;
  font-weight: 700;
  font-family: var(--mono);
  text-transform: uppercase;
  letter-spacing: .03em;
  line-height: 1.3;
}

.log-meta {
  color: var(--text-dim);
  font-family: var(--mono);
  font-size: .65rem;
  font-weight: 700;
  text-align: right;
  flex-shrink: 0;
  line-height: 1.6;
}
</style>

<div class="page-header greeting">
  <div class="greeting-name">Halo, <?= htmlspecialchars(explode(' ', $user['name'])[0]) ?>!</div>
  <div class="greeting-sub"><?= $user['role'] === 'guest' ? 'Akun Anda masih menunggu aktivasi admin.' : 'Pilih aplikasi yang ingin diakses.' ?></div>
</div>

<?php if ($user['role'] === 'guest'): ?>
<div class="guest-notice">
  Akun Anda berstatus <strong>Guest</strong> dan belum memiliki akses ke aplikasi. Tunggu administrator mengaktifkan dan mengatur hak akses Anda.
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
<div class="empty-apps" style="margin-bottom:1rem">Belum ada aktivitas tercatat.</div>
<?php endif; ?>

<?php layout_foot(); ?>