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

// Apps user punya akses -- dengan graceful degrade saat Supabase down
$apps          = [];
$fav_ids       = [];
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

    // Ambil daftar application_id yang difavoritkan user ini
    $fav_rows = $sb->select('user_favorites', ['user_id' => (string)$user['id']], 'application_id');
    $fav_ids  = array_flip(array_column($fav_rows, 'application_id'));

} catch (\Throwable $e) {
    // Supabase down -- graceful degrade
    function_exists('gudeg_log') ? gudeg_log('ERROR', 'dashboard.php', 'Supabase error: ' . $e->getMessage()) : error_log('dashboard.php Supabase error: ' . $e->getMessage());
    $supabase_down = true;

    // Coba ambil data user dari LocalCache
    $cached = LocalCache::getUser((string)$user['id']);
    if ($cached) {
        $user = array_merge($user, $cached);
    }

    // Apps tidak bisa ditampilkan tanpa Supabase -- biarkan $apps = []
}

layout_head('Dashboard', 'dashboard');
?>
<style nonce="<?= csp_nonce() ?>">
.banner-warn {
  background: rgba(234,179,8,.08);
  border: 1px solid rgba(234,179,8,.2);
  border-radius: var(--radius);
  padding: 12px 16px;
  margin-bottom: 1rem;
  font-size: 13px;
  color: #eab308;
  font-family: var(--sans);
  letter-spacing: .01em;
  display: flex; align-items: center; gap: 8px;
  box-shadow: 0 0 16px rgba(234,179,8,.06), var(--elev-1), var(--elev-inset);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  position: relative; overflow: hidden;
}
.banner-warn::before {
  content: '';
  position: absolute; top: 0; left: 0; right: 0; height: 1px;
  background: linear-gradient(90deg, transparent, rgba(234,179,8,.3), transparent);
}
.banner-warn i { font-size: 15px; flex-shrink: 0; }

.greeting, .page-header.greeting { margin-bottom: 20px; padding-bottom: 14px; border-bottom: 2px solid var(--accent-bdr); }
.greeting-name {
  font-size: 18px; font-weight: 700;
  letter-spacing: .01em;
  font-family: var(--sans);
  color: var(--text);
}
.greeting-sub {
  font-size: 13px; font-weight: 400; color: var(--muted-hi);
  margin-top: 4px; letter-spacing: .01em;
  font-family: var(--sans);
}

.guest-notice {
  background: var(--accent-bg);
  border: 1px solid var(--accent-bdr);
  border-radius: var(--radius);
  padding: 14px 16px;
  margin-bottom: 1rem;
  font-size: 14px; font-family: var(--sans);
  color: var(--muted-hi); line-height: 1.7;
  box-shadow: 0 0 16px var(--accent-glow-sm), var(--elev-1), var(--elev-inset);
  backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
  position: relative; overflow: hidden;
}
.guest-notice::before {
  content: '';
  position: absolute; top: 0; left: 0; right: 0; height: 1px;
  background: linear-gradient(90deg, transparent, var(--accent-bdr), transparent);
}
.guest-notice strong { color: var(--accent); }

.section-label { margin-bottom: 6px; }

