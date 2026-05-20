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
        } elseif ($action === 'change_role') {
            $new_role = $_POST['new_role'] ?? '';
            if (in_array($new_role, ['admin', 'user', 'guest'])) {
                $db->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$new_role, $target_id]);
                log_admin_action($user['id'], $user['name'], "Ubah Role menjadi " . strtoupper($new_role), $target['name']);
                $msg = "Role <strong>{$target['name']}</strong> diubah ke $new_role.";
            }
        } elseif ($action === 'reset_password') {
            $new_pass = bin2hex(random_bytes(6));
            $hash = password_hash($new_pass, PASSWORD_BCRYPT);
            $db->prepare("UPDATE users SET password = ?, password_changed_at = NOW() WHERE id = ?")->execute([$hash, $target_id]);
            log_admin_action($user['id'], $user['name'], 'Reset Password', $target['email']);
            $msg = "Password <strong>{$target['name']}</strong> direset ke: <code style='font-family:monospace;background:rgba(0,0,0,.3);padding:.1rem .4rem;border-radius:4px'>$new_pass</code>";
        } elseif ($action === 'update_apps') {
            $app_ids = array_map('intval', $_POST['app_ids'] ?? []);
            $db->prepare("DELETE FROM user_applications WHERE user_id = ?")->execute([$target_id]);
            foreach ($app_ids as $aid) {
                $db->prepare("INSERT INTO user_applications (user_id, application_id) VALUES (?,?)")->execute([$target_id, $aid]);
            }
            log_admin_action($user['id'], $user['name'], 'Update Akses Aplikasi SSO', $target['name']);
            $msg = "Akses aplikasi <strong>{$target['name']}</strong> diperbarui.";
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
/* ── Page header ── */
.page-header { margin-bottom: 1.5rem; }

/* ── User cards (mobile) / table (desktop) ── */
.user-list { display: flex; flex-direction: column; gap: .75rem; }

.user-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  overflow: hidden;
}
.user-card-head {
  display: flex; align-items: center; justify-content: space-between;
  padding: .9rem 1.1rem;
  gap: .75rem;
}
.user-info { min-width: 0; flex: 1; }
.user-name { font-weight: 500; font-size: .92rem; }
.user-email { font-size: .75rem; color: var(--text-dim); margin-top: .1rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.user-meta { display: flex; gap: .4rem; align-items: center; flex-shrink: 0; flex-wrap: wrap; justify-content: flex-end; }
.user-last-login { font-size: .7rem; color: var(--text-dim); font-family: 'DM Mono', monospace; }

.user-actions {
  border-top: 1px solid var(--border);
  padding: .75rem 1.1rem;
  display: flex; flex-wrap: wrap; gap: .5rem; align-items: flex-start;
}

/* buttons */
.btn-sm {
  background: var(--surface2);
  border: 1px solid var(--border);
  color: var(--text);
  border-radius: 6px;
  padding: .32rem .7rem;
  font-size: .78rem;
  cursor: pointer;
  font-family: 'DM Sans', sans-serif;
  transition: border-color .15s, color .15s;
  white-space: nowrap;
}
.btn-sm:hover { border-color: var(--accent); color: var(--accent); }
.btn-sm.danger { border-color: rgba(229,115,115,.3); color: var(--danger); }
.btn-sm.danger:hover { border-color: var(--danger); }

select.sel-sm {
  background: var(--surface2);
  border: 1px solid var(--border);
  color: var(--text);
  border-radius: 6px;
  padding: .32rem .5rem;
  font-size: .78rem;
  cursor: pointer;
  font-family: 'DM Sans', sans-serif;
}
select.sel-sm:hover { border-color: var(--accent); }

/* Role form inline */
.role-form { display: flex; gap: .35rem; align-items: center; }

/* App access accordion */
.app-access {
  border-top: 1px solid var(--border);
}
.app-access-toggle {
  width: 100%; background: none; border: none;
  padding: .65rem 1.1rem;
  display: flex; align-items: center; justify-content: space-between;
  color: var(--text-dim); font-size: .82rem;
  cursor: pointer; font-family: 'DM Sans', sans-serif;
  transition: color .15s, background .15s;
}
.app-access-toggle:hover { color: var(--accent); background: var(--accent-glow); }
.app-access-toggle .chevron { transition: transform .2s; font-size: .7rem; }
.app-access-toggle.open .chevron { transform: rotate(180deg); }

.app-access-body {
  display: none; padding: .75rem 1.1rem 1rem;
  border-top: 1px solid var(--border);
}
.app-access-body.open { display: block; }

.apps-check { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: .75rem; }
.apps-check label {
  display: flex; align-items: center; gap: .35rem;
  font-size: .8rem; color: var(--text-dim); cursor: pointer;
  padding: .3rem .6rem;
  border: 1px solid var(--border);
  border-radius: 6px;
  transition: border-color .15s, color .15s;
}
.apps-check label:hover { border-color: var(--accent); color: var(--text); }
.apps-check input[type=checkbox] { accent-color: var(--accent); }

/* Self row */
.self-note { font-size: .78rem; color: var(--text-dim); font-style: italic; }
</style>

<div class="page-header">
  <h1 class="page-title">Kelola User</h1>
  <p class="page-subtitle">Total <?= count($users) ?> pengguna terdaftar</p>
</div>

<?php if ($msg): ?>
  <div class="msg-box"><?= $msg ?></div>
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
      <span class="badge badge-<?= $u['role'] ?>"><?= $u['role'] ?></span>
      <span class="badge <?= $u['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
        <?= $u['is_active'] ? 'Aktif' : 'Nonaktif' ?>
      </span>
    </div>
  </div>

  <?php if ($u['id'] !== (int)$user['id']): ?>
  <div class="user-actions">
    <!-- Toggle aktif -->
    <form method="POST" style="display:inline">
      <input type="hidden" name="action" value="toggle_active">
      <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
      <button type="submit" class="btn-sm <?= $u['is_active'] ? 'danger' : '' ?>">
        <?= $u['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
      </button>
    </form>

    <!-- Ubah role -->
    <form method="POST" class="role-form">
      <input type="hidden" name="action" value="change_role">
      <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
      <select name="new_role" class="sel-sm">
        <option value="admin" <?= $u['role']==='admin'?'selected':'' ?>>Admin</option>
        <option value="user"  <?= $u['role']==='user' ?'selected':'' ?>>User</option>
        <option value="guest" <?= $u['role']==='guest'?'selected':'' ?>>Guest</option>
      </select>
      <button type="submit" class="btn-sm">Set Role</button>
    </form>

    <!-- Reset password -->
    <form method="POST" style="display:inline"
          onsubmit="return confirm('Reset password <?= htmlspecialchars(addslashes($u['name'])) ?>?')">
      <input type="hidden" name="action" value="reset_password">
      <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
      <button type="submit" class="btn-sm danger">Reset PW</button>
    </form>

    <span class="user-last-login" style="width:100%;margin-top:.1rem">
      Last login: <?= $u['last_login_at'] ? date('d M y H:i', strtotime($u['last_login_at'])) : '—' ?>
    </span>
  </div>

  <!-- App access accordion -->
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
        <p style="font-size:.8rem;color:var(--text-dim);margin-bottom:.75rem">Belum ada aplikasi SSO terdaftar.</p>
        <?php endif; ?>
        <button type="submit" class="btn-sm">Simpan Akses</button>
      </form>
    </div>
  </div>

  <?php else: ?>
  <div class="user-actions">
    <span class="self-note">(Anda sendiri)</span>
  </div>
  <?php endif; ?>
</div>
<?php endforeach; ?>
</div>

<script>
function toggleAppAccess(btn) {
  btn.classList.toggle('open');
  var uid = btn.dataset.uid;
  document.getElementById('app-access-' + uid).classList.toggle('open');
}
</script>

<?php layout_foot(); ?>