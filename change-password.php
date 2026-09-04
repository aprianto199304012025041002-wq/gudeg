<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/LocalCache.php';
require_once __DIR__ . '/includes/layout.php';
require_login();

$user  = get_current_user_data();
$sb    = getSupabase();
$error = '';
$ok    = false;
$sess_msg = '';
$sess_msg_type = 'success';

// Handle logout sesi lain
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout_session') {
    csrf_verify();
    $target_sess = $_POST['session_id'] ?? '';
    if ($target_sess && $target_sess !== session_id()) {
        try {
            $sb->delete('sessions', ['id' => $target_sess, 'user_id' => $user['id']]);
            $sess_msg = 'Sesi berhasil diakhiri.';
        } catch (\Throwable $e) {
            gudeg_log('WARNING', 'change-password::logout_session', 'Gagal hapus session: ' . $e->getMessage());
            $sess_msg = 'Gagal mengakhiri sesi. Coba lagi.';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout_all_sessions') {
    csrf_verify();
    try {
        $sb->delete('sessions', ['user_id' => $user['id'], 'id' => 'neq.' . session_id()]);
        $sess_msg = 'Semua sesi lain berhasil diakhiri.';
    } catch (\Throwable $e) {
        gudeg_log('WARNING', 'change-password::logout_all_sessions', 'Gagal hapus semua sessions: ' . $e->getMessage());
        $sess_msg = 'Gagal mengakhiri sesi lain. Coba lagi.';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $old  = $_POST['old_password'] ?? '';
    $new  = $_POST['new_password'] ?? '';
    $conf = $_POST['confirm'] ?? '';

    $row     = $sb->selectOne('users', ['id' => $user['id']], 'password');
    $current = $row['password'] ?? '';

    if (!password_verify($old, $current)) {
        $error = 'Password lama tidak cocok.';
    } elseif ($strength_err = validate_password_strength($new)) {
        $error = $strength_err;
    } elseif ($new !== $conf) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        $hash = password_hash($new, PASSWORD_BCRYPT);
        $sb->update('users', ['id' => $user['id']], [
            'password'            => $hash,
            'password_changed_at' => date('Y-m-d H:i:s'),
        ]);
        log_activity($user['id'], $user['name'], $user['email'], 'Ubah Kata Sandi Sendiri');

        // Hapus cache user -- paksa fetch ulang dari DB saat login berikutnya
        LocalCache::clearUser((string)$user['id']);

        try {
            $sb->delete('sessions', ['user_id' => $user['id'], 'id' => 'neq.' . session_id()]);
        } catch (\Throwable $e) {
            gudeg_log('WARNING', 'change-password::change_pass', 'Gagal hapus sessions lain setelah ganti password: ' . $e->getMessage());
        }

        $ok = true;
    }
}

// Ambil 10 log aktivitas terakhir user
$user_logs = $sb->select('user_logs', ['user_id' => $user['id']], 'activity,ip_address,created_at', [
    'limit' => 10,
    'order' => 'id.desc',
]);

// Pastikan sesi sekarang tersimpan di DB
$current_sess_id = session_id();
$ua_now  = $_SERVER['HTTP_USER_AGENT'] ?? '';
$ip_now  = get_ip();
$now_str = date('Y-m-d H:i:s');

$existing_session = $sb->selectOne('sessions', ['id' => $current_sess_id], 'id');
$session_data = [
    'user_id'       => (int)$user['id'],
    'ip_address'    => $ip_now,
    'user_agent'    => $ua_now,
    'last_activity' => $now_str,
];
if ($existing_session) {
    $sb->update('sessions', ['id' => $current_sess_id], $session_data);
} else {
    $session_data['id']         = $current_sess_id;
    $session_data['payload']    = '';
    $session_data['created_at'] = $now_str;
    $sb->insert('sessions', $session_data);
}

// Ambil sesi aktif user
$active_sessions = $sb->select('sessions', ['user_id' => $user['id']], 'id,ip_address,user_agent,created_at,last_activity', [
    'order' => 'last_activity.desc',
]);

layout_head('Setting', 'password');
?>
<style nonce="<?= csp_nonce() ?>">
.form-group { margin-bottom: 16px; }
.text-note { font-size: 13px; color: var(--muted-hi); padding: 8px 0; font-family: var(--sans); }
.form-reset { margin: 0; }

#pw-hint {
  margin-top: 8px; font-size: 11px; line-height: 1.9; color: var(--muted);
  font-family: var(--sans); padding: 8px 12px;
  background: rgba(0,0,0,.12); border-radius: var(--radius-sm);
  border: 1px solid var(--glass-border);
}
[data-theme="bright"] #pw-hint { background: rgba(0,0,0,.03); }
#pw-hint span { display: block; }

