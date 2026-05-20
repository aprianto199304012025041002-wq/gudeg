<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();

$user = get_current_user_data();
$db = getDB();
$error = '';
$success = '';

// Jika ada logic POST (tambah/hapus app), biarkan di sini
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... logic aslimu ...
}

$apps = $db->query("SELECT * FROM apps ORDER BY id DESC")->fetchAll();

layout_head('Aplikasi SSO', 'admin-apps');
?>

<div class="page-header">
  <h1>Aplikasi SSO</h1>
  <p>Kelola client ID dan Secret untuk integrasi aplikasi</p>
</div>

<!-- Form Tambah App -->
<div class="card" style="margin-bottom:24px;">
  <h2 style="font-family:var(--font-head);font-size:1.1rem;margin-bottom:16px;">Tambah Aplikasi Baru</h2>

  <?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

  <form method="POST" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
    <!-- Pastikan name atribut cocok dengan form-mu aslinya -->
    <div class="form-group" style="margin:0;">
      <label class="form-label">Nama Aplikasi</label>
      <input class="form-control" type="text" name="name" placeholder="Misal: SIMPEG" required/>
    </div>
    <div class="form-group" style="margin:0;">
      <label class="form-label">Redirect Callback URL</label>
      <input class="form-control" type="url" name="redirect_url" placeholder="https://..." required/>
    </div>
    <div style="grid-column:1/-1;">
      <button type="submit" class="btn btn-primary" name="action" value="add_app">+ Daftarkan Aplikasi</button>
    </div>
  </form>
</div>

<!-- List Apps -->
<div style="display:grid;gap:16px;">
  <?php foreach ($apps as $app): ?>
  <div class="card">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:16px;">
      <div>
        <h3 style="font-family:var(--font-head);font-size:1.2rem;margin-bottom:4px;"><?= htmlspecialchars($app['name']) ?></h3>
        <p style="font-size:.85rem;color:var(--text-muted);"><span style="font-weight:600;">Callback:</span> <?= htmlspecialchars($app['redirect_url']) ?></p>
      </div>
      <form method="POST" action="/admin/delete-app.php" onsubmit="return confirm('Yakin hapus aplikasi <?= htmlspecialchars($app['name']) ?>?')">
        <input type="hidden" name="app_id" value="<?= $app['id'] ?>"/>
        <button type="submit" class="btn btn-danger btn-sm">Hapus App</button>
      </form>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:16px;margin-top:20px;">
      <div>
        <div class="form-label">Client ID</div>
        <div style="background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:10px 14px;font-family:monospace;font-size:.9rem;color:var(--accent);">
          <?= htmlspecialchars($app['client_id']) ?>
        </div>
      </div>
      <div>
        <div class="form-label">Client Secret</div>
        <div style="position:relative;">
          <div class="secret-field" 
               style="background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:10px 40px 10px 14px;font-family:monospace;font-size:.9rem;color:#fff;filter:blur(5px);transition:all .2s;cursor:pointer;user-select:none;"
               onclick="toggleSecret(this)" title="Klik untuk lihat rahasia">
            <?= htmlspecialchars($app['client_secret']) ?>
          </div>
          <svg style="position:absolute;right:14px;top:50%;transform:translateY(-50%);pointer-events:none;color:var(--text-muted);"
               width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
          </svg>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<script>
function toggleSecret(el) {
  const blurred = el.style.filter === 'blur(5px)';
  el.style.filter = blurred ? 'none' : 'blur(5px)';
  el.style.userSelect = blurred ? 'text' : 'none';
}
</script>

<?php layout_foot(); ?>