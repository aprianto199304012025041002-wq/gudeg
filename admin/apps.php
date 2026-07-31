<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();

$user = get_current_user_data();
$sb   = getSupabase();

$bubble_type  = '';
$bubble_title = '';
$bubble_desc  = '';
$bubble_code  = '';
$bubble_warn  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_app') {
        $name      = trim($_POST['name'] ?? '');
        $redirect  = trim($_POST['redirect'] ?? '');
        $desc      = trim($_POST['description'] ?? '');
        $signed    = isset($_POST['use_signed_token']) ? 1 : 0;
        if ($name && $redirect) {
            $cid       = bin2hex(random_bytes(8));
            $sec_plain = bin2hex(random_bytes(32));
            $sec_hash  = hash_client_secret($sec_plain);
            $now = date('Y-m-d H:i:s');
            $sb->insert('sso_applications', [
                'name'             => $name,
                'redirect'         => $redirect,
                'client_id'        => $cid,
                'client_secret'    => $sec_hash,
                'description'      => $desc,
                'use_signed_token' => (bool)$signed,
                'is_active'        => true,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
            log_admin_action($user['id'], $user['name'],
                'Registrasi Aplikasi Baru' . ($signed ? ' (Signed Token)' : ''), $name);
            $bubble_type  = 'success';
            $bubble_title = 'Aplikasi Didaftarkan';
            $bubble_desc  = htmlspecialchars($name) . ' berhasil didaftarkan. Client ID: <code>' . htmlspecialchars($cid) . '</code>';
            $bubble_code  = $sec_plain;
            $bubble_warn  = 'Simpan secret ini sekarang — tidak akan ditampilkan lagi!';
        }
    } elseif ($action === 'regenerate_secret') {
        $id  = (int)$_POST['app_id'];
        $app = $sb->selectOne('sso_applications', ['id' => $id], 'id,name');
        if ($app) {
            $new_plain = bin2hex(random_bytes(32));
            $new_hash  = hash_client_secret($new_plain);
            $sb->update('sso_applications', ['id' => $id], [
                'client_secret' => $new_hash,
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
            log_admin_action($user['id'], $user['name'], 'Regenerate Client Secret', $app['name']);
            $bubble_type  = 'info';
            $bubble_title = 'Secret Diperbarui';
            $bubble_desc  = 'Secret baru untuk <strong>' . htmlspecialchars($app['name']) . '</strong>. Update nilai ini di GitHub Secrets repo aplikasi klien.';
            $bubble_code  = $new_plain;
            $bubble_warn  = 'Simpan secret baru ini sekarang — tidak akan ditampilkan lagi!';
        }
    } elseif ($action === 'toggle_status') {
        $id  = (int)$_POST['app_id'];
        $app = $sb->selectOne('sso_applications', ['id' => $id]);
        if ($app) {
            $new = !$app['is_active'];
            $sb->update('sso_applications', ['id' => $id], ['is_active' => $new]);
            log_admin_action($user['id'], $user['name'], 'Update Status Aplikasi', $app['name'] . ' (' . ($new ? 'Aktif' : 'Deaktif') . ')');
            $bubble_type  = 'success';
            $bubble_title = 'Status Diperbarui';
            $bubble_desc  = htmlspecialchars($app['name']) . ' ' . ($new ? 'diaktifkan' : 'dinonaktifkan') . '.';
        }
    } elseif ($action === 'toggle_signed') {
        $id  = (int)$_POST['app_id'];
        $app = $sb->selectOne('sso_applications', ['id' => $id]);
        if ($app) {
            $new = !$app['use_signed_token'];
            $sb->update('sso_applications', ['id' => $id], ['use_signed_token' => $new]);
            log_admin_action($user['id'], $user['name'], 'Toggle Signed Token: ' . ($new ? 'ON' : 'OFF'), $app['name']);
            $bubble_type  = 'info';
            $bubble_title = 'Token Mode';
            $bubble_desc  = htmlspecialchars($app['name']) . ' diubah ke ' . ($new ? 'RSA' : 'HMAC') . '.';
        }
    } elseif ($action === 'delete_app') {
        $id  = (int)$_POST['app_id'];
        $app = $sb->selectOne('sso_applications', ['id' => $id], 'name');
        if ($app) {
            $sb->delete('sso_applications', ['id' => $id]);
            $sb->delete('user_applications', ['application_id' => $id]);
            log_admin_action($user['id'], $user['name'], 'Hapus Aplikasi', $app['name']);
            $bubble_type  = 'danger';
            $bubble_title = 'Aplikasi Dihapus';
            $bubble_desc  = htmlspecialchars($app['name']) . ' telah dihapus permanen.';
        }
    }
}

$apps = $sb->select('sso_applications', [], '*', ['order' => 'id.desc']);

layout_head('Kelola Aplikasi', 'admin-apps');
?>
<style>
.add-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 4px;
  padding: .9rem;
  margin-bottom: 1.2rem;
}

.add-card-title {
  font-size: .85rem;
  font-weight: 600;
  font-family: var(--sans);
  color: var(--accent);
  text-transform: uppercase;
  letter-spacing: .06em;
  margin-bottom: .8rem;
}

.fg { margin-bottom: .7rem; }

.fg label {
  display: block;
  font-size: .72rem;
  font-weight: 600;
  color: var(--text-dim);
  text-transform: uppercase;
  letter-spacing: .07em;
  margin-bottom: .35rem;
  font-family: var(--sans);
}

.fg input[type=text] {
  width: 100%;
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 2px;
  padding: .6rem .75rem;
  color: var(--text);
  font-family: var(--sans);
  font-size: .9rem;
  outline: none;
  transition: border-color .2s;
  -webkit-appearance: none;
  -webkit-box-shadow: 0 0 0 1000px var(--bg) inset;
  -webkit-text-fill-color: var(--text);
}
.fg input[type=text]:focus { border-color: var(--accent); }

.check-label {
  display: flex;
  align-items: flex-start;
  gap: .5rem;
  font-size: .8rem;
  font-weight: 500;
  font-family: var(--sans);
  color: var(--text-dim);
  text-transform: uppercase;
  letter-spacing: .03em;
  cursor: pointer;
  margin-top: .3rem;
  line-height: 1.5;
}
.check-label input { accent-color: var(--accent); flex-shrink: 0; margin-top: .2rem; }

.btn-add {
  background: rgba(122,162,212,.1);
  border: 1px solid var(--accent);
  color: var(--accent);
  border-radius: 2px;
  padding: .6rem 1rem;
  font-size: .82rem;
  font-weight: 600;
  cursor: pointer;
  font-family: var(--sans);
  text-transform: uppercase;
  letter-spacing: .05em;
  margin-top: .75rem;
  width: 100%;
  transition: background .2s;
}
.btn-add:hover { background: rgba(122,162,212,.18); }

.app-list { display: flex; flex-direction: column; gap: .6rem; }

.app-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 4px;
  overflow: hidden;
}

