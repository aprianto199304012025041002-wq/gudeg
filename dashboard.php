<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$user = get_current_user_data();
$db = getDB();

// Get apps this user has access to (non-guest only)
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

// Recent activity (last 5)
$logs = $db->prepare("SELECT activity, ip_address, created_at FROM user_logs WHERE user_id = ? ORDER BY id DESC LIMIT 5");
$logs->execute([$user['id']]);
$recent = $logs->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — Portal SSO</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Mono:wght@400;500&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  :root {
    --bg: #0a0c10; --surface: #12151c; --surface2: #181c26;
    --border: #1e2330; --accent: #c8a96e; --accent-dim: #8a7249;
    --text: #e8e4dc; --text-dim: #7a7a8a;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: var(--bg); color: var(--text); font-family: 'DM Sans', sans-serif; min-height: 100vh; }
  body { background-image: radial-gradient(ellipse 80% 40% at 50% -10%, rgba(200,169,110,0.06), transparent); }

  /* Nav */
  nav { background: var(--surface); border-bottom: 1px solid var(--border); padding: 0 2rem; display: flex; align-items: center; justify-content: space-between; height: 56px; position: sticky; top: 0; z-index: 10; }
  .nav-brand { font-family: 'DM Serif Display', serif; font-size: 1.1rem; color: var(--accent); }
  .nav-brand span { color: var(--text-dim); font-family: 'DM Mono', monospace; font-size: 0.75rem; margin-left: 0.5rem; }
  .nav-right { display: flex; align-items: center; gap: 1rem; font-size: 0.85rem; }
  .nav-right .role-badge { background: var(--border); border-radius: 20px; padding: 0.25rem 0.7rem; font-family: 'DM Mono', monospace; font-size: 0.72rem; color: var(--accent); text-transform: uppercase; }
  .nav-right a { color: var(--text-dim); text-decoration: none; }
  .nav-right a:hover { color: var(--text); }
  .nav-right a.admin-link { color: var(--accent); }

  /* Main */
  main { max-width: 900px; margin: 0 auto; padding: 2.5rem 1.5rem; }
  .greeting { margin-bottom: 2.5rem; }
  .greeting h2 { font-family: 'DM Serif Display', serif; font-size: 2rem; }
  .greeting p { color: var(--text-dim); margin-top: 0.4rem; font-size: 0.9rem; }

  /* Guest notice */
  .guest-notice { background: rgba(200,169,110,0.08); border: 1px solid rgba(200,169,110,0.2); border-radius: 12px; padding: 1.2rem 1.5rem; margin-bottom: 2rem; display: flex; align-items: center; gap: 0.75rem; }
  .guest-notice .icon { font-size: 1.3rem; }
  .guest-notice p { font-size: 0.88rem; color: var(--text-dim); line-height: 1.5; }

  /* Section */
  .section-title { font-size: 0.75rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.1em; color: var(--text-dim); margin-bottom: 1rem; font-family: 'DM Mono', monospace; }

  /* Apps grid */
  .apps-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2.5rem; }
  .app-card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 1.2rem; text-decoration: none; color: var(--text); transition: border-color 0.2s, transform 0.1s; display: block; }
  .app-card:hover { border-color: var(--accent); transform: translateY(-2px); }
  .app-card .app-logo { width: 40px; height: 40px; border-radius: 10px; background: var(--border); overflow: hidden; margin-bottom: 0.8rem; }
  .app-card .app-logo img { width: 100%; height: 100%; object-fit: cover; }
  .app-card .app-name { font-weight: 500; font-size: 0.95rem; margin-bottom: 0.25rem; }
  .app-card .app-desc { font-size: 0.78rem; color: var(--text-dim); line-height: 1.4; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .app-card .launch-label { margin-top: 0.8rem; font-size: 0.72rem; font-family: 'DM Mono', monospace; color: var(--accent); text-transform: uppercase; letter-spacing: 0.08em; }

  /* Empty apps */
  .empty-apps { background: var(--surface); border: 1px dashed var(--border); border-radius: 12px; padding: 2rem; text-align: center; color: var(--text-dim); font-size: 0.88rem; margin-bottom: 2.5rem; }

  /* Recent logs */
  .log-table { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
  .log-row { display: flex; align-items: center; justify-content: space-between; padding: 0.8rem 1.2rem; border-bottom: 1px solid var(--border); font-size: 0.83rem; }
  .log-row:last-child { border-bottom: none; }
  .log-activity { color: var(--text); }
  .log-meta { color: var(--text-dim); font-family: 'DM Mono', monospace; font-size: 0.75rem; text-align: right; }

  /* Change password */
  .action-links { display: flex; gap: 0.75rem; margin-top: 2.5rem; flex-wrap: wrap; }
  .action-link { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.55rem 1rem; border-radius: 8px; font-size: 0.83rem; text-decoration: none; border: 1px solid var(--border); color: var(--text-dim); transition: border-color 0.2s, color 0.2s; }
  .action-link:hover { border-color: var(--accent); color: var(--accent); }
</style>
</head>
<body>
<nav>
  <div class="nav-brand">GUDEG SSO <span>Portal</span></div>
  <div class="nav-right">
    <span class="role-badge"><?= htmlspecialchars($user['role']) ?></span>
    <?php if ($user['role'] === 'admin'): ?>
      <a href="/admin/" class="admin-link">Admin Panel</a>
    <?php endif; ?>
    <a href="/logout.php">Keluar</a>
  </div>
</nav>

<main>
  <div class="greeting">
    <h2>Halo, <?= htmlspecialchars(explode(' ', $user['name'])[0]) ?>!</h2>
    <p>Selamat datang di Portal SSO. <?= $user['role'] === 'guest' ? 'Akun Anda masih menunggu aktivasi.' : 'Pilih aplikasi yang ingin diakses.' ?></p>
  </div>

  <?php if ($user['role'] === 'guest'): ?>
  <div class="guest-notice">
    <div class="icon">⏳</div>
    <p>Akun Anda berstatus <strong>Guest</strong> dan belum memiliki akses ke aplikasi. Tunggu administrator mengaktifkan dan mengatur hak akses Anda.</p>
  </div>
  <?php endif; ?>

  <?php if ($user['role'] !== 'guest'): ?>
  <div class="section-title">Aplikasi Anda</div>
  <?php if ($apps): ?>
  <div class="apps-grid">
    <?php foreach ($apps as $app): ?>
    <a href="/sso.php?client_id=<?= urlencode($app['client_id']) ?>" class="app-card">
      <div class="app-logo">
        <?php if ($app['logo']): ?>
          <img src="/<?= htmlspecialchars($app['logo']) ?>" alt="<?= htmlspecialchars($app['name']) ?>">
        <?php else: ?>
          <div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--text-dim);font-size:1.2rem;">🖥️</div>
        <?php endif; ?>
      </div>
      <div class="app-name"><?= htmlspecialchars($app['name']) ?></div>
      <div class="app-desc"><?= htmlspecialchars($app['description'] ?? 'Klik untuk akses') ?></div>
      <div class="launch-label">Buka →</div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="empty-apps">Belum ada aplikasi yang ditugaskan ke akun Anda. Hubungi administrator.</div>
  <?php endif; ?>
  <?php endif; ?>

  <div class="section-title">Aktivitas Terakhir</div>
  <?php if ($recent): ?>
  <div class="log-table">
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
  <?php endif; ?>

  <div class="action-links">
    <a href="/change-password.php" class="action-link">🔑 Ubah Password</a>
    <?php if ($user['role'] === 'admin'): ?>
      <a href="/admin/" class="action-link">⚙️ Panel Admin</a>
    <?php endif; ?>
  </div>
</main>
</body>
</html>
