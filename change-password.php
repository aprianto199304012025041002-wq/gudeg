<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';
require_login();

$user  = get_current_user_data();
$db    = getDB();
$error = '';
$ok    = false;
$sess_msg = '';

// Handle logout sesi lain
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout_session') {
    csrf_verify();
    $target_sess = $_POST['session_id'] ?? '';
    if ($target_sess && $target_sess !== session_id()) {
        $db->prepare("DELETE FROM sessions WHERE id = ? AND user_id = ?")->execute([$target_sess, $user['id']]);
        $sess_msg = 'Sesi berhasil diakhiri.';
    }
}

// Handle logout semua sesi lain
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout_all_sessions') {
    csrf_verify();
    $db->prepare("DELETE FROM sessions WHERE user_id = ? AND id != ?")->execute([$user['id'], session_id()]);
    $sess_msg = 'Semua sesi lain berhasil diakhiri.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // FIX: Validasi CSRF token
    csrf_verify();

    $old  = $_POST['old_password'] ?? '';
    $new  = $_POST['new_password'] ?? '';
    $conf = $_POST['confirm'] ?? '';

    $row = $db->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
    $row->execute([$user['id']]);
    $current = $row->fetchColumn();

    if (!password_verify($old, $current)) {
        $error = 'Password lama tidak cocok.';
    } elseif (strlen($new) < 8) {
        $error = 'Password baru minimal 8 karakter.';
    } elseif ($new !== $conf) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        $hash = password_hash($new, PASSWORD_BCRYPT);
        $db->prepare("UPDATE users SET password = ?, password_changed_at = NOW() WHERE id = ?")->execute([$hash, $user['id']]);
        log_activity($user['id'], $user['name'], $user['email'], 'Ubah Password Sendiri');
        $ok = true;
    }
}

// Ambil 10 log aktivitas terakhir user
$log_stmt = $db->prepare("SELECT activity, ip_address, created_at FROM user_logs WHERE user_id = ? ORDER BY id DESC LIMIT 10");
$log_stmt->execute([$user['id']]);
$user_logs = $log_stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil sesi aktif user
$sess_stmt = $db->prepare("SELECT id, ip_address, user_agent, created_at, last_activity FROM sessions WHERE user_id = ? ORDER BY last_activity DESC");
$sess_stmt->execute([$user['id']]);
$active_sessions = $sess_stmt->fetchAll(PDO::FETCH_ASSOC);
$current_sess_id = session_id();

layout_head('Pengaturan', 'password');
?>
<style>
.pw-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 4px;
  padding: 1.1rem 1rem;
  max-width: 460px;
}

.form-group { margin-bottom: 1rem; }

.form-group label {
  display: block;
  font-size: .72rem;
  font-weight: 600;
  color: var(--text-dim);
  text-transform: uppercase;
  letter-spacing: .07em;
  margin-bottom: .35rem;
  font-family: var(--sans);
}

.form-group input[type="password"] {
  width: 100%;
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 2px;
  padding: .65rem .85rem;
  color: var(--text);
  font-family: var(--sans);
  font-size: .9rem;
  outline: none;
  transition: border-color .2s;
  -webkit-appearance: none;
  -webkit-box-shadow: 0 0 0 1000px var(--bg) inset;
  -webkit-text-fill-color: var(--text);
}
.form-group input[type="password"]:focus { border-color: var(--accent); }

.btn-save {
  width: 100%;
  background: rgba(122,162,212,.1);
  border: 1px solid var(--accent);
  color: var(--accent);
  border-radius: 2px;
  padding: .75rem;
  font-family: var(--sans);
  font-size: .82rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .06em;
  cursor: pointer;
  margin-top: .5rem;
  transition: background .2s;
  -webkit-tap-highlight-color: transparent;
}
.btn-save:hover { background: rgba(122,162,212,.18); }
.btn-save:active { opacity: .85; }

.success-banner {
  background: rgba(74,222,128,.06);
  border: 1px solid rgba(74,222,128,.2);
  color: var(--success);
  border-radius: 4px;
  padding: .75rem .9rem;
  font-size: .82rem;
  font-weight: 600;
  font-family: var(--sans);
  text-transform: uppercase;
  letter-spacing: .04em;
  display: flex;
  align-items: center;
  gap: .5rem;
  max-width: 460px;
  margin-bottom: 1rem;
}