.app-card-head { padding: .85rem 1rem; }

.app-name {
  font-weight: 600;
  font-size: .9rem;
  font-family: var(--sans);
  text-transform: uppercase;
  letter-spacing: .03em;
  margin-bottom: .2rem;
  line-height: 1.4;
}

.app-desc {
  font-size: .8rem;
  color: var(--text-dim);
  font-family: var(--sans);
  margin-bottom: .5rem;
  line-height: 1.4;
}

.app-badges { display: flex; gap: .35rem; flex-wrap: wrap; }

.mono-block {
  font-family: var(--mono);
  font-size: .78rem;
  color: var(--text-dim);
  word-break: break-all;
  line-height: 1.7;
  margin-top: .5rem;
}

.secret-blur {
  filter: blur(4px);
  transition: filter .2s;
  cursor: pointer;
  display: inline;
}
.secret-blur:hover,
.secret-blur.revealed { filter: none; }

.reveal-hint {
  font-size: .72rem;
  color: var(--accent);
  margin-left: .3rem;
  cursor: pointer;
}

.redirect-url {
  font-size: .75rem;
  color: var(--text-dim);
  opacity: .6;
  margin-top: .2rem;
}

.app-actions {
  border-top: 1px solid var(--border);
  padding: .6rem .9rem;
  display: flex;
  flex-wrap: wrap;
  gap: .4rem;
}