.form-group label {
  display: block; font-size: 11px; font-weight: 600;
  color: var(--muted-hi); letter-spacing: .04em;
  margin-bottom: 6px; font-family: var(--sans);
}
.form-group input[type="password"] {
  width: 100%;
  background: rgba(0,0,0,.2);
  border: 1px solid var(--glass-border-hi);
  border-radius: var(--radius-sm);
  padding: 10px 13px;
  color: var(--text); font-family: var(--sans); font-size: 14px;
  outline: none;
  transition: border-color var(--transition-fast), box-shadow var(--transition-fast), background var(--transition-fast);
  -webkit-appearance: none;
  -webkit-box-shadow: 0 0 0 1000px rgba(5,8,15,.8) inset;
  -webkit-text-fill-color: var(--text);
  box-sizing: border-box;
  box-shadow: var(--elev-inset);
}
[data-theme="bright"] .form-group input[type="password"] {
  background: rgba(255,255,255,.7);
  -webkit-box-shadow: 0 0 0 1000px rgba(255,255,255,.9) inset;
}
.form-group input[type="password"]:focus {
  border-color: var(--accent-bdr);
  background: var(--accent-bg);
  box-shadow: 0 0 0 3px var(--accent-glow-sm), var(--elev-inset);
}

/* -- Tombol Simpan -- */
.btn-save {
  width: 100%;
  background: var(--accent-deep);
  color: #ffffff;
  border: 1px solid rgba(74,144,217,.35);
  border-radius: var(--radius-sm);
  padding: 11px;
  font-family: var(--sans);
  font-size: 14px; font-weight: 600;
  letter-spacing: .03em;
  cursor: pointer; margin-top: 8px;
  box-shadow: 0 0 16px var(--accent-glow-sm), var(--elev-2);
  transition: background var(--transition-fast), border-color var(--transition-fast), box-shadow var(--transition-fast);
  -webkit-tap-highlight-color: transparent;
}
.btn-save:hover {
  background: var(--accent);
  border-color: var(--accent);
  box-shadow: 0 0 24px var(--accent-glow), var(--elev-3);
}
.btn-save:active { opacity: .88; }
[data-theme="bright"] .btn-save { background: var(--accent-deep); color: #ffffff; }
[data-theme="bright"] .btn-save:hover { background: #1a5fcc; }

/* -- Layout dua kolom -- */
.two-col {
  display: grid; grid-template-columns: 1fr 1fr;
  gap: 16px; margin-bottom: 16px; align-items: start;
}
.two-col > div { display: flex; flex-direction: column; gap: 10px; min-width: 0; }
@media (max-width: 767px) { .two-col { grid-template-columns: 1fr; } }

@media (max-width: 767px) {
  .panel-body { padding: 10px 12px; }
  .panel-hd   { padding: 6px 12px; }
  .btn-save   { padding: 10px; }
  .btn-logout-all { padding: 8px; }
  .log-meta { font-size: 10px; }
  .sesi-meta { font-size: 10px; }
  .sesi-row { flex-wrap: wrap; }
  .sesi-row > div:first-child { width: 100%; }
}
@media (max-width: 400px) {
  .log-row { flex-direction: column; align-items: flex-start; gap: 4px; }
  .log-meta { text-align: left; }
}

/* -- Panel -- */
.panel {
  background: var(--glass-hi);
  backdrop-filter: blur(20px) saturate(160%);
  -webkit-backdrop-filter: blur(20px) saturate(160%);
  border: 1px solid var(--glass-border-hi);
  border-radius: var(--radius);
  overflow: hidden; min-width: 0;
  box-shadow: var(--elev-3), var(--elev-inset);
  position: relative;
}
.panel::before {
  content: '';
  position: absolute; top: 0; left: 0; right: 0; height: 1px;
  background: linear-gradient(90deg, transparent, var(--glass-border-hi), transparent);
  pointer-events: none; z-index: 1;
}
.panel-hd {
  padding: 8px 14px;
  border-bottom: 1px solid var(--glass-border);
  display: flex; align-items: center; gap: 8px;
  background: var(--accent-bg);
}
.panel-hd i { font-size: 15px; color: var(--accent); }
.panel-title {
  font-size: 12px; font-weight: 700;
  color: var(--muted-hi); font-family: var(--sans);
  letter-spacing: .02em;
}
.panel-body { padding: 12px 14px; }

/* -- Log aktivitas -- */
.log-row {
  display: flex; align-items: center; justify-content: space-between;
  padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,.04); gap: 8px;
}
[data-theme="bright"] .log-row { border-bottom-color: rgba(0,0,0,.06); }
.log-row:last-child { border-bottom: none; }
.log-activity {
  font-size: 13px; font-weight: 600; font-family: var(--sans);
  color: var(--text); word-break: break-word; overflow-wrap: anywhere; min-width: 0;
}
.log-meta {
  color: var(--muted-hi); font-family: var(--sans);
  font-size: 11px; text-align: right; flex-shrink: 0; line-height: 1.6;
}

/* -- Sesi aktif -- */
.sesi-row {
  display: flex; align-items: center; justify-content: space-between;
  padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,.04); gap: 8px;
}
[data-theme="bright"] .sesi-row { border-bottom-color: rgba(0,0,0,.06); }
.sesi-row:last-child { border-bottom: none; }
.sesi-device {
  font-size: 13px; font-weight: 600; font-family: var(--sans);
  color: var(--text);
  display: flex; align-items: center; gap: 8px;
  word-break: break-word; overflow-wrap: anywhere; min-width: 0;
}
.sesi-device i { font-size: 14px; color: var(--accent); flex-shrink: 0; }
.sesi-meta { font-size: 11px; color: var(--muted-hi); margin-top: 2px; word-break: break-all; font-family: var(--sans); }
.sesi-row > div:first-child { min-width: 0; flex: 1 1 0; }
.badge-current {
  display: inline-block; font-size: 10px; padding: 1px 7px; border-radius: 4px;
  background: var(--accent-bg); color: var(--accent);
  border: 1px solid var(--accent-bdr);
  margin-left: 4px; white-space: nowrap; font-family: var(--sans);
  font-weight: 600;
}
.btn-logout-sess {
  background: rgba(224,82,82,.08); border: 1px solid rgba(224,82,82,.25);
  color: var(--danger); border-radius: var(--radius-sm); padding: 5px 11px;
  font-size: 12px; font-weight: 600; font-family: var(--sans);
  cursor: pointer; white-space: nowrap; flex-shrink: 0;
  transition: box-shadow var(--transition-fast), border-color var(--transition-fast), background var(--transition-fast);
  box-shadow: var(--elev-inset);
}
.btn-logout-sess:hover { border-color: var(--danger); background: rgba(224,82,82,.14); }
.btn-logout-all {
  width: 100%; background: transparent; border: 1px solid rgba(224,82,82,.35);
  color: var(--danger); border-radius: var(--radius-sm); padding: 10px;
  font-size: 12px; font-weight: 500; cursor: pointer; font-family: var(--sans);
  margin-top: 12px; box-sizing: border-box;
  transition: background var(--transition-fast), border-color var(--transition-fast);
}
.btn-logout-all:hover { background: rgba(224,82,82,.08); border-color: var(--danger); }

