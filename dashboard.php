<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/LocalCache.php';
require_once __DIR__ . '/includes/layout.php';
require_login();

$user = get_current_user_data();

// Dismiss IP warning banner
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['dismiss_ip_warning'])) {
    csrf_verify();
    unset($_SESSION['new_ip_warning']);
    header("Location: /dashboard.php");
    exit;
}

// Apps user punya akses — dengan graceful degrade saat Supabase down
$apps          = [];
$supabase_down = false;

try {
    $sb = getSupabase();

    if ($user['role'] !== 'guest') {
        if ($user['role'] === 'admin') {
            $apps = $sb->select('sso_applications', ['is_active' => true]);
        } else {
            // PostgREST tidak punya JOIN langsung kayak SQL, jadi 2 langkah:
            // 1) ambil daftar application_id yang jadi hak akses user ini
            $ua_rows = $sb->select('user_applications', ['user_id' => $user['id']], 'application_id');
            $app_ids = array_column($ua_rows, 'application_id');
            // 2) ambil detail aplikasinya yang masih aktif, filter id yang cocok pakai operator "in"
            if ($app_ids) {
                $apps = $sb->select('sso_applications', [
                    'id'        => 'in.(' . implode(',', $app_ids) . ')',
                    'is_active' => true,
                ]);
            }
        }
    }

    // Refresh LocalCache setiap kali berhasil ambil data dari Supabase
    LocalCache::setUser((string)$user['id'], $user);

} catch (\Throwable $e) {
    // Supabase down — graceful degrade
    function_exists('gudeg_log') ? gudeg_log('ERROR', 'dashboard.php', 'Supabase error: ' . $e->getMessage()) : error_log('dashboard.php Supabase error: ' . $e->getMessage());
    $supabase_down = true;

    // Coba ambil data user dari LocalCache
    $cached = LocalCache::getUser((string)$user['id']);
    if ($cached) {
        $user = array_merge($user, $cached);
    }

    // Apps tidak bisa ditampilkan tanpa Supabase — biarkan $apps = []
}

layout_head('Dashboard', 'dashboard');
?>
<style>
.banner-warn {
  background: rgba(234,179,8,.08);
  border: 1px solid rgba(234,179,8,.25);
  border-radius: 8px;
  padding: .75rem 1rem;
  margin-bottom: 1rem;
  font-size: .78rem;
  color: #eab308;
  font-family: var(--mono);
  letter-spacing: .03em;
  display: flex;
  align-items: center;
  gap: 8px;
  box-shadow: 0 0 12px rgba(234,179,8,.08);
}
.banner-warn i { font-size: 15px; flex-shrink: 0; }

.greeting { margin-bottom: 1rem; }

.greeting-name {
  font-size: 1rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .08em;
  font-family: var(--mono);
  background: linear-gradient(135deg, var(--accent), #7dd3fc);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.greeting-sub {
  font-size: .78rem;
  color: var(--muted-hi);
  margin-top: .25rem;
  text-transform: uppercase;
  letter-spacing: .06em;
  font-family: var(--mono);
}

.guest-notice {
  background: var(--accent-bg);
  border: 1px solid var(--accent-bdr);
  border-radius: 8px;
  padding: .85rem 1rem;
  margin-bottom: 1rem;
  font-size: .82rem;
  font-family: var(--mono);
  color: var(--muted-hi);
  line-height: 1.7;
}
.guest-notice strong { color: var(--accent); }

.apps-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: .5rem;
  margin-bottom: 1.2rem;
}
@media (max-width: 400px) {
  .apps-grid { grid-template-columns: 1fr 1fr; }
  .app-card { padding: .6rem .7rem; }
}

.app-card {
  background: var(--glass-hi);
  backdrop-filter: blur(12px) saturate(150%);
  border: 1px solid var(--border-hi);
  border-radius: 8px;
  padding: .75rem .8rem;
  display: block;
  text-decoration: none;
  color: var(--text);
  transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
  -webkit-tap-highlight-color: transparent;
}
.app-card:hover,
.app-card:focus {
  border-color: var(--accent);
  box-shadow: 0 0 16px var(--accent-glow);
  outline: none;
}
.app-card:active { opacity: .85; }

.app-logo {
  width: 28px;
  height: 28px;
  border-radius: 5px;
  background: var(--accent-bg);
  border: 1px solid var(--accent-bdr);
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
  font-size: .78rem;
  font-family: var(--mono);
  text-transform: uppercase;
  letter-spacing: .06em;
  margin-bottom: .2rem;
  line-height: 1.3;
}

