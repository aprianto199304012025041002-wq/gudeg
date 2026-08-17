<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();

$user = get_current_user_data();
$sb   = getSupabase();

$msg  = '';
$msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action    = $_POST['action'] ?? '';
    $target_id = (int)($_POST['target_id'] ?? 0);

    $target = $sb->selectOne('users', ['id' => $target_id]);

    if ($target && $target_id !== (int)$user['id']) {
        if ($action === 'toggle_active') {
            $new_status = !$target['is_active'];
            $sb->update('users', ['id' => $target_id], ['is_active' => $new_status]);
            $label = $new_status ? 'Status Akun Diaktifkan' : 'Status Akun Dinonaktifkan';
            log_admin_action($user['id'], $user['name'], $label, $target['name']);
            $msg = "Akun <strong>{$target['name']}</strong> berhasil " . ($new_status ? 'diaktifkan' : 'dinonaktifkan') . '.';
            $msg_type = 'success';
        } elseif ($action === 'change_role') {
            $new_role = $_POST['new_role'] ?? '';
            // Whitelist hanya 'user' dan 'guest' — sengaja tidak termasuk 'admin'.
            // Promosi ke admin harus dilakukan langsung di DB (Supabase dashboard)
            // oleh superadmin, bukan via panel ini, untuk mencegah privilege escalation
            // tidak sengaja oleh admin biasa.
            if (in_array($new_role, ['user', 'guest'])) {
                $sb->update('users', ['id' => $target_id], ['role' => $new_role]);
                log_admin_action($user['id'], $user['name'], "Ubah Role menjadi " . strtoupper($new_role), $target['name']);
                $msg = "Role <strong>{$target['name']}</strong> diubah ke $new_role.";
                $msg_type = 'success';
            }
        } elseif ($action === 'reset_password') {
            $new_pass = bin2hex(random_bytes(6));
            $hash = password_hash($new_pass, PASSWORD_BCRYPT);
            $sb->update('users', ['id' => $target_id], [
                'password'             => $hash,
                'password_changed_at'  => date('Y-m-d H:i:s'),
            ]);
            // Catat aksi reset tanpa menyertakan password plaintext di log
            log_admin_action($user['id'], $user['name'], 'Reset Password', $target['email']);
            try {
                $sb->delete('sessions', ['user_id' => $target_id]);
            } catch (\Throwable $e) {
                gudeg_log('WARNING', 'admin/index::reset_password', 'Gagal hapus sessions user ' . $target_id . ': ' . $e->getMessage());
            }
            $msg = "Password <strong>{$target['name']}</strong> direset ke: <code>$new_pass</code>";
            $msg_type = 'info';
        } elseif ($action === 'delete_user') {
            // Soft delete: set deleted_at + nonaktifkan akun
            // Konsisten dengan login.php & query user list yang filter deleted_at = is.null
            // Data relasi tetap dibersihkan karena tidak relevan setelah akun dihapus
            $sb->update('users', ['id' => $target_id], [
                'deleted_at' => date('Y-m-d H:i:s'),
                'is_active'  => false,
            ]);
            try {
                $sb->delete('sessions',          ['user_id' => $target_id]);
                $sb->delete('user_logs',         ['user_id' => $target_id]);
                $sb->delete('user_applications', ['user_id' => $target_id]);
            } catch (\Throwable $e) {
                gudeg_log('WARNING', 'admin/index::delete_user', 'Gagal hapus data relasi user ' . $target_id . ': ' . $e->getMessage());
            }
            log_admin_action($user['id'], $user['name'], 'Hapus User (Soft Delete)', $target['name'] . ' (' . $target['email'] . ')');
            $msg = "Akun <strong>{$target['name']}</strong> telah dinonaktifkan dan disembunyikan dari sistem.";
            $msg_type = 'danger';
        } elseif ($action === 'update_apps') {
            $app_ids = array_map('intval', $_POST['app_ids'] ?? []);
            try {
                $sb->delete('user_applications', ['user_id' => $target_id]);
                if (!empty($app_ids)) {
                    $rows = array_map(fn($aid) => ['user_id' => $target_id, 'application_id' => $aid], $app_ids);
                    $sb->insert('user_applications', $rows);
                }
            } catch (\Throwable $e) {
                gudeg_log('WARNING', 'admin/index::update_apps', 'Gagal update akses aplikasi user ' . $target_id . ': ' . $e->getMessage());
            }
            log_admin_action($user['id'], $user['name'], 'Update Akses Aplikasi SSO', $target['name']);
            $msg = "Akses aplikasi <strong>{$target['name']}</strong> diperbarui.";
            $msg_type = 'success';
        }
    }
}

