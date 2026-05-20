<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /dashboard.php'); exit;
}

$pageTitle  = 'Aplikasi SSO';
$activePage = 'admin-apps';
$isAdmin    = true;

$error   = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // [fungsi tambah/hapus app tidak diubah — koneksikan ke logic asli]
}

include __DIR__ . '/../includes/layout.php';

// [ambil dari DB — dummy:]
$apps = [
    ['id'=>1,'name'=>'SIMPEG','redirect_url'=>'https://simpeg.example.com/callback',
     'client_id'=>'cid_sim001','client_secret'=>'cs_xKj9mNpQ2rT8vWy4','created_at'=>'2025-01-10'],
    ['id'=>2,'name'=>'E-Office','redirect_url'=>'https://eoffice.example.com/sso',
     'client_id'=>'cid_eof002','client_secret'=>'cs_aB3dEf6gHi0jKl5m','created_at'=>'2025-02-20'],
];
?>

<div class="page-header">
  <h1>Aplikasi SSO</h1>
  <p>Kelola integrasi aplikasi Single Sign-On</p>
</div>

<!-- Tambah app -->
<div class="card" style="margin-bottom:20px;">
  <div style="font-family:var(--font-head);font-size:.9rem;font-weight:700;margin-bottom:14px;">Tambah Aplikasi Baru</div>

  <?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

  <form method="POST" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
    <div class="form-group" style="margin:0;">
      <label class="form-label">Nama Aplikasi</label>
      <input class="form-control" type="text" name="name" placeholder="Nama aplikasi" required/>
    </div>
    <div class="form-group" style="margin:0;">
      <label class="form-label">Redirect URL</label>
      <input class="form-control" type="url" name="redirect_url" placeholder="https://..." required/>
    </div>
    <div style="grid-column:1/-1;">
      <button type="submit" class="btn btn-primary">+ Tambah Aplikasi</button>
    </div>
  </form>
</div>

<!-- List app -->
<div style="display:grid;gap:12px;">
  <?php foreach ($apps as $app): ?>
  <div class="card">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;">
      <div>
        <div style="font-family:var(--font-head);font-size:1rem;font-weight:700;"><?= htmlspecialchars($app['name']) ?></div>
        <div style="font-size:.78rem;color:var(--text-muted);margin-top:2px;"><?= htmlspecialchars($app['redirect_url']) ?></div>
      </div>
      <form method="POST" action="/admin/delete-app.php"
            onsubmit="return confirm('Hapus aplikasi <?= htmlspecialchars($app['name']) ?>?')">
        <input type="hidden" name="app_id" value="<?= $app['id'] ?>"/>
        <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
      </form>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:14px;">
      <div>
        <div class="form-label" style="margin-bottom:4px;">Client ID</div>
        <div style="background:var(--bg3);border:1px solid var(--border-hi);border-radius:7px;padding:8px 11px;font-family:monospace;font-size:.8rem;color:var(--text);">
          <?= htmlspecialchars($app['client_id']) ?>
        </div>
      </div>
      <div>
        <div class="form-label" style="margin-bottom:4px;">Client Secret</div>
        <div style="position:relative;">
          <div class="secret-field" data-secret="<?= htmlspecialchars($app['client_secret']) ?>"
               style="background:var(--bg3);border:1px solid var(--border-hi);border-radius:7px;
                      padding:8px 38px 8px 11px;font-family:monospace;font-size:.8rem;color:var(--text);
                      filter:blur(5px);transition:filter .2s;cursor:pointer;user-select:none;"
               onclick="toggleSecret(this)"
               title="Klik untuk tampilkan">
            <?= htmlspecialchars($app['client_secret']) ?>
          </div>
          <svg style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;color:var(--text-muted);"
               width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
            <circle cx="12" cy="12" r="3"/>
          </svg>
        </div>
      </div>
    </div>

    <div style="margin-top:10px;font-size:.76rem;color:var(--text-muted);">
      Terdaftar: <?= htmlspecialchars($app['created_at']) ?>
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

<?php include __DIR__ . '/../includes/layout_end.php'; ?>