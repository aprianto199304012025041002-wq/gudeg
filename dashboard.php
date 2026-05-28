<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';
require_login();

$user = get_current_user_data();
$db   = getDB();

// Dismiss IP warning banner
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['dismiss_ip_warning'])) {
    csrf_verify();
    unset($_SESSION['new_ip_warning']);
    header("Location: /dashboard.php");
    exit;
}

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

layout_head('Beranda', 'dashboard');
?>
<style>
.greeting { margin-bottom: 1rem; }

.greeting-name {
  font-size: 1.1rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .04em;
  font-family: var(--sans);
}

.greeting-sub {
  font-size: .85rem;
  color: var(--text-dim);
  margin-top: .2rem;
  text-transform: uppercase;
  letter-spacing: .04em;
  font-family: var(--sans);
}

.guest-notice {
  background: rgba(122,162,212,.06);
  border: 1px solid rgba(122,162,212,.2);
  border-radius: 4px;
  padding: .85rem 1rem;
  margin-bottom: 1rem;
  font-size: .85rem;
  font-family: var(--sans);
  color: var(--text-dim);
  line-height: 1.6;
}
.guest-notice strong { color: var(--accent); }

.apps-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
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
  font-weight: 600;
  font-size: .85rem;
  font-family: var(--sans);
  text-transform: uppercase;
  letter-spacing: .03em;
  margin-bottom: .2rem;
  line-height: 1.3;
}

.app-desc {
  font-size: .78rem;
  color: var(--text-dim);
  font-family: var(--sans);
  line-height: 1.4;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.app-launch {
  margin-top: .5rem;
  font-size: .75rem;
  font-family: var(--sans);
  font-weight: 600;
  color: var(--accent);
  text-transform: uppercase;
  letter-spacing: .06em;
}

.empty-apps {
  background: var(--surface);
  border: 1px dashed var(--border);
  border-radius: 4px;
  padding: 1.25rem 1rem;
  text-align: center;
  color: var(--text-dim);
  font-size: .85rem;
  font-family: var(--sans);
  line-height: 1.6;
  margin-bottom: 1.2rem;
}

</style>

<?php if (!empty($_SESSION['new_ip_warning'])): ?>
<div style="
  background:rgba(192,64,64,.08);
  border:1px solid rgba(192,64,64,.25);
  border-radius:4px;
  padding:.75rem 1rem;
  margin-bottom:1rem;
  font-size:.82rem;
  font-family:var(--sans);
  color:var(--danger);
  display:flex;
  align-items:flex-start;
  gap:.6rem;
">
  <i class="ti ti-alert-triangle" aria-hidden="true" style="font-size:16px;flex-shrink:0;margin-top:1px"></i>
  <div>
    <strong style="text-transform:uppercase;letter-spacing:.03em;display:block;margin-bottom:2px">Login dari Perangkat Baru</strong>
    Kami mendeteksi login dari IP baru: <code style="font-family:var(--mono);background:rgba(0,0,0,.2);padding:1px 6px;border-radius:3px"><?= htmlspecialchars($_SESSION['new_ip_warning']) ?></code>.
    Jika ini bukan Anda, segera <a href="/change-password.php" style="color:var(--danger);text-decoration:underline">ubah password</a> dan hubungi administrator.
  </div>
  <form method="POST" action="/dashboard.php" style="margin:0;flex-shrink:0">
    <?= csrf_field() ?>
    <input type="hidden" name="dismiss_ip_warning" value="1">
    <button type="submit" style="background:none;border:none;color:var(--danger);cursor:pointer;font-size:18px;line-height:1;opacity:.6;padding:0" title="Tutup">&#215;</button>
  </form>
</div>
<?php endif; ?>

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
    <a href="/sso.php?client_id=<?= urlencode($app['client_id']) ?>" class="app-card" target="_blank" rel="noopener noreferrer">
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

<?php layout_foot(); ?>