$users    = $sb->select('users', ['deleted_at' => 'is.null'], '*', ['order' => 'id.asc']);
$all_apps = $sb->select('sso_applications', [], '*', ['order' => 'id.asc']);

$ua_map = [];
$user_ids = array_column($users, 'id');
if (!empty($user_ids)) {
    foreach ($sb->select('user_applications', ['user_id' => 'in.(' . implode(',', $user_ids) . ')']) as $r) {
        $ua_map[$r['user_id']][] = $r['application_id'];
    }
}

layout_head('Admin · User', 'admin-users');
?>
<style nonce="<?= csp_nonce() ?>">
.page-header { margin-bottom: 1rem; }
.form-inline { display: inline; }
.text-muted-sm { font-size: .72rem; color: var(--muted-hi); margin-bottom: .6rem; }

.user-list { display: flex; flex-direction: column; gap: .7rem; }

.user-card {
  background: var(--glass-hi);
  backdrop-filter: blur(20px) saturate(160%);
  -webkit-backdrop-filter: blur(20px) saturate(160%);
  border: 1px solid var(--glass-border-hi);
  border-radius: var(--radius);
  overflow: hidden;
  box-shadow: var(--elev-2), var(--elev-inset);
  transition: border-color var(--transition-fast), box-shadow var(--transition-fast), transform var(--transition-fast);
  position: relative;
}
.user-card::before {
  content: '';
  position: absolute; top: 0; left: 0; right: 0; height: 1px;
  background: linear-gradient(90deg, transparent, var(--glass-border-hi), transparent);
  pointer-events: none;
}
.user-card:hover {
  border-color: var(--accent-bdr);
  box-shadow: 0 0 20px var(--accent-glow-sm), var(--elev-3), var(--elev-inset);
  transform: translateY(-1px);
}

.user-card-head {
  display: flex; align-items: flex-start;
  justify-content: space-between;
  padding: .8rem .95rem; gap: .6rem;
}
.user-info { min-width: 0; flex: 1; }
.user-name {
  font-weight: 700; font-size: .85rem;
  text-transform: uppercase; letter-spacing: .06em; line-height: 1.3;
  font-family: var(--mono);
}
.user-email {
  font-size: .75rem; color: var(--muted-hi);
  margin-top: .2rem; word-break: break-all; line-height: 1.4;
  font-family: var(--mono);
}
.user-meta {
  display: flex; gap: .3rem; flex-shrink: 0;
  flex-wrap: wrap; justify-content: flex-end; max-width: 110px;
}
.user-actions {
  border-top: 1px solid var(--glass-border);
  padding: .65rem .95rem;
  display: flex; flex-wrap: wrap; gap: .4rem; align-items: center;
  background: rgba(0,0,0,.08);
}
[data-theme="bright"] .user-actions { background: rgba(0,0,0,.02); }

.btn-sm {
  background: rgba(255,255,255,.04);
  border: 1px solid var(--glass-border-hi);
  color: var(--muted-hi);
  border-radius: var(--radius-sm);
  padding: .35rem .7rem;
  font-size: .72rem; font-weight: 700;
  cursor: pointer; font-family: var(--mono);
  text-transform: uppercase; letter-spacing: .06em;
  white-space: nowrap;
  transition: border-color var(--transition-fast), color var(--transition-fast), box-shadow var(--transition-fast), background var(--transition-fast);
  line-height: 1.2;
  box-shadow: var(--elev-inset);
}
[data-theme="bright"] .btn-sm { background: rgba(0,0,0,.03); }
.btn-sm:hover { border-color: var(--accent-bdr); color: var(--accent); box-shadow: 0 0 10px var(--accent-glow-sm), var(--elev-inset); }
.btn-sm.danger { border-color: var(--danger-bdr); color: var(--danger); background: rgba(239,68,68,.04); }
.btn-sm.danger:hover { border-color: var(--danger); background: rgba(239,68,68,.08); box-shadow: 0 0 10px rgba(239,68,68,.15), var(--elev-inset); }
.btn-sm.delete { border-color: var(--danger-bdr); color: var(--danger); background: rgba(239,68,68,.04); }
.btn-sm.delete:hover { border-color: var(--danger); background: rgba(239,68,68,.08); box-shadow: 0 0 10px rgba(239,68,68,.15), var(--elev-inset); }

.role-form { display: flex; gap: .35rem; align-items: center; width: 100%; }