.btn-sm {
  background: var(--surface2);
  border: 1px solid var(--border);
  color: var(--text);
  border-radius: 2px;
  padding: .38rem .75rem;
  font-size: .8rem;
  font-weight: 500;
  cursor: pointer;
  font-family: var(--sans);
  text-transform: uppercase;
  letter-spacing: .04em;
  white-space: nowrap;
  transition: border-color .15s, color .15s;
  line-height: 1.2;
}
.btn-sm:hover { border-color: var(--accent); color: var(--accent); }
.btn-sm.danger { border-color: rgba(229,115,115,.2); color: var(--danger); }
.btn-sm.danger:hover { border-color: var(--danger); }
.btn-sm.warn { border-color: rgba(212,168,0,.25); color: #d4a800; }
.btn-sm.warn:hover { border-color: #d4a800; }

/* ── Confirm dialog ─────────────────────────────────────── */
.confirm-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.6);
  z-index: 200;
  align-items: center;
  justify-content: center;
}
.confirm-overlay.show { display: flex; }
.confirm-box {
  background: var(--surface);
  border: 1px solid var(--accent-bdr);
  border-radius: 6px;
  padding: 20px 22px;
  width: 320px;
  max-width: 90vw;
}
.confirm-title {
  font-size: .9rem;
  font-weight: 600;
  color: var(--text);
  text-transform: uppercase;
  letter-spacing: .03em;
  margin-bottom: 8px;
}
.confirm-msg {
  font-size: .85rem;
  color: var(--text-dim);
  line-height: 1.6;
  margin-bottom: 16px;
}
.confirm-msg strong { color: var(--text); }
.confirm-btns {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
}

/* ── Mobile optimizations ─────────────────────────────── */
@media (max-width: 600px) {
  .add-card { padding: .75rem; }
  .fg input[type=text] { font-size: .85rem; }
  .mono-block { font-size: .72rem; word-break: break-all; }
  .app-actions { gap: .3rem; padding: .5rem .75rem; }
  .btn-sm { padding: .35rem .6rem; font-size: .75rem; }
  .app-card-head { padding: .75rem; }
  .secret-blur { display: block; }
  .confirm-box { width: calc(100vw - 32px); padding: 16px; }
}

