<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();

$user = get_current_user_data();
$db   = getDB();

$msg = '';
$msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $target_id = (int)($_POST['target_id'] ?? 0);

    $row = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $row->execute([$target_id]);
    $target = $row->fetch(PDO::FETCH_ASSOC);

    if ($target && $target_id !== (int)$user['id']) {
        if ($action === 'toggle_active') {
            $new_status = $target['is_active'] ? 0 : 1;
            $db->prepare("UPDATE users SET is_active = ? WHERE id = ?")->execute([$new_status, $target_id]);
            $label = $new_status ? 'Status Akun Diaktifkan' : 'Status Akun Dinonaktifkan';
            log_admin_action($user['id'], $user['name'], $label, $target['name']);
            $msg = "Akun <strong>{$target['name']}</strong> berhasil " . ($new_status ? 'diaktifkan' : 'dinonaktifkan') . '.';
            $msg_type = 'success';
        } elseif ($action === 'change_role') {
            $new_role = $_POST['new_role'] ?? '';
            if (in_array($new_role, ['user', 'guest'])) {
                $db->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$new_role, $target_id]);
                log_admin_action($user['id'], $user['name'], "Ubah Role menjadi " . strtoupper($new_role), $target['name']);
                $msg = "Role <strong>{$target['name']}</strong> diubah ke $new_role.";
                $msg_type = 'success';
            }
        } elseif ($action === 'reset_password') {
            $new_pass = bin2hex(random_bytes(6));
            $hash = password_hash($new_pass, PASSWORD_BCRYPT);
            $db->prepare("UPDATE users SET password = ?, password_changed_at = NOW() WHERE id = ?")->execute([$hash, $target_id]);
            log_admin_action($user['id'], $user['name'], "Reset Password — password baru: $new_pass", $target['email']);
            $msg = "Password <strong>{$target['name']}</strong> direset ke: <code style='font-family:monospace;background:rgba(0,0,0,.3);padding:.1rem .4rem;border-radius:4px'>$new_pass</code>";
            $msg_type = 'info';
        } elseif ($action === 'delete_user') {
            $db->prepare("DELETE FROM user_applications WHERE user_id = ?")->execute([$target_id]);
            $db->prepare("DELETE FROM users WHERE id = ?")->execute([$target_id]);
            log_admin_action($user['id'], $user['name'], 'Hapus User', $target['name'] . ' (' . $target['email'] . ')');
            $msg = "Akun <strong>{$target['name']}</strong> telah dihapus permanen.";
            $msg_type = 'danger';
        } elseif ($action === 'update_apps') {
            $app_ids = array_map('intval', $_POST['app_ids'] ?? []);
            $db->prepare("DELETE FROM user_applications WHERE user_id = ?")->execute([$target_id]);
            foreach ($app_ids as $aid) {
                $db->prepare("INSERT INTO user_applications (user_id, application_id) VALUES (?,?)")->execute([$target_id, $aid]);
            }
            log_admin_action($user['id'], $user['name'], 'Update Akses Aplikasi SSO', $target['name']);
            $msg = "Akses aplikasi <strong>{$target['name']}</strong> diperbarui.";
            $msg_type = 'success';
        }
    }
}

$users    = $db->query("SELECT * FROM users WHERE deleted_at IS NULL ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$all_apps = $db->query("SELECT * FROM sso_applications ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

$ua_map = [];
foreach ($db->query("SELECT * FROM user_applications")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $ua_map[$r['user_id']][] = $r['application_id'];
}

layout_head('Kelola User', 'admin-users');
?>
<style>
.page-header { margin-bottom: 1rem; }

.user-list { display: flex; flex-direction: column; gap: .6rem; }

.user-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 4px;
  overflow: hidden;
}

.user-card-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  padding: .75rem .9rem;
  gap: .6rem;
}

.user-info { min-width: 0; flex: 1; }

.user-name {
  font-weight: 600;
  font-size: .9rem;
  text-transform: uppercase;
  letter-spacing: .03em;
  line-height: 1.3;
}

.user-email {
  font-size: .8rem;
  color: var(--text-dim);
  margin-top: .15rem;
  word-break: break-all;
  line-height: 1.4;
}

.user-meta {
  display: flex;
  gap: .3rem;
  flex-shrink: 0;
  flex-wrap: wrap;
  justify-content: flex-end;
  max-width: 110px;
}