select.sel-sm {
  background: rgba(0,0,0,.2);
  border: 1px solid var(--glass-border-hi);
  color: var(--text); border-radius: var(--radius-sm);
  padding: .35rem .5rem;
  font-size: .72rem; font-weight: 700;
  cursor: pointer; font-family: var(--mono);
  flex: 1; text-transform: uppercase; letter-spacing: .04em;
  transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
  box-shadow: var(--elev-inset);
}
[data-theme="bright"] select.sel-sm { background: rgba(255,255,255,.7); }
select.sel-sm:hover { border-color: var(--accent-bdr); }
select.sel-sm:focus { outline: none; border-color: var(--accent-bdr); box-shadow: 0 0 0 2px var(--accent-glow-sm), var(--elev-inset); }

.user-last-login {
  font-size: .7rem; color: var(--muted);
  width: 100%; margin-top: .15rem;
  letter-spacing: .04em; font-family: var(--mono);
}

.app-access { border-top: 1px solid var(--glass-border); }
.app-access-toggle {
  width: 100%; background: none; border: none;
  padding: .65rem .95rem;
  display: flex; align-items: center; justify-content: space-between;
  color: var(--muted-hi);
  font-size: .72rem; font-weight: 700; font-family: var(--mono);
  text-transform: uppercase; letter-spacing: .06em;
  cursor: pointer; transition: color var(--transition-fast);
}
.app-access-toggle:hover { color: var(--accent); }
.app-access-toggle .chevron { transition: transform var(--transition-med); font-size: .7rem; }
.app-access-toggle.open .chevron { transform: rotate(180deg); }

.app-access-body {
  display: none; padding: .65rem .95rem .85rem;
  border-top: 1px solid var(--glass-border);
  background: rgba(0,0,0,.06);
}
[data-theme="bright"] .app-access-body { background: rgba(0,0,0,.02); }
.app-access-body.open { display: block; }

.apps-check { display: flex; flex-wrap: wrap; gap: .4rem; margin-bottom: .6rem; }
.apps-check label {
  display: flex; align-items: center; gap: .35rem;
  font-size: .72rem; font-weight: 700; font-family: var(--mono);
  text-transform: uppercase; letter-spacing: .05em;
  color: var(--muted-hi); cursor: pointer;
  padding: .32rem .6rem;
  border: 1px solid var(--glass-border-hi); border-radius: var(--radius-sm);
  transition: border-color var(--transition-fast), color var(--transition-fast), background var(--transition-fast);
  background: rgba(255,255,255,.02);
}
.apps-check label:hover { border-color: var(--accent-bdr); color: var(--text); background: var(--accent-bg); }
.apps-check input[type=checkbox] { accent-color: var(--accent); }

.self-note { font-size: .78rem; color: var(--muted-hi); font-style: italic; font-family: var(--mono); }

@media (max-width: 600px) {
  .role-form { flex-wrap: nowrap; }
  select.sel-sm { min-width: 0; }
  .user-actions { gap: .3rem; padding: .5rem .75rem; }
  .btn-sm { padding: .28rem .5rem; font-size: .68rem; }
  .user-card-head { padding: .6rem .75rem; }
  .user-name { font-size: .78rem; }
  .user-email { font-size: .68rem; }
  .user-last-login { font-size: .65rem; }
  .app-access-toggle { padding: .5rem .75rem; font-size: .68rem; }
  .app-access-body { padding: .5rem .75rem .7rem; }
  .apps-check label { font-size: .68rem; padding: .25rem .45rem; }
  .confirm-box { width: calc(100vw - 32px); padding: 16px; }
}

/* ── Confirm dialog ── */
.confirm-overlay {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,.7);
  backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
  z-index: 200; align-items: center; justify-content: center;
}
.confirm-overlay.show { display: flex; }
.confirm-box {
  background: var(--glass-hi);
  backdrop-filter: blur(24px) saturate(180%); -webkit-backdrop-filter: blur(24px) saturate(180%);
  border: 1px solid var(--glass-border-hi);
  border-radius: var(--radius);
  padding: 20px 22px; width: 320px; max-width: 90vw;
  box-shadow: var(--elev-4), var(--elev-inset);
  position: relative; overflow: hidden;
}
.confirm-box::before {
  content: '';
  position: absolute; top: 0; left: 0; right: 0; height: 1px;
  background: linear-gradient(90deg, transparent, var(--glass-border-hi), transparent);
}
.confirm-title {
  font-size: .85rem; font-weight: 700; color: var(--text);
  text-transform: uppercase; letter-spacing: .08em; margin-bottom: 8px;
  font-family: var(--mono);
}
.confirm-msg { font-size: .82rem; color: var(--muted-hi); line-height: 1.6; margin-bottom: 16px; }
.confirm-msg strong { color: var(--text); }
.confirm-btns { display: flex; gap: 8px; justify-content: flex-end; }

/* ── Bubble ── */
.bubble-wrap {
  position: fixed;
  bottom: calc(16px + env(safe-area-inset-bottom)); right: 16px;
  display: flex; flex-direction: column-reverse;
  gap: 8px; z-index: 9999; width: 320px; pointer-events: none;
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
.bubble.success { background: rgba(34,197,94,.1);  border-color: rgba(34,197,94,.3);  color: #86efac; }
.bubble.info    { background: rgba(0,229,255,.08);  border-color: rgba(0,229,255,.25); color: #67e8f9; }
.bubble.danger  { background: rgba(239,68,68,.1);   border-color: rgba(239,68,68,.3);  color: #fca5a5; }
[data-theme="bright"] .bubble.success { color: #166534; }
[data-theme="bright"] .bubble.info    { color: #0369a1; }
[data-theme="bright"] .bubble.danger  { color: #991b1b; }
.bubble i { font-size: 16px; flex-shrink: 0; margin-top: 1px; }
.bubble-body { flex: 1; min-width: 0; }
.bubble-title { font-weight: 700; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 2px; font-size: .72rem; font-family: var(--mono); }
.bubble-desc  { font-size: .78rem; opacity: .9; }
.bubble-code  {
  font-family: var(--mono); font-size: .75rem;
  background: rgba(0,0,0,.3); padding: 2px 7px;
  border-radius: 3px; letter-spacing: .05em;
  display: inline-block; margin-top: 4px;
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
  animation: bubbleProgress 6s linear forwards;
}
@keyframes bubbleProgress { from { width: 100%; } to { width: 0%; } }
</style>

<div class="page-header">
  <h1 class="page-title">Kelola User</h1>
  <p class="page-subtitle">Total <?= count($users) ?> pengguna terdaftar</p>
</div>

<?php if ($msg): ?>
<div id="page-msg"
     data-type="<?= htmlspecialchars($msg_type) ?>"
     data-full="<?= htmlspecialchars($msg) ?>"></div>
<?php endif; ?>

<div class="user-list">
<?php foreach ($users as $u): ?>
<div class="user-card">
  <div class="user-card-head">
    <div class="user-info">
      <div class="user-name"><?= htmlspecialchars($u['name']) ?></div>
      <div class="user-email"><?= htmlspecialchars($u['email']) ?></div>
    </div>
    <div class="user-meta">
      <span class="badge badge-<?= $u['role'] ?>"><?= strtoupper($u['role']) ?></span>
      <span class="badge <?= $u['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
        <?= $u['is_active'] ? 'Aktif' : 'Nonaktif' ?>
      </span>
    </div>
  </div>

  <?php if ($u['id'] !== (int)$user['id']): ?>
  <div class="user-actions">
    <form method="POST" class="form-inline">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="toggle_active">
      <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
      <button type="submit" class="btn-sm <?= $u['is_active'] ? 'danger' : '' ?>">
        <?= $u['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
      </button>
    </form>

    <form method="POST" class="role-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="change_role">
      <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
      <select name="new_role" class="sel-sm">
        <option value="user"  <?= $u['role']==='user' ?'selected':'' ?>>User</option>
        <option value="guest" <?= $u['role']==='guest'?'selected':'' ?>>Guest</option>
        <!-- 'admin' tidak ada di sini: promosi admin harus via Supabase dashboard langsung -->
      </select>
      <button type="submit" class="btn-sm">Ubah Role</button>
    </form>

    <form method="POST" class="form-inline" id="form-reset-<?= $u['id'] ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="reset_password">
      <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
      <button type="button" class="btn-sm danger"
              onclick="showConfirm('Reset Password',
                'Reset password <strong><?= htmlspecialchars(addslashes($u['name'])) ?></strong>? Password baru akan muncul di notifikasi dan log admin.',
                'Ya, Reset', 'danger',
                function(){ document.getElementById('form-reset-<?= $u['id'] ?>').submit(); })">
        Reset Password
      </button>
    </form>

    <form method="POST" class="form-inline" id="form-delete-<?= $u['id'] ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete_user">
      <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
      <button type="button" class="btn-sm delete"
              onclick="showConfirm('Hapus User',
                '<span style=\'color:var(--danger);font-weight:600\'>PERINGATAN:</span> Hapus akun <strong><?= htmlspecialchars(addslashes($u['name'])) ?></strong>? Akun akan disembunyikan permanen dari sistem dan tidak bisa login. Data tetap tersimpan di database (soft delete).',
                'Ya, Hapus', 'danger',
                function(){ document.getElementById('form-delete-<?= $u['id'] ?>').submit(); })">
        &#128683; Hapus User
      </button>
    </form>

    <span class="user-last-login">
      Login Terakhir: <?= $u['last_login_at'] ? date('d M Y H:i', strtotime($u['last_login_at'])) : '—' ?>
    </span>
  </div>

  <div class="app-access">
    <button type="button" class="app-access-toggle"
            onclick="toggleAppAccess(this)"
            data-uid="<?= $u['id'] ?>">
      <span>Akses Aplikasi (<?= count($ua_map[$u['id']] ?? []) ?>)</span>
      <span class="chevron">▼</span>
    </button>
    <div class="app-access-body" id="app-access-<?= $u['id'] ?>">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_apps">
        <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
        <?php if ($all_apps): ?>
        <div class="apps-check">
          <?php foreach ($all_apps as $app): ?>
          <label>
            <input type="checkbox" name="app_ids[]" value="<?= $app['id'] ?>"
              <?= in_array($app['id'], $ua_map[$u['id']] ?? []) ? 'checked' : '' ?>>
            <?= htmlspecialchars($app['name']) ?>
          </label>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-muted-sm">Belum ada aplikasi SSO terdaftar.</p>
        <?php endif; ?>
        <button type="submit" class="btn-sm">Simpan Akses</button>
      </form>
    </div>
  </div>

  <?php else: ?>
  <div class="user-actions">
    <span class="self-note">(Anda sendiri — tidak dapat diubah)</span>
  </div>
  <?php endif; ?>
</div>
<?php endforeach; ?>
</div>

<!-- Confirm dialog -->
<div class="confirm-overlay" id="confirmOverlay">
  <div class="confirm-box">
    <div class="confirm-title" id="confirmTitle"></div>
    <div class="confirm-msg" id="confirmMsg"></div>
    <div class="confirm-btns">
      <button class="btn-sm" id="confirm-cancel-btn">Batal</button>
      <button class="btn-sm" id="confirmOkBtn">Lanjutkan</button>
    </div>
  </div>
</div>

<!-- Floating bubble container -->
<div class="bubble-wrap" id="bubbleWrap"></div>

<script nonce="<?= csp_nonce() ?>">
function toggleAppAccess(btn) {
  btn.classList.toggle('open');
  var uid = btn.dataset.uid;
  document.getElementById('app-access-' + uid).classList.toggle('open');
}

var _confirmCb = null;

function showConfirm(title, msg, btnLabel, btnClass, cb) {
  document.getElementById('confirmTitle').textContent = title;
  document.getElementById('confirmMsg').innerHTML = msg;
  var ok = document.getElementById('confirmOkBtn');
  ok.textContent = btnLabel;
  ok.className = 'btn-sm ' + (btnClass === 'danger' ? 'danger' : '');
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

function showBubble(type, icon, title, desc, code, duration) {
  duration = duration || 6000;
  var wrap = document.getElementById('bubbleWrap');
  var b = document.createElement('div');
  b.className = 'bubble ' + type;
  var codeHtml = code ? '<span class="bubble-code">' + code + '</span>' : '';
  b.innerHTML =
    '<i class="ti ti-' + icon + '" aria-hidden="true"></i>' +
    '<div class="bubble-body"><div class="bubble-title">' + title + '</div>' +
    '<div class="bubble-desc">' + desc + codeHtml + '</div></div>' +
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

(function() {
  var el = document.getElementById('page-msg');
  if (!el) return;
  var type = el.dataset.type || 'success';
  var full = el.dataset.full || '';
  var icons  = { success: 'check', info: 'key', danger: 'alert-triangle' };
  var titles = { success: 'Berhasil', info: 'Info', danger: 'Peringatan' };

  var code = null;
  var desc = full;
  if (type === 'info') {
    var match = full.match(/<code[^>]*>([^<]+)<\/code>/);
    if (match) {
      code = match[1];
      desc = full.replace(/<code[^>]*>[^<]+<\/code>/, '').replace(/<[^>]+>/g, '').trim();
    }
  } else {
    desc = full.replace(/<[^>]+>/g, '');
  }

  showBubble(type, icons[type] || 'check', titles[type] || 'Info', desc, code, type === 'info' ? 10000 : 6000);
})();

// Bind static onclick pengganti CSP nonce
document.addEventListener('DOMContentLoaded', function() {
  var cancelBtn = document.getElementById('confirm-cancel-btn');
  if (cancelBtn) cancelBtn.addEventListener('click', function() { if (window.closeConfirm) closeConfirm(); });
});
</script>

<?php layout_foot(); ?>