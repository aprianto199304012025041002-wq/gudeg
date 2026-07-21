<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';
require_login();

$user = get_current_user_data();
$sb   = getSupabase();

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
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: .5rem;
  margin-bottom: 1.2rem;
}
@media (max-width: 400px) {
  .apps-grid { grid-template-columns: 1fr 1fr; }
  .app-card { padding: .6rem .7rem; }
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

/* ── Floating bubble system ─────────────────────────────── */
.bubble-wrap {
  position: fixed;
  bottom: 16px;
  right: 16px;
  display: flex;
  flex-direction: column-reverse;
  gap: 8px;
  z-index: 9999;
  width: 320px;
  pointer-events: none;
}
@media (max-width: 480px) {
  .bubble-wrap {
    bottom: 12px;
    right: 8px;
    left: 8px;
    width: auto;
  }
}
.bubble {
  border-radius: 6px;
  padding: 10px 12px 10px 14px;
  font-size: .8rem;
  line-height: 1.5;
  border: 1px solid;
  display: flex;
  align-items: flex-start;
  gap: 8px;
  pointer-events: all;
  animation: bubbleIn .25s ease;
  box-shadow: 0 4px 20px rgba(0,0,0,.4);
  position: relative;
}
@keyframes bubbleIn {
  from { opacity: 0; transform: translateY(10px); }
  to   { opacity: 1; transform: translateY(0); }
}
.bubble.danger {
  background: rgba(192,64,64,.12);
  border-color: rgba(192,64,64,.35);
  color: #e08080;
}
.bubble i { font-size: 16px; flex-shrink: 0; margin-top: 1px; }
.bubble-body { flex: 1; min-width: 0; }
.bubble-title {
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .04em;
  margin-bottom: 3px;
  font-family: var(--sans);
  font-size: .78rem;
}
.bubble-desc {
  font-size: .78rem;
  opacity: .9;
  line-height: 1.6;
}
.bubble-desc a { color: inherit; text-decoration: underline; }
.bubble-desc code {
  font-family: var(--mono);
  background: rgba(0,0,0,.25);
  padding: 1px 5px;
  border-radius: 3px;
  font-size: .75rem;
}
.bubble-close {
  position: absolute;
  top: 6px;
  right: 8px;
  cursor: pointer;
  opacity: .5;
  font-size: 16px;
  color: inherit;
  background: none;
  border: none;
  padding: 0;
  line-height: 1;
  flex-shrink: 0;
  -webkit-tap-highlight-color: transparent;
}
.bubble-close:hover { opacity: 1; }
.bubble-progress {
  position: absolute;
  bottom: 0; left: 0;
  height: 2px;
  background: currentColor;
  opacity: .3;
  border-radius: 0 0 6px 6px;
  animation: bubbleProgress 8s linear forwards;
}
@keyframes bubbleProgress {
  from { width: 100%; }
  to   { width: 0%; }
}

/* ── Safe area Android WebView ───────────────────────── */
@supports (padding-top: env(safe-area-inset-top)) {
  .bubble-wrap { bottom: calc(16px + env(safe-area-inset-bottom)); }
  @media (max-width: 480px) {
    .bubble-wrap { bottom: calc(12px + env(safe-area-inset-bottom)); right: 8px; left: 8px; width: auto; }
  }
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

  // Auto-dismiss after 8s
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

  // Kirim dismiss ke server via fetch
  var fd = new FormData();
  fd.append('dismiss_ip_warning', '1');
  fd.append('csrf_token', <?= json_encode($_SESSION['csrf_token'] ?? '') ?>);
  fetch('/dashboard.php', { method: 'POST', body: fd, credentials: 'same-origin' });
}
</script>
<?php endif; ?>

<?php layout_foot(); ?>
<script>
// APK: Simpan kredensial ke EncryptedSharedPreferences setelah login berhasil
// Data sementara disimpan di sessionStorage oleh form login, lalu kita
// teruskan ke native bridge di sini dan langsung hapus dari sessionStorage.
(function () {
  try {
    var email    = sessionStorage.getItem('__gudeg_pending_email');
    var password = sessionStorage.getItem('__gudeg_pending_password');
    if (email && password && window.GudegNative && window.GudegNative.saveCredentials) {
      window.GudegNative.saveCredentials(email, password);
    }
  } catch (e) {}
  // Hapus dari sessionStorage apapun hasilnya — jangan simpan password di sini
  try {
    sessionStorage.removeItem('__gudeg_pending_email');
    sessionStorage.removeItem('__gudeg_pending_password');
  } catch (e) {}
})();
</script>