/* ── Floating bubble ─────────────────────────────────────── */
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
  .bubble-wrap { bottom: 12px; right: 8px; left: 8px; width: auto; }
}
.bubble {
  border-radius: 6px;
  padding: 10px 32px 10px 14px;
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
.bubble.success { background: rgba(58,138,96,.12);  border-color: rgba(58,138,96,.35);  color: #60c890; }
.bubble.danger  { background: rgba(192,64,64,.12);  border-color: rgba(192,64,64,.35);  color: #e08080; }
.bubble.info    { background: rgba(122,162,212,.12); border-color: rgba(122,162,212,.35); color: #7aa2d4; }
.bubble i { font-size: 16px; flex-shrink: 0; margin-top: 1px; }
.bubble-body { flex: 1; min-width: 0; }
.bubble-title { font-weight: 700; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 2px; font-size: .75rem; }
.bubble-desc  { font-size: .78rem; opacity: .9; line-height: 1.5; }
.bubble-desc code {
  font-family: var(--mono);
  background: rgba(0,0,0,.25);
  padding: 1px 5px;
  border-radius: 3px;
  font-size: .72rem;
  word-break: break-all;
}
.bubble-code {
  font-family: var(--mono);
  font-size: .78rem;
  background: rgba(0,0,0,.3);
  padding: 3px 8px;
  border-radius: 3px;
  display: block;
  margin-top: 5px;
  word-break: break-all;
  letter-spacing: .04em;
  color: inherit;
}
.bubble-warn {
  font-size: .72rem;
  margin-top: 4px;
  opacity: .8;
  font-weight: 600;
}
.bubble-close {
  position: absolute; top: 6px; right: 8px;
  cursor: pointer; opacity: .5; font-size: 16px;
  color: inherit; background: none; border: none; padding: 0; line-height: 1;
  -webkit-tap-highlight-color: transparent;
}
.bubble-close:hover { opacity: 1; }
.bubble-progress {
  position: absolute; bottom: 0; left: 0;
  height: 2px; background: currentColor; opacity: .3;
  border-radius: 0 0 6px 6px;
  animation: bubbleProgress 10s linear forwards;
}
@keyframes bubbleProgress { from { width: 100%; } to { width: 0%; } }

/* ── Safe area Android WebView ───────────────────────── */
@supports (padding-top: env(safe-area-inset-top)) {
  .bubble-wrap { bottom: calc(16px + env(safe-area-inset-bottom)); }
  @media (max-width: 480px) {
    .bubble-wrap { bottom: calc(12px + env(safe-area-inset-bottom)); right: 8px; left: 8px; width: auto; }
  }
}
</style>

<div class="page-header">
  <h1 class="page-title">Kelola Aplikasi</h1>
  <p class="page-subtitle">Daftarkan dan kelola aplikasi yang terintegrasi.</p>
</div>

<div class="add-card">
  <div class="add-card-title">+ Daftarkan Aplikasi Baru</div>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_app">
    <div class="fg">
      <label>Nama Aplikasi</label>
      <input type="text" name="name" placeholder="Nama aplikasi" required>
    </div>
    <div class="fg">
      <label>Redirect URL (Callback)</label>
      <input type="text" name="redirect" placeholder="https://app.example.com/callback" required>
    </div>
    <div class="fg">
      <label>Deskripsi (Opsional)</label>
      <input type="text" name="description" placeholder="Deskripsi singkat">
    </div>
    <label class="check-label">
      <input type="checkbox" name="use_signed_token">
      Gunakan RSA Signed Token (lebih aman, memerlukan public key di sisi klien)
    </label>
    <button type="submit" class="btn-add">Daftarkan Aplikasi</button>
  </form>
</div>

<div class="section-label">Aplikasi Terdaftar (<?= count($apps) ?>)</div>
<div class="app-list">
<?php foreach ($apps as $app): ?>
<div class="app-card">
  <div class="app-card-head">
    <div class="app-name"><?= htmlspecialchars($app['name']) ?></div>
    <?php if ($app['description']): ?>
      <div class="app-desc"><?= htmlspecialchars($app['description']) ?></div>
    <?php endif; ?>
    <div class="app-badges">
      <span class="badge <?= $app['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
        <?= $app['is_active'] ? 'Aktif' : 'Nonaktif' ?>
      </span>
      <span class="badge <?= $app['use_signed_token'] ? 'badge-rsa' : 'badge-hmac' ?>">
        <?= $app['use_signed_token'] ? 'RSA' : 'HMAC' ?>
      </span>
    </div>
    <div class="mono-block">
      <div>Client ID: <?= htmlspecialchars($app['client_id']) ?></div>
      <div>
        Client Secret (hash):
        <span class="secret-blur" onclick="this.classList.toggle('revealed')"
              title="Ketuk untuk tampilkan"><?= htmlspecialchars($app['client_secret'] ?? '—') ?></span>
        <span class="reveal-hint" onclick="this.previousElementSibling.classList.toggle('revealed')">[Ketuk untuk tampilkan]</span>
      </div>

      <div class="redirect-url">↳ <?= htmlspecialchars($app['redirect']) ?></div>
    </div>
  </div>
  <div class="app-actions">
    <form method="POST" style="display:inline">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="toggle_status">
      <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
      <button type="submit" class="btn-sm <?= $app['is_active'] ? 'danger' : '' ?>">
        <?= $app['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
      </button>
    </form>
    <form method="POST" style="display:inline">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="toggle_signed">
      <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
      <button type="submit" class="btn-sm">Toggle RSA</button>
    </form>
    <form method="POST" style="display:inline" id="form-regen-<?= $app['id'] ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="regenerate_secret">
      <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
      <button type="button" class="btn-sm warn"
              onclick="showConfirm(
                'Regenerate Client Secret',
                'Generate secret baru untuk <strong><?= htmlspecialchars(addslashes($app['name'])) ?></strong>? Secret lama akan langsung tidak berlaku. Kamu harus update GitHub Secrets di repo aplikasi klien.',
                'Ya, Regenerate',
                function(){ document.getElementById(\'form-regen-<?= $app['id'] ?>\').submit(); }
              )">
        🔑 Regenerate Secret
      </button>
    </form>
    <form method="POST" style="display:inline"
          onsubmit="return confirm('Hapus aplikasi <?= htmlspecialchars(addslashes($app['name'])) ?>?')">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete_app">
      <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
      <button type="submit" class="btn-sm danger">Hapus Aplikasi</button>
    </form>
  </div>
</div>
<?php endforeach; ?>
</div>

<!-- Confirm dialog -->
<div class="confirm-overlay" id="confirmOverlay">
  <div class="confirm-box">
    <div class="confirm-title" id="confirmTitle"></div>
    <div class="confirm-msg" id="confirmMsg"></div>
    <div class="confirm-btns">
      <button class="btn-sm" onclick="closeConfirm()">Batal</button>
      <button class="btn-sm warn" id="confirmOkBtn">Lanjutkan</button>
    </div>
  </div>
</div>

<!-- Floating bubble container -->
<div class="bubble-wrap" id="bubbleWrap"></div>

<script>
var _confirmCb = null;

function showConfirm(title, msg, btnLabel, cb) {
  document.getElementById('confirmTitle').textContent = title;
  document.getElementById('confirmMsg').innerHTML = msg;
  var ok = document.getElementById('confirmOkBtn');
  ok.textContent = btnLabel;
  _confirmCb = cb;
  document.getElementById('confirmOverlay').classList.add('show');
}

function closeConfirm() {
  document.getElementById('confirmOverlay').classList.remove('show');
  _confirmCb = null;
}

document.getElementById('confirmOkBtn').addEventListener('click', function() {
  var cb = _confirmCb;
  closeConfirm();
  if (cb) cb();
});

document.getElementById('confirmOverlay').addEventListener('click', function(e) {
  if (e.target === this) closeConfirm();
});

function showBubble(type, icon, title, desc, code, warn, duration) {
  duration = duration || 6000;
  var wrap = document.getElementById('bubbleWrap');
  var b = document.createElement('div');
  b.className = 'bubble ' + type;
  var extra = '';
  if (code) extra += '<span class="bubble-code">' + code + '</span>';
  if (warn) extra += '<div class="bubble-warn">⚠ ' + warn + '</div>';
  b.innerHTML =
    '<i class="ti ti-' + icon + '" aria-hidden="true"></i>' +
    '<div class="bubble-body"><div class="bubble-title">' + title + '</div>' +
    '<div class="bubble-desc">' + desc + extra + '</div></div>' +
    '<button class="bubble-close" aria-label="Tutup" onclick="closeBubble(this.parentNode)">&#215;</button>' +
    '<div class="bubble-progress" style="animation-duration:' + (duration/1000) + 's"></div>';
  wrap.appendChild(b);
  setTimeout(function() { closeBubble(b); }, duration);
}
function closeBubble(b) {
  if (!b || !b.parentNode) return;
  b.style.transition = 'opacity .3s, transform .3s';
  b.style.opacity = '0';
  b.style.transform = 'translateY(10px)';
  setTimeout(function() { if (b.parentNode) b.remove(); }, 320);
}

<?php if ($bubble_type): ?>
<?php
  $icon_map = ['success' => 'check', 'danger' => 'trash', 'info' => 'key'];
  $icon     = $icon_map[$bubble_type] ?? 'check';
  $duration = $bubble_code ? 15000 : 6000;
?>
showBubble(
  <?= json_encode($bubble_type) ?>,
  <?= json_encode($icon) ?>,
  <?= json_encode($bubble_title) ?>,
  <?= json_encode($bubble_desc) ?>,
  <?= json_encode($bubble_code) ?>,
  <?= json_encode($bubble_warn) ?>,
  <?= $duration ?>
);
<?php endif; ?>
</script>

<?php layout_foot(); ?>