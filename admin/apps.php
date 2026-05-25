<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();

$user = get_current_user_data();
$db   = getDB();
$msg  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_app') {
        $name     = trim($_POST['name'] ?? '');
        $redirect = trim($_POST['redirect'] ?? '');
        $desc     = trim($_POST['description'] ?? '');
        $signed   = isset($_POST['use_signed_token']) ? 1 : 0;
        if ($name && $redirect) {
            $cid = bin2hex(random_bytes(8));
            $sec = bin2hex(random_bytes(32));
            $db->prepare("INSERT INTO sso_applications (name, redirect, client_id, client_secret, description, use_signed_token, is_active, created_at, updated_at) VALUES (?,?,?,?,?,?,1,NOW(),NOW())")
               ->execute([$name, $redirect, $cid, $sec, $desc, $signed]);
            log_admin_action($user['id'], $user['name'],
                'Registrasi Aplikasi Baru' . ($signed ? ' (Signed Token)' : ''), $name);
            $msg = "Aplikasi <strong>$name</strong> berhasil didaftarkan. Client ID: <code style='font-family:monospace;background:rgba(0,0,0,.3);padding:.1rem .4rem;border-radius:4px'>$cid</code>";
        }
    } elseif ($action === 'toggle_status') {
        $id  = (int)$_POST['app_id'];
        $row = $db->prepare("SELECT * FROM sso_applications WHERE id = ? LIMIT 1");
        $row->execute([$id]);
        $app = $row->fetch(PDO::FETCH_ASSOC);
        if ($app) {
            $new = $app['is_active'] ? 0 : 1;
            $db->prepare("UPDATE sso_applications SET is_active = ? WHERE id = ?")->execute([$new, $id]);
            log_admin_action($user['id'], $user['name'], 'Update Status Aplikasi', $app['name'] . ' (' . ($new ? 'Aktif' : 'Deaktif') . ')');
            $msg = "Status aplikasi diperbarui.";
        }
    } elseif ($action === 'toggle_signed') {
        $id  = (int)$_POST['app_id'];
        $row = $db->prepare("SELECT * FROM sso_applications WHERE id = ? LIMIT 1");
        $row->execute([$id]);
        $app = $row->fetch(PDO::FETCH_ASSOC);
        if ($app) {
            $new = $app['use_signed_token'] ? 0 : 1;
            $db->prepare("UPDATE sso_applications SET use_signed_token = ? WHERE id = ?")->execute([$new, $id]);
            log_admin_action($user['id'], $user['name'], 'Toggle Signed Token: ' . ($new ? 'ON' : 'OFF'), $app['name']);
            $msg = "Signed Token diubah ke " . ($new ? 'RSA' : 'HMAC') . ".";
        }
    } elseif ($action === 'delete_app') {
        $id  = (int)$_POST['app_id'];
        $row = $db->prepare("SELECT name FROM sso_applications WHERE id = ? LIMIT 1");
        $row->execute([$id]);
        $app = $row->fetch(PDO::FETCH_ASSOC);
        if ($app) {
            $db->prepare("DELETE FROM sso_applications WHERE id = ?")->execute([$id]);
            $db->prepare("DELETE FROM user_applications WHERE application_id = ?")->execute([$id]);
            log_admin_action($user['id'], $user['name'], 'Hapus Aplikasi', $app['name']);
            $msg = "Aplikasi <strong>" . htmlspecialchars($app['name']) . "</strong> berhasil dihapus.";
        }
    }
}

$apps = $db->query("SELECT * FROM sso_applications ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

layout_head('Aplikasi SSO', 'admin-apps');
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
</style>

<div class="page-header">
  <h1 class="page-title">Aplikasi SSO</h1>
  <p class="page-subtitle">Daftarkan dan kelola aplikasi yang terintegrasi.</p>
</div>

<?php if ($msg): ?>
  <div class="msg-box"><?= $msg ?></div>
<?php endif; ?>

<div class="add-card">
  <div class="add-card-title">+ Daftarkan Aplikasi Baru</div>
  <form method="POST">
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
        Client Secret:
        <span class="secret-blur" onclick="this.classList.toggle('revealed')"
              title="Ketuk untuk tampilkan"><?= htmlspecialchars($app['client_secret']) ?></span>
        <span class="reveal-hint" onclick="this.previousElementSibling.classList.toggle('revealed')">[Ketuk untuk tampilkan]</span>
      </div>
      <div class="redirect-url">↳ <?= htmlspecialchars($app['redirect']) ?></div>
    </div>
  </div>
  <div class="app-actions">
    <form method="POST" style="display:inline">
      <input type="hidden" name="action" value="toggle_status">
      <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
      <button type="submit" class="btn-sm <?= $app['is_active'] ? 'danger' : '' ?>">
        <?= $app['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
      </button>
    </form>
    <form method="POST" style="display:inline">
      <input type="hidden" name="action" value="toggle_signed">
      <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
      <button type="submit" class="btn-sm">Toggle RSA</button>
    </form>
    <form method="POST" style="display:inline"
          onsubmit="return confirm('Hapus aplikasi <?= htmlspecialchars(addslashes($app['name'])) ?>?')">
      <input type="hidden" name="action" value="delete_app">
      <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
      <button type="submit" class="btn-sm danger">Hapus Aplikasi</button>
    </form>
  </div>
</div>
<?php endforeach; ?>
</div>

<?php layout_foot(); ?>