.fav-btn {
  position: absolute; top: 8px; right: 8px;
  background: none; border: none; padding: 2px;
  cursor: pointer; line-height: 1;
  color: var(--muted); font-size: 16px;
  transition: color var(--transition-fast), transform var(--transition-fast);
  -webkit-tap-highlight-color: transparent;
  z-index: 1;
}
.fav-btn:hover  { color: #f59e0b; transform: scale(1.15); }
.fav-btn.active { color: #f59e0b; }

.section-fav { margin-bottom: 20px; }
.section-fav .section-label { color: #f59e0b; }

.apps-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  gap: 10px; margin-bottom: 20px;
}
@media (max-width: 480px) {
  .apps-grid { grid-template-columns: 1fr 1fr; }
  .app-card { padding: 10px 12px; }
}

.app-card {
  display: flex; flex-direction: row; align-items: flex-start; gap: 10px;
  background: var(--glass-hi);
  backdrop-filter: blur(20px) saturate(160%);
  -webkit-backdrop-filter: blur(20px) saturate(160%);
  border: 1px solid var(--glass-border-hi);
  border-radius: var(--radius);
  padding: 10px 32px 10px 12px;
  text-decoration: none; color: var(--text);
  transition: border-color var(--transition-fast), box-shadow var(--transition-fast), transform var(--transition-fast);
  -webkit-tap-highlight-color: transparent;
  box-shadow: var(--elev-2), var(--elev-inset);
  position: relative; overflow: hidden;
}
.app-card::before {
  content: '';
  position: absolute; top: 0; left: 0; right: 0; height: 1px;
  background: linear-gradient(90deg, transparent, var(--glass-border-hi), transparent);
}
.app-card:hover, .app-card:focus {
  border-color: var(--accent-bdr);
  box-shadow: 0 0 20px var(--accent-glow), var(--elev-3), var(--elev-inset);
  transform: translateY(-2px);
  outline: none;
}
.app-card:active { opacity: .85; transform: translateY(0); }

.app-logo {
  width: 32px; height: 32px; border-radius: 8px; flex-shrink: 0;
  background: var(--accent-bg); border: 1px solid var(--accent-bdr);
  display: flex; align-items: center; justify-content: center;
  font-size: 16px; overflow: hidden;
  box-shadow: 0 0 10px var(--accent-glow-sm), var(--elev-1);
}
.app-logo img { width: 100%; height: 100%; object-fit: cover; }

.app-info { flex: 1; min-width: 0; }
.app-name {
  font-weight: 700; font-size: 13px;
  font-family: var(--sans);
  margin-bottom: 3px; line-height: 1.3;
}
.app-desc {
  font-size: 12px; font-weight: 400; color: var(--muted-hi);
  font-family: var(--sans); line-height: 1.5;
}


.empty-apps {
  background: var(--glass-hi);
  backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
  border: 1px dashed var(--glass-border-hi);
  border-radius: var(--radius);
  padding: 2rem 1rem; text-align: center;
  color: var(--muted-hi); font-size: 14px;
  font-family: var(--sans); line-height: 1.7;
  margin-bottom: 16px; letter-spacing: .02em;
  box-shadow: var(--elev-1);
}

.bubble-wrap {
  position: fixed;
  bottom: calc(16px + env(safe-area-inset-bottom)); right: 16px;
  display: flex; flex-direction: column-reverse; gap: 8px;
  z-index: 9999; width: 320px; pointer-events: none;
}
@media (max-width: 480px) {
  .bubble-wrap { bottom: calc(12px + env(safe-area-inset-bottom)); right: 8px; left: 8px; width: auto; }
}
.bubble {
  border-radius: var(--radius); padding: 10px 32px 10px 14px;
  font-size: .8rem; line-height: 1.5; border: 1px solid;
  display: flex; align-items: flex-start; gap: 8px;
  pointer-events: all; animation: bubbleIn .25s ease;
  box-shadow: var(--elev-3); position: relative;
  backdrop-filter: blur(20px) saturate(160%); -webkit-backdrop-filter: blur(20px) saturate(160%);
}
@keyframes bubbleIn {
  from { opacity: 0; transform: translateY(10px); }
  to   { opacity: 1; transform: translateY(0); }
}
.bubble.danger { background: rgba(239,68,68,.12); border-color: rgba(239,68,68,.3); color: #fca5a5; }
.bubble i { font-size: 16px; flex-shrink: 0; margin-top: 1px; }
.bubble-body { flex: 1; min-width: 0; }
.bubble-title {
  font-weight: 700; letter-spacing: .02em; margin-bottom: 3px;
  font-family: var(--sans); font-size: 11px;
}
.bubble-desc { font-size: .78rem; font-weight: 500; opacity: .9; line-height: 1.6; }
.bubble-desc a { color: inherit; text-decoration: underline; }
.bubble-desc code {
  font-family: var(--mono); background: rgba(0,0,0,.3);
  padding: 1px 5px; border-radius: 3px; font-size: .72rem;
}
.bubble-close {
  position: absolute; top: 6px; right: 8px;
  cursor: pointer; opacity: .5; font-size: 16px;
  color: inherit; background: none; border: none; padding: 0; line-height: 1;
  -webkit-tap-highlight-color: transparent;
}
.bubble-close:hover { opacity: 1; }
.bubble-progress {
  position: absolute; bottom: 0; left: 0; height: 2px;
  background: currentColor; opacity: .35;
  border-radius: 0 0 var(--radius) var(--radius);
  animation: bubbleProgress 8s linear forwards;
}
@keyframes bubbleProgress { from { width: 100%; } to { width: 0%; } }
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
<?php
  $fav_apps   = array_filter($apps, fn($a) => isset($fav_ids[$a['id']]));
  $other_apps = $apps; // semua app tetap tampil di section bawah (favorit duplikasi)
?>

<?php if ($fav_apps): ?>
<div class="section-fav">
  <div class="section-label">&#9733; Favorit</div>
  <div class="apps-grid">
    <?php foreach ($fav_apps as $app): ?>
    <?php $app_id = htmlspecialchars($app['id']); ?>
    <a href="/sso.php?client_id=<?= urlencode($app['client_id']) ?>" class="app-card" target="_blank" rel="noopener noreferrer">
      <button class="fav-btn active" data-app-id="<?= $app_id ?>" aria-label="Hapus dari favorit" title="Hapus dari favorit"><i class="ti ti-star-filled"></i></button>
      <div class="app-logo">
        <?php if (!empty($app['logo'])): ?>
          <img src="/<?= htmlspecialchars($app['logo']) ?>" alt="<?= htmlspecialchars($app['name']) ?>">
        <?php else: ?>
          <i class="ti ti-device-desktop"></i>
        <?php endif; ?>
      </div>
      <div class="app-info">
        <div class="app-name"><?= htmlspecialchars($app['name']) ?></div>
        <div class="app-desc"><?= htmlspecialchars($app['description'] ?? 'Klik untuk akses') ?></div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

  <div class="section-label">Aplikasi Anda</div>
  <?php if ($apps): ?>
  <div class="apps-grid">
    <?php foreach ($other_apps as $app): ?>
    <?php $app_id = htmlspecialchars($app['id']); ?>
    <a href="/sso.php?client_id=<?= urlencode($app['client_id']) ?>" class="app-card" target="_blank" rel="noopener noreferrer">
      <button class="fav-btn" data-app-id="<?= $app_id ?>" aria-label="Tambah ke favorit" title="Tambah ke favorit"><i class="ti ti-star"></i></button>
      <div class="app-logo">
        <?php if (!empty($app['logo'])): ?>
          <img src="/<?= htmlspecialchars($app['logo']) ?>" alt="<?= htmlspecialchars($app['name']) ?>">
        <?php else: ?>
          <i class="ti ti-device-desktop"></i>
        <?php endif; ?>
      </div>
      <div class="app-info">
        <div class="app-name"><?= htmlspecialchars($app['name']) ?></div>
        <div class="app-desc"><?= htmlspecialchars($app['description'] ?? 'Klik untuk akses') ?></div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="empty-apps">Belum ada aplikasi yang ditugaskan ke akun Anda.<br>Hubungi administrator.</div>
  <?php endif; ?>
<?php endif; ?>

<script nonce="<?= csp_nonce() ?>">
(function () {
  var CSRF = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.fav-btn');
    if (!btn) return;

    // Cegah link card terbuka
    e.preventDefault();
    e.stopPropagation();

    var appId    = btn.dataset.appId;
    var isFav    = btn.classList.contains('active');
    var icon     = btn.querySelector('i');

    // Optimistic UI — langsung update tampilan
    if (isFav) {
      btn.classList.remove('active');
      icon.className = 'ti ti-star';
      btn.title       = 'Tambah ke favorit';
      btn.setAttribute('aria-label', 'Tambah ke favorit');
      // Pindahkan card ke grid "Aplikasi Anda"
      moveCard(btn.closest('.app-card'), false);
    } else {
      btn.classList.add('active');
      icon.className = 'ti ti-star-filled';
      btn.title       = 'Hapus dari favorit';
      btn.setAttribute('aria-label', 'Hapus dari favorit');
      moveCard(btn.closest('.app-card'), true);
    }

    // Kirim ke server
    fetch('/api/favorite.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ application_id: appId, csrf_token: CSRF })
    })
    .then(function (r) { return r.json(); })
    .catch(function () {
      // Rollback kalau gagal
      if (isFav) {
        btn.classList.add('active');
        icon.className = 'ti ti-star-filled';
        moveCard(btn.closest('.app-card'), true);
      } else {
        btn.classList.remove('active');
        icon.className = 'ti ti-star';
        moveCard(btn.closest('.app-card'), false);
      }
    });
  });

  function moveCard(card, toFav) {
    if (!card) return;

    if (toFav) {
      // Pastikan section favorit ada, kalau belum buat
      var favSection = document.querySelector('.section-fav');
      if (!favSection) {
        favSection = document.createElement('div');
        favSection.className = 'section-fav';
        favSection.innerHTML = '<div class="section-label">&#9733; Favorit</div><div class="apps-grid"></div>';
        var appSection = document.querySelector('.section-label:not(.section-fav .section-label)');
        if (appSection) appSection.parentNode.insertBefore(favSection, appSection);
      }
      var favGrid = favSection.querySelector('.apps-grid');
      if (favGrid) {
        // Clone card — original tetap di grid bawah
        var clone = card.cloneNode(true);
        clone.dataset.favClone = '1';
        favGrid.appendChild(clone);
      }
    } else {
      // Unfav: hapus clone dari section favorit, card asli di bawah tetap
      var favSection = document.querySelector('.section-fav');
      if (!favSection) return;
      var favGrid = favSection.querySelector('.apps-grid');
      // Cari clone berdasarkan app-id
      var appId  = card.querySelector('.fav-btn') ? card.querySelector('.fav-btn').dataset.appId : null;
      if (appId && favGrid) {
        var clones = favGrid.querySelectorAll('[data-fav-clone="1"]');
        clones.forEach(function(c) {
          var b = c.querySelector('.fav-btn');
          if (b && b.dataset.appId === appId) c.remove();
        });
      }
      // Hapus section favorit kalau sudah kosong
      if (favGrid && favGrid.children.length === 0) favSection.remove();
    }
  }
})();
</script>

<!-- Floating bubble container -->
<div class="bubble-wrap" id="bubbleWrap"></div>

<?php if (!empty($_SESSION['new_ip_warning'])): ?>
<script nonce="<?= csp_nonce() ?>">
(function() {
  var ip = <?= json_encode(htmlspecialchars($_SESSION['new_ip_warning'])) ?>;
  var wrap = document.getElementById('bubbleWrap');
  var b = document.createElement('div');
  b.className = 'bubble danger';
  b.innerHTML =
    '<i class="ti ti-alert-triangle" aria-hidden="true"></i>' +
    '<div class="bubble-body">' +
      '<div class="bubble-title">Masuk dari Perangkat Baru</div>' +
      '<div class="bubble-desc">IP baru terdeteksi: <code>' + ip + '</code>. ' +
        'Bukan Anda? Segera <a href="/change-password.php">ubah kata sandi</a> dan hubungi administrator.</div>' +
    '</div>' +
    '<button class="bubble-close" aria-label="Tutup" data-action="dismiss-ip">&#215;</button>' +
    '<div class="bubble-progress"></div>';
  wrap.appendChild(b);

  b.querySelector('[data-action="dismiss-ip"]').addEventListener('click', function() {
    dismissIPWarning(this);
  });

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