.user-actions {
  border-top: 1px solid var(--border);
  padding: .6rem .9rem;
  display: flex;
  flex-wrap: wrap;
  gap: .4rem;
  align-items: center;
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

.role-form {
  display: flex;
  gap: .35rem;
  align-items: center;
  width: 100%;
}

select.sel-sm {
  background: var(--surface2);
  border: 1px solid var(--border);
  color: var(--text);
  border-radius: 2px;
  padding: .38rem .5rem;
  font-size: .8rem;
  font-weight: 500;
  cursor: pointer;
  font-family: var(--sans);
  flex: 1;
}
select.sel-sm:hover { border-color: var(--accent); }

.user-last-login {
  font-size: .75rem;
  color: var(--text-dim);
  width: 100%;
  margin-top: .1rem;
  letter-spacing: .02em;
  font-family: var(--sans);
}

.app-access { border-top: 1px solid var(--border); }

.app-access-toggle {
  width: 100%;
  background: none;
  border: none;
  padding: .6rem .9rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  color: var(--text-dim);
  font-size: .8rem;
  font-weight: 500;
  font-family: var(--sans);
  text-transform: uppercase;
  letter-spacing: .04em;
  cursor: pointer;
  transition: color .15s;
}
.app-access-toggle:hover { color: var(--accent); }
.app-access-toggle .chevron { transition: transform .2s; font-size: .75rem; }
.app-access-toggle.open .chevron { transform: rotate(180deg); }

.app-access-body {
  display: none;
  padding: .65rem .9rem .85rem;
  border-top: 1px solid var(--border);
}
.app-access-body.open { display: block; }

.apps-check {
  display: flex;
  flex-wrap: wrap;
  gap: .4rem;
  margin-bottom: .6rem;
}
.apps-check label {
  display: flex;
  align-items: center;
  gap: .35rem;
  font-size: .8rem;
  font-weight: 500;
  font-family: var(--sans);
  text-transform: uppercase;
  letter-spacing: .03em;
  color: var(--text-dim);
  cursor: pointer;
  padding: .35rem .6rem;
  border: 1px solid var(--border);
  border-radius: 2px;
  transition: border-color .15s, color .15s;
}
.apps-check label:hover { border-color: var(--accent); color: var(--text); }
.apps-check input[type=checkbox] { accent-color: var(--accent); }

.self-note {
  font-size: .8rem;
  color: var(--text-dim);
  font-style: italic;
}

.btn-sm.delete {
  border-color: rgba(192,64,64,.2);
  color: #a03030;
}
.btn-sm.delete:hover {
  border-color: var(--danger);
  color: var(--danger);
}

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

.bubble-wrap {
  position: fixed;
  top: 14px;
  right: 14px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  z-index: 300;
  width: 300px;
  pointer-events: none;
}
.bubble {
  border-radius: 4px;
  padding: 10px 12px;
  font-size: .8rem;
  line-height: 1.5;
  border: 1px solid;
  display: flex;
  align-items: flex-start;
  gap: 8px;
  pointer-events: all;
  transition: opacity .3s;
  font-family: var(--sans);
}
.bubble.success { background: rgba(58,138,96,.12); border-color: rgba(58,138,96,.3); color: #3a8a60; }
.bubble.info    { background: var(--acc-bg);        border-color: var(--acc-bdr);       color: var(--acc); }
.bubble.danger  { background: rgba(192,64,64,.1);   border-color: rgba(192,64,64,.3);   color: var(--danger); }
.bubble i { font-size: 16px; flex-shrink: 0; margin-top: 1px; }
.bubble-body { flex: 1; min-width: 0; }
.bubble-title { font-weight: 600; text-transform: uppercase; letter-spacing: .03em; margin-bottom: 2px; }
.bubble-desc  { font-size: .75rem; opacity: .85; }
.bubble-code  {
  font-family: var(--mono);
  font-size: .8rem;
  background: rgba(0,0,0,.3);
  padding: 2px 7px;
  border-radius: 3px;
  letter-spacing: .05em;
  display: inline-block;
  margin-top: 4px;
  color: var(--acc);
}
.bubble-close {
  margin-left: auto;
  cursor: pointer;
  opacity: .5;
  font-size: 18px;
  flex-shrink: 0;
  color: inherit;
  background: none;
  border: none;
  font-family: inherit;
  padding: 0 2px;
  line-height: 1;
}
.bubble-close:hover { opacity: 1; }
</style>

<div class="page-header">
  <h1 class="page-title">Kelola User</h1>
  <p class="page-subtitle">Total <?= count($users) ?> pengguna terdaftar</p>
</div>

<?php if ($msg): ?>
<div id="page-msg"
     data-msg="<?= htmlspecialchars(strip_tags($msg)) ?>"
     data-type="<?= htmlspecialchars($msg_type) ?>"></div>
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
    <form method="POST" style="display:inline">
      <input type="hidden" name="action" value="toggle_active">
      <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
      <button type="submit" class="btn-sm <?= $u['is_active'] ? 'danger' : '' ?>">
        <?= $u['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
      </button>
    </form>

    <form method="POST" class="role-form">
      <input type="hidden" name="action" value="change_role">
      <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
      <select name="new_role" class="sel-sm">
        <option value="user"  <?= $u['role']==='user' ?'selected':'' ?>>User</option>
        <option value="guest" <?= $u['role']==='guest'?'selected':'' ?>>Guest</option>
      </select>
      <button type="submit" class="btn-sm">Ubah Role</button>
    </form>

    <form method="POST" style="display:inline" id="form-reset-<?= $u['id'] ?>">
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

    <form method="POST" style="display:inline" id="form-delete-<?= $u['id'] ?>">
      <input type="hidden" name="action" value="delete_user">
      <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
      <button type="button" class="btn-sm delete"
              onclick="showConfirm('Hapus User Permanen',
                '<span style=\'color:var(--danger);font-weight:600\'>PERINGATAN:</span> Hapus akun <strong><?= htmlspecialchars(addslashes($u['name'])) ?></strong> secara permanen? Seluruh data user akan dihapus dan tidak bisa dikembalikan.',
                'Ya, Hapus Permanen', 'danger',
                function(){ document.getElementById('form-delete-<?= $u['id'] ?>').submit(); })">
        &#128465; Hapus User
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
        <p style="font-size:.72rem;color:var(--text-dim);margin-bottom:.6rem">Belum ada aplikasi SSO terdaftar.</p>
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

<div class="confirm-overlay" id="confirmOverlay">
  <div class="confirm-box">
    <div class="confirm-title" id="confirmTitle"></div>
    <div class="confirm-msg" id="confirmMsg"></div>
    <div class="confirm-btns">
      <button class="btn-sm" onclick="closeConfirm()">Batal</button>
      <button class="btn-sm" id="confirmOkBtn">Lanjutkan</button>
    </div>
  </div>
</div>

<div class="bubble-wrap" id="bubbleWrap"></div>

<script>
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
  closeConfirm();
  if (_confirmCb) _confirmCb();
});