.app-desc {
  font-size: .75rem;
  color: var(--muted-hi);
  font-family: var(--sans);
  line-height: 1.5;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.app-launch {
  margin-top: .5rem;
  font-size: .72rem;
  font-family: var(--mono);
  font-weight: 700;
  color: var(--accent);
  text-transform: uppercase;
  letter-spacing: .08em;
}

.empty-apps {
  background: var(--glass-hi);
  border: 1px dashed var(--border-hi);
  border-radius: 8px;
  padding: 1.5rem 1rem;
  text-align: center;
  color: var(--muted-hi);
  font-size: .82rem;
  font-family: var(--mono);
  line-height: 1.7;
  margin-bottom: 1.2rem;
  letter-spacing: .04em;
}

.bubble-wrap {
  position: fixed;
  bottom: calc(16px + env(safe-area-inset-bottom));
  right: 16px;
  display: flex;
  flex-direction: column-reverse;
  gap: 8px;
  z-index: 9999;
  width: 320px;
  pointer-events: none;
}
@media (max-width: 480px) {
  .bubble-wrap { bottom: calc(12px + env(safe-area-inset-bottom)); right: 8px; left: 8px; width: auto; }
}
.bubble {
  border-radius: 8px;
  padding: 10px 32px 10px 14px;
  font-size: .8rem;
  line-height: 1.5;
  border: 1px solid;
  display: flex;
  align-items: flex-start;
  gap: 8px;
  pointer-events: all;
  animation: bubbleIn .25s ease;
  box-shadow: 0 8px 32px rgba(0,0,0,.5);
  position: relative;
  backdrop-filter: blur(16px);
}
@keyframes bubbleIn {
  from { opacity: 0; transform: translateY(10px); }
  to   { opacity: 1; transform: translateY(0); }
}
.bubble.danger {
  background: rgba(239,68,68,.1);
  border-color: rgba(239,68,68,.3);
  color: #fca5a5;
}
.bubble i { font-size: 16px; flex-shrink: 0; margin-top: 1px; }
.bubble-body { flex: 1; min-width: 0; }
.bubble-title {
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .06em;
  margin-bottom: 3px;
  font-family: var(--mono);
  font-size: .72rem;
}
.bubble-desc {
  font-size: .78rem;
  opacity: .9;
  line-height: 1.6;
}
.bubble-desc a { color: inherit; text-decoration: underline; }
.bubble-desc code {
  font-family: var(--mono);
  background: rgba(0,0,0,.3);
  padding: 1px 5px;
  border-radius: 3px;
  font-size: .72rem;
}
.bubble-close {
  position: absolute;
  top: 6px; right: 8px;
  cursor: pointer; opacity: .5; font-size: 16px;
  color: inherit; background: none; border: none; padding: 0; line-height: 1;
  flex-shrink: 0;
  -webkit-tap-highlight-color: transparent;
}
.bubble-close:hover { opacity: 1; }
.bubble-progress {
  position: absolute;
  bottom: 0; left: 0;
  height: 2px;
  background: currentColor; opacity: .35;
  border-radius: 0 0 8px 8px;
  animation: bubbleProgress 8s linear forwards;
}
@keyframes bubbleProgress {
  from { width: 100%; }
  to   { width: 0%; }
}
</style>

<?php if ($supabase_down): ?>
<div class="banner-warn">
  <i class="ti ti-alert-triangle"></i>
  Layanan sedang gangguan. Beberapa fitur mungkin tidak tersedia. Data yang ditampilkan mungkin tidak terkini.
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

<!-- Floating bubble container -->
<div class="bubble-wrap" id="bubbleWrap"></div>

<?php if (!empty($_SESSION['new_ip_warning'])): ?>
<script>
(function() {
  var ip = <?= json_encode(htmlspecialchars($_SESSION['new_ip_warning'])) ?>;
  var wrap = document.getElementById('bubbleWrap');
  var b = document.createElement('div');
  b.className = 'bubble danger';
  b.innerHTML =
    '<i class="ti ti-alert-triangle" aria-hidden="true"></i>' +
    '<div class="bubble-body">' +
      '<div class="bubble-title">Login dari Perangkat Baru</div>' +
      '<div class="bubble-desc">IP baru terdeteksi: <code>' + ip + '</code>. ' +
        'Bukan Anda? Segera <a href="/change-password.php">ubah password</a> dan hubungi administrator.</div>' +
    '</div>' +
    '<button class="bubble-close" aria-label="Tutup" onclick="dismissIPWarning(this)">&#215;</button>' +
    '<div class="bubble-progress"></div>';
  wrap.appendChild(b);

  setTimeout(function() { dismissBubble(b); }, 8000);
})();

function dismissBubble(b) {
  b.style.transition = 'opacity .3s, transform .3s';
  b.style.opacity = '0';
  b.style.transform = 'translateY(10px)';
  setTimeout(function() { if (b.parentNode) b.remove(); }, 320);
}

function dismissIPWarning(btn) {
  var b = btn.closest('.bubble');
  dismissBubble(b);

  var fd = new FormData();
  fd.append('dismiss_ip_warning', '1');
  fd.append('csrf_token', <?= json_encode($_SESSION['csrf_token'] ?? '') ?>);
  fetch('/dashboard.php', { method: 'POST', body: fd, credentials: 'same-origin' });
}
</script>
<?php endif; ?>

<?php layout_foot(); ?>