.two-col {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
  margin-bottom: 1rem;
  align-items: start;
}
@media (max-width: 700px) {
  .two-col { grid-template-columns: 1fr; }
}
.panel {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 4px;
  overflow: hidden;
}
.panel-hd {
  padding: .6rem 1rem;
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center; gap: 8px;
}
.panel-hd i { font-size: 15px; color: var(--accent-dim); }
.panel-title {
  font-size: 11px; font-weight: 600; text-transform: uppercase;
  letter-spacing: .07em; color: var(--text-dim);
}
.panel-body { padding: .5rem 1rem; }

.log-row {
  display: flex; align-items: center; justify-content: space-between;
  padding: .55rem 0; border-bottom: 1px solid var(--border); gap: .6rem;
}
.log-row:last-child { border-bottom: none; }
.log-activity {
  font-size: .82rem; font-weight: 600; font-family: var(--sans);
  text-transform: uppercase; letter-spacing: .03em; color: var(--text);
}
.log-meta {
  color: var(--text-dim); font-family: var(--sans);
  font-size: .75rem; text-align: right; flex-shrink: 0; line-height: 1.6;
}

.sesi-row {
  display: flex; align-items: center; justify-content: space-between;
  padding: .65rem 0; border-bottom: 1px solid var(--border); gap: .6rem;
}
.sesi-row:last-child { border-bottom: none; }
.sesi-device {
  font-size: .82rem; font-weight: 600; font-family: var(--sans);
  text-transform: uppercase; letter-spacing: .03em; color: var(--text);
  display: flex; align-items: center; gap: 6px;
}
.sesi-device i { font-size: 14px; color: var(--accent-dim); }
.sesi-meta { font-size: .75rem; color: var(--text-dim); margin-top: 2px; }
.badge-current {
  display: inline-block; font-size: 10px; padding: 1px 6px; border-radius: 3px;
  background: rgba(58,138,96,.1); color: var(--success);
  border: 1px solid rgba(58,138,96,.2); text-transform: uppercase;
  letter-spacing: .04em; margin-left: 4px;
}
.btn-logout-sess {
  background: rgba(192,64,64,.08); border: 1px solid rgba(192,64,64,.25);
  color: var(--danger); border-radius: 2px; padding: .3rem .7rem;
  font-size: .75rem; font-weight: 600; text-transform: uppercase;
  letter-spacing: .04em; cursor: pointer; font-family: var(--sans);
  white-space: nowrap; flex-shrink: 0;
}
.btn-logout-sess:hover { border-color: var(--danger); }
.btn-logout-all {
  width: 100%; background: rgba(192,64,64,.06); border: 1px solid rgba(192,64,64,.2);
  color: var(--danger); border-radius: 2px; padding: .65rem;
  font-size: .8rem; font-weight: 600; text-transform: uppercase;
  letter-spacing: .05em; cursor: pointer; font-family: var(--sans);
  margin-top: .75rem;
}
.btn-logout-all:hover { background: rgba(192,64,64,.12); }
.sess-notice {
  background: rgba(58,138,96,.08); border: 1px solid rgba(58,138,96,.2);
  color: var(--success); border-radius: 4px; padding: .6rem .9rem;
  font-size: .82rem; font-weight: 600; font-family: var(--sans);
  max-width: 680px; margin-bottom: .75rem;
}
</style>

<div class="page-header">
  <h1 class="page-title">Pengaturan</h1>
  <p class="page-subtitle">Masuk sebagai <?= htmlspecialchars($user['email']) ?></p>
</div>

<?php if ($ok): ?>
  <div class="success-banner">
    <span>✓</span> Password berhasil diubah!
  </div>
<?php endif; ?>

<?php if ($error): ?>
  <div class="msg-box error" style="max-width:440px"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (!$ok): ?>
<div class="pw-card">
  <form method="POST">
    <?= csrf_field() ?>
    <div class="form-group">
      <label for="old_password">Password Lama</label>
      <input type="password" id="old_password" name="old_password"
             required autocomplete="current-password">
    </div>
    <div class="form-group">
      <label for="new_password">Password Baru</label>
      <input type="password" id="new_password" name="new_password"
             placeholder="Min. 8 karakter" required autocomplete="new-password">
    </div>
    <div class="form-group">
      <label for="confirm">Konfirmasi Password Baru</label>
      <input type="password" id="confirm" name="confirm"
             required autocomplete="new-password">
    </div>
    <button type="submit" class="btn-save">Simpan Password</button>
  </form>
</div>
<?php endif; ?>

<?php if ($sess_msg): ?>
<div class="sess-notice"><?= htmlspecialchars($sess_msg) ?></div>
<?php endif; ?>

<div class="two-col">
<div><!-- kolom kiri: ubah password + sesi -->

<!-- Panel: 10 Aktivitas Terakhir -->
<div class="panel">
  <div class="panel-hd">
    <i class="ti ti-history" aria-hidden="true"></i>
    <span class="panel-title">10 Aktivitas Terakhir</span>
  </div>
  <div class="panel-body">
    <?php if ($user_logs): ?>
      <?php foreach ($user_logs as $log): ?>
      <div class="log-row">
        <span class="log-activity"><?= htmlspecialchars($log['activity']) ?></span>
        <span class="log-meta">
          <?= htmlspecialchars($log['ip_address']) ?><br>
          <?= date('d M, H:i', strtotime($log['created_at'])) ?>
        </span>
      </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p style="font-size:.82rem;color:var(--text-dim);padding:.5rem 0">Belum ada aktivitas tercatat.</p>
    <?php endif; ?>
  </div>
</div>

</div><!-- /kolom kiri -->
<div><!-- kolom kanan: log aktivitas -->

<!-- Panel: Sesi Login Aktif -->
<div class="panel">
  <div class="panel-hd">
    <i class="ti ti-device-laptop" aria-hidden="true"></i>
    <span class="panel-title">Sesi Login Aktif</span>
  </div>
  <div class="panel-body">
    <?php if ($active_sessions): ?>
      <?php foreach ($active_sessions as $sess): ?>
      <?php
        $ua_raw   = $sess['user_agent'] ?? '';
        $is_mobile = stripos($ua_raw, 'Mobile') !== false || stripos($ua_raw, 'Android') !== false;
        $icon     = $is_mobile ? 'device-mobile' : 'device-desktop';
        $browser  = 'Browser';
        if (stripos($ua_raw, 'Firefox') !== false)       $browser = 'Firefox';
        elseif (stripos($ua_raw, 'Edg') !== false)       $browser = 'Edge';
        elseif (stripos($ua_raw, 'Chrome') !== false)    $browser = 'Chrome';
        elseif (stripos($ua_raw, 'Safari') !== false)    $browser = 'Safari';
        $os = 'Unknown';
        if (stripos($ua_raw, 'Windows') !== false)       $os = 'Windows';
        elseif (stripos($ua_raw, 'Android') !== false)   $os = 'Android';
        elseif (stripos($ua_raw, 'iPhone') !== false)    $os = 'iPhone';
        elseif (stripos($ua_raw, 'Mac') !== false)       $os = 'Mac';
        elseif (stripos($ua_raw, 'Linux') !== false)     $os = 'Linux';
        $is_current = $sess['id'] === $current_sess_id;
        $la_raw = $sess['last_activity'];
        if ($la_raw && is_numeric($la_raw)) {
            $last_act = date('d M Y H:i', (int)$la_raw);
        } elseif ($la_raw) {
            $last_act = date('d M Y H:i', strtotime($la_raw));
        } else {
            $last_act = '—';
        }
      ?>
      <div class="sesi-row">
        <div>
          <div class="sesi-device">
            <i class="ti ti-<?= $icon ?>" aria-hidden="true"></i>
            <?= $os ?> · <?= $browser ?>
            <?php if ($is_current): ?>
              <span class="badge-current">Sesi ini</span>
            <?php endif; ?>
          </div>
          <div class="sesi-meta"><?= htmlspecialchars($sess['ip_address'] ?? '—') ?> · <?= $last_act ?></div>
        </div>
        <?php if (!$is_current): ?>
        <form method="POST" style="margin:0">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="logout_session">
          <input type="hidden" name="session_id" value="<?= htmlspecialchars($sess['id']) ?>">
          <button type="submit" class="btn-logout-sess">Logout</button>
        </form>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>

      <?php
        $other_sessions = array_filter($active_sessions, fn($s) => $s['id'] !== $current_sess_id);
      ?>
      <?php if (count($other_sessions) > 0): ?>
      <form method="POST" style="margin:0">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="logout_all_sessions">
        <button type="submit" class="btn-logout-all">Logout Semua Perangkat Lain</button>
      </form>
      <?php endif; ?>
    <?php else: ?>
      <p style="font-size:.82rem;color:var(--text-dim);padding:.5rem 0">Tidak ada sesi aktif lain.</p>
    <?php endif; ?>
  </div>
</div>

</div><!-- /kolom kanan -->
</div><!-- /two-col -->

<?php layout_foot(); ?>