document.getElementById('confirmOverlay').addEventListener('click', function(e) {
  if (e.target === this) closeConfirm();
});

function showBubble(type, icon, title, desc, code) {
  var wrap = document.getElementById('bubbleWrap');
  var b = document.createElement('div');
  b.className = 'bubble ' + type;
  var codeHtml = code ? '<div class="bubble-code">' + code + '</div>' : '';
  b.innerHTML = '<i class="ti ti-' + icon + '" aria-hidden="true"></i>'
    + '<div class="bubble-body"><div class="bubble-title">' + title + '</div>'
    + '<div class="bubble-desc">' + desc + codeHtml + '</div></div>'
    + '<button class="bubble-close" onclick="this.parentNode.remove()" aria-label="Tutup">&#215;</button>';
  wrap.prepend(b);
  setTimeout(function() {
    b.style.opacity = '0';
    setTimeout(function() { if (b.parentNode) b.remove(); }, 350);
  }, 6000);
}

(function() {
  var el = document.getElementById('page-msg');
  if (!el) return;
  var msg  = el.dataset.msg;
  var type = el.dataset.type || 'success';
  var icons = { success: 'check', info: 'key', danger: 'alert-triangle' };
  var titles = { success: 'Berhasil', info: 'Info', danger: 'Peringatan' };
  showBubble(type, icons[type] || 'check', titles[type] || 'Info', msg);
})();
</script>

<?php layout_foot(); ?>