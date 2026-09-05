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

.section-fav { margin-bottom: 0; }
.section-fav .section-label { color: #f59e0b; }

.section-all { margin-top: 16px; }
.section-all-header {
  display: flex; align-items: center; justify-content: space-between;
  cursor: pointer; user-select: none;
  -webkit-tap-highlight-color: transparent;
}
.section-all-header .section-label { margin-bottom: 0; }
.section-all-toggle {
  width: 20px; height: 20px;
  display: flex; align-items: center; justify-content: center;
  color: var(--muted); font-size: 14px;
  transition: transform var(--transition-fast);
}
.section-all.collapsed .section-all-toggle { transform: rotate(-90deg); }
.section-all-body {
  overflow: hidden;
  transition: max-height 0.28s ease, opacity 0.2s ease;
  max-height: 2000px; opacity: 1;
}
.section-all.collapsed .section-all-body { max-height: 0; opacity: 0; }

.apps-grid {
  overflow: hidden;
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
  padding: 10px 36px 10px 12px;
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
    <a href="/sso.php?client_id=<?= urlencode($app['client_id']) ?>" class="app-card" data-fav-clone="1" target="_blank" rel="noopener noreferrer">
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

  <div class="section-all" id="section-all">
    <div class="section-all-header" id="section-all-header" role="button" aria-expanded="true" aria-controls="section-all-body">
      <div class="section-label">Aplikasi Anda</div>
      <div class="section-all-toggle"><i class="ti ti-chevron-down"></i></div>
    </div>
    <div class="section-all-body" id="section-all-body">
  <?php if ($apps): ?>
  <div class="apps-grid" style="margin-top:6px">
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
  <div class="empty-apps" style="margin-top:6px">Belum ada aplikasi yang ditugaskan ke akun Anda.<br>Hubungi administrator.</div>
  <?php endif; ?>
    </div><!-- /section-all-body -->
  </div><!-- /section-all -->
<?php endif; ?>

<script nonce="<?= csp_nonce() ?>">
(function () {
  // ── Collapsible "Aplikasi Anda" ──────────────────────────────────────────
  var sectionAll    = document.getElementById('section-all');
  var sectionHeader = document.getElementById('section-all-header');
  if (sectionAll && sectionHeader) {
    // Restore state dari localStorage — default: collapsed
    var stored = localStorage.getItem('apps_collapsed');
    if (stored === null || stored === '1') {
      sectionAll.classList.add('collapsed');
      sectionHeader.setAttribute('aria-expanded', 'false');
    }
    sectionHeader.addEventListener('click', function () {
      var collapsed = sectionAll.classList.toggle('collapsed');
      sectionHeader.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      localStorage.setItem('apps_collapsed', collapsed ? '1' : '0');
    });
  }
})();

(function () {
  var CSRF = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;

  // Helper: set state semua tombol bintang untuk appId tertentu
  function syncStars(appId, active) {
    document.querySelectorAll('.fav-btn[data-app-id="' + appId + '"]').forEach(function(b) {
      if (active) {
        b.classList.add('active');
        b.querySelector('i').className = 'ti ti-star-filled';
        b.title = 'Hapus dari favorit';
        b.setAttribute('aria-label', 'Hapus dari favorit');
      } else {
        b.classList.remove('active');
        b.querySelector('i').className = 'ti ti-star';
        b.title = 'Tambah ke favorit';
        b.setAttribute('aria-label', 'Tambah ke favorit');
      }
    });
  }

  // Helper: pastikan section favorit ada, return favGrid
  function ensureFavSection() {
    var favSection = document.querySelector('.section-fav');
    if (!favSection) {
      favSection = document.createElement('div');
      favSection.className = 'section-fav';
      favSection.innerHTML = '<div class="section-label">&#9733; Favorit</div><div class="apps-grid"></div>';
      var sectionAll = document.getElementById('section-all');
      if (sectionAll) sectionAll.parentNode.insertBefore(favSection, sectionAll);
    }
    return favSection.querySelector('.apps-grid');
  }

  // Helper: hapus clone dari section favorit by appId
  function removeFromFav(appId) {
    var favSection = document.querySelector('.section-fav');
    if (!favSection) return;
    var favGrid = favSection.querySelector('.apps-grid');
    if (favGrid) {
      favGrid.querySelectorAll('[data-fav-clone="1"]').forEach(function(c) {
        var b = c.querySelector('.fav-btn');
        if (b && b.dataset.appId === appId) c.remove();
      });
      if (favGrid.children.length === 0) favSection.remove();
    }
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.fav-btn');
    if (!btn) return;

    e.preventDefault();
    e.stopPropagation();

    var appId = btn.dataset.appId;
    var isFav = btn.classList.contains('active');

    if (isFav) {
      // UNFAV: sinkron semua bintang → mati, hapus clone dari favorit
      syncStars(appId, false);
      removeFromFav(appId);
    } else {
      // FAV: sinkron semua bintang → nyala, clone card asli ke favorit
      syncStars(appId, true);
      // Cari card asli di grid bawah (bukan clone)
      var otherGrid = document.querySelector('#section-all .apps-grid');
      if (otherGrid) {
        var original = null;
        otherGrid.querySelectorAll('.app-card').forEach(function(c) {
          var b = c.querySelector('.fav-btn');
          if (b && b.dataset.appId === appId) original = c;
        });
        if (original) {
          var favGrid = ensureFavSection();
          if (favGrid) {
            var clone = original.cloneNode(true);
            clone.dataset.favClone = '1';
            // Pastikan bintang di clone juga active
            var cloneBtn = clone.querySelector('.fav-btn');
            if (cloneBtn) {
              cloneBtn.classList.add('active');
              cloneBtn.querySelector('i').className = 'ti ti-star-filled';
              cloneBtn.title = 'Hapus dari favorit';
              cloneBtn.setAttribute('aria-label', 'Hapus dari favorit');
            }
            favGrid.appendChild(clone);
          }
        }
      }
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
        syncStars(appId, true);
        // Tambah balik clone ke favorit
        var otherGrid = document.querySelector('#section-all .apps-grid');
        if (otherGrid) {
          otherGrid.querySelectorAll('.app-card').forEach(function(c) {
            var b = c.querySelector('.fav-btn');
            if (b && b.dataset.appId === appId) {
              var favGrid = ensureFavSection();
              if (favGrid) {
                var clone = c.cloneNode(true);
                clone.dataset.favClone = '1';
                favGrid.appendChild(clone);
              }
            }
          });
        }
      } else {
        syncStars(appId, false);
        removeFromFav(appId);
      }
    });
  });
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