/* -- Bubble -- */
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
  font-size: 13px; line-height: 1.5; border: 1px solid;
  display: flex; align-items: flex-start; gap: 8px;
  pointer-events: all; animation: bubbleIn .25s ease;
  box-shadow: var(--elev-3); position: relative;
  backdrop-filter: blur(20px) saturate(160%); -webkit-backdrop-filter: blur(20px) saturate(160%);
  font-family: var(--sans);
}
@keyframes bubbleIn {
  from { opacity: 0; transform: translateY(10px); }
  to   { opacity: 1; transform: translateY(0); }
}
.bubble.success { background: rgba(52,211,153,.10); border-color: rgba(52,211,153,.28); color: #6ee7b7; }
.bubble.danger  { background: rgba(224,82,82,.12);  border-color: rgba(224,82,82,.30);  color: #fca5a5; }
.bubble.info    { background: var(--accent-bg);     border-color: var(--accent-bdr);    color: var(--accent); }
[data-theme="bright"] .bubble.success { color: #065f46; }
[data-theme="bright"] .bubble.danger  { color: #991b1b; }
[data-theme="bright"] .bubble.info    { color: var(--accent-deep); }
.bubble i { font-size: 16px; flex-shrink: 0; margin-top: 1px; }
.bubble-body { flex: 1; min-width: 0; }
.bubble-title { font-weight: 700; letter-spacing: .03em; margin-bottom: 2px; font-size: .72rem; }
.bubble-desc  { font-size: .78rem; font-weight: 500; opacity: .9; }
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
  <h1 class="page-title">Pengaturan</h1>
  <p class="page-subtitle">Masuk sebagai <?= htmlspecialchars($user['email']) ?></p>
</div>

<div class="two-col">
<div><!-- kolom kiri: ubah password + sesi -->

<!-- Panel: Ubah Kata Sandi -->
<div class="panel">
  <div class="panel-hd">
    <i class="ti ti-key" aria-hidden="true"></i>
    <span class="panel-title">Ubah Kata Sandi</span>
  </div>
  <div class="panel-body">
    <?php if (!$ok): ?>
    <form method="POST">
      <?= csrf_field() ?>
      <div class="form-group">
        <label for="old_password">Kata Sandi Lama</label>
        <input type="password" id="old_password" name="old_password"
               required autocomplete="current-password">
      </div>
      <div class="form-group">
        <label for="new_password">Kata Sandi Baru</label>
        <input type="password" id="new_password" name="new_password"
               placeholder="Min. 8 karakter" required autocomplete="new-password"
               oninput="checkStrength(this.value)">
        <div id="pw-hint">
          <span id="pw-len">&#9675; Min. 8 karakter</span>
          <span id="pw-upper">&#9675; Huruf besar (A-Z)</span>
          <span id="pw-lower">&#9675; Huruf kecil (a-z)</span>
          <span id="pw-num">&#9675; Angka (0-9)</span>
          <span id="pw-sym">&#9675; Karakter spesial (!, @, #, dll)</span>
        </div>
      </div>
      <script nonce="<?= csp_nonce() ?>">
      function checkStrength(v) {
        function set(id, ok, label) {
          var el = document.getElementById(id);
          el.textContent = (ok ? '\u25CF ' : '\u25CB ') + label;
          el.style.color = ok ? 'var(--success)' : 'var(--muted)';
        }
        set('pw-len',   v.length >= 8,           'Min. 8 karakter');
        set('pw-upper', /[A-Z]/.test(v),         'Huruf besar (A-Z)');
        set('pw-lower', /[a-z]/.test(v),         'Huruf kecil (a-z)');
        set('pw-num',   /[0-9]/.test(v),         'Angka (0-9)');
        set('pw-sym',   /[^a-zA-Z0-9]/.test(v), 'Karakter spesial (!, @, #, dll)');
      }
      </script>
      <div class="form-group">
        <label for="confirm">Konfirmasi Kata Sandi Baru</label>
        <input type="password" id="confirm" name="confirm"
               required autocomplete="new-password">
      </div>
      <button type="submit" class="btn-save" id="btn-save-pw">Simpan Kata Sandi</button>
    </form>
    <script nonce="<?= csp_nonce() ?>">
    (function() {
      var f = document.querySelector('form[method="POST"]:not(.form-reset)');
      if (!f) return;
      f.addEventListener('submit', function() {
        var btn = document.getElementById('btn-save-pw');
        if (btn) { btn.disabled = true; btn.textContent = 'Menyimpan\u2026'; }
      });
    })();
    </script>
    <?php else: ?>
      <p class="text-note">Kata sandi berhasil diubah. Semua sesi lain telah diakhiri.</p>
    <?php endif; ?>
  </div>
</div>

<!-- Panel: Sesi Aktif -->
<div class="panel">
  <div class="panel-hd">
    <i class="ti ti-device-laptop" aria-hidden="true"></i>
    <span class="panel-title">Sesi Aktif</span>
  </div>
  <div class="panel-body">
    <?php if ($active_sessions): ?>
      <?php foreach ($active_sessions as $sess): ?>
      <?php
        $ua_raw    = $sess['user_agent'] ?? '';
        $is_mobile = stripos($ua_raw, 'Mobile') !== false || stripos($ua_raw, 'Android') !== false;
        $icon      = $is_mobile ? 'device-mobile' : 'device-desktop';
        $browser   = 'Browser';
        if (stripos($ua_raw, 'Firefox') !== false)    $browser = 'Firefox';
        elseif (stripos($ua_raw, 'Edg') !== false)    $browser = 'Edge';
        elseif (stripos($ua_raw, 'Chrome') !== false) $browser = 'Chrome';
        elseif (stripos($ua_raw, 'Safari') !== false) $browser = 'Safari';
        $os = 'Unknown';
        if (stripos($ua_raw, 'Windows') !== false)    $os = 'Windows';
        elseif (stripos($ua_raw, 'Android') !== false) $os = 'Android';
        elseif (stripos($ua_raw, 'iPhone') !== false) $os = 'iPhone';
        elseif (stripos($ua_raw, 'Mac') !== false)    $os = 'Mac';
        elseif (stripos($ua_raw, 'Linux') !== false)  $os = 'Linux';
        $is_current = $sess['id'] === $current_sess_id;
        $la_raw = $sess['last_activity'];
        if ($la_raw && is_numeric($la_raw)) {
            $last_act = date('d M Y H:i', (int)$la_raw);
        } elseif ($la_raw) {
            $last_act = date('d M Y H:i', strtotime($la_raw));
        } else {
            $last_act = '-';
        }
      ?>
      <div class="sesi-row">
        <div>
          <div class="sesi-device">
            <i class="ti ti-<?= $icon ?>" aria-hidden="true"></i>
            <?= $os ?> &middot; <?= $browser ?>
            <?php if ($is_current): ?>
              <span class="badge-current">Sesi ini</span>
            <?php endif; ?>
          </div>
          <div class="sesi-meta"><?= htmlspecialchars($sess['ip_address'] ?? '-') ?> &middot; <?= $last_act ?></div>
        </div>
        <?php if (!$is_current): ?>
        <form method="POST" class="form-reset" id="form-logout-sess-<?= htmlspecialchars($sess['id']) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="logout_session">
          <input type="hidden" name="session_id" value="<?= htmlspecialchars($sess['id']) ?>">
          <button type="button" class="btn-logout-sess"
                  data-action="confirm"
                  data-title="Keluar Perangkat"
                  data-msg="Keluar dari sesi ini?"
                  data-label="Ya, Keluar"
                  data-cls="danger"
                  data-target="form-logout-sess-<?= htmlspecialchars($sess['id']) ?>">
            Logout
          </button>
        </form>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>

      <?php
        $other_sessions = array_filter($active_sessions, fn($s) => $s['id'] !== $current_sess_id);
      ?>
      <?php if (count($other_sessions) > 0): ?>
      <form method="POST" class="form-reset" id="form-logout-all">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="logout_all_sessions">
        <button type="button" class="btn-logout-all"
                data-action="confirm"
                data-title="Keluar Semua Perangkat"
                data-msg="Keluar dari semua perangkat lain yang sedang aktif? Sesi ini tidak akan terpengaruh."
                data-label="Ya, Keluar Semua"
                data-cls="danger"
                data-target="form-logout-all">
          Keluar Semua Perangkat Lain
        </button>
      </form>
      <?php endif; ?>
    <?php else: ?>
      <p class="text-note">Tidak ada sesi aktif lain.</p>
    <?php endif; ?>
  </div>
</div>

</div><!-- /kolom kiri -->
<div><!-- kolom kanan: log aktivitas -->

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
      <p class="text-note">Belum ada aktivitas tercatat.</p>
    <?php endif; ?>
  </div>
</div>

</div><!-- /kolom kanan -->
</div><!-- /two-col -->

<div class="bubble-wrap" id="bubbleWrap"></div>

<script nonce="<?= csp_nonce() ?>">
function showBubble(type, icon, title, desc, duration) {
  duration = duration || 6000;
  var wrap = document.getElementById('bubbleWrap');
  var b = document.createElement('div');
  b.className = 'bubble ' + type;
  b.innerHTML =
    '<i class="ti ti-' + icon + '" aria-hidden="true"></i>' +
    '<div class="bubble-body"><div class="bubble-title">' + title + '</div>' +
    '<div class="bubble-desc">' + desc + '</div></div>' +
    '<button class="bubble-close" aria-label="Tutup" data-action="close-bubble">&#215;</button>' +
    '<div class="bubble-progress" style="animation-duration:' + (duration/1000) + 's"></div>';
  wrap.appendChild(b);
  b.querySelector('[data-action="close-bubble"]').addEventListener('click', function() { closeBubble(b); });
  setTimeout(function() { closeBubble(b); }, duration);
}
function closeBubble(b) {
  if (!b || !b.parentNode) return;
  b.style.transition = 'opacity .3s, transform .3s';
  b.style.opacity = '0';
  b.style.transform = 'translateY(10px)';
  setTimeout(function() { if (b.parentNode) b.remove(); }, 320);
}

<?php if ($ok): ?>
showBubble('success', 'check', 'Kata Sandi Diubah', 'Kata sandi berhasil diubah. Semua sesi lain telah diakhiri.');
<?php elseif ($error): ?>
showBubble('danger', 'alert-circle', 'Gagal', <?= json_encode(htmlspecialchars($error)) ?>);
<?php endif; ?>

<?php if ($sess_msg): ?>
showBubble('info', 'device-laptop', 'Sesi', <?= json_encode(htmlspecialchars($sess_msg)) ?>);
<?php endif; ?>
</script>

<?php layout_foot(); ?>
