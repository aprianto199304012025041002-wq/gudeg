<?php
http_response_code(403);
// Baca ADMIN_EMAIL dari .env kalau ada
(function () {
    $env = __DIR__ . '/config/.env';
    if (!file_exists($env)) return;
    foreach (file($env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        if (trim($k) === 'ADMIN_EMAIL' && !defined('ADMIN_EMAIL')) define('ADMIN_EMAIL', trim($v));
    }
})();
if (!defined('ADMIN_EMAIL')) define('ADMIN_EMAIL', 'admin@dummy.com');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>GUDEG - Akses Ditolak</title>
<link rel="icon" type="image/x-icon" href="/assets/favicon.ico">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<link rel="apple-touch-icon" href="/assets/favicon.png">
<link rel="stylesheet" href="/assets/tabler/tabler-icons.min.css">
<!-- Apply theme before paint (no flash) -->
<script src="/assets/js/error-theme.js"></script>
<!-- Google Fonts dihapus: font-src 'self' di CSP tidak mengizinkan CDN eksternal (#75) -->
<link rel="stylesheet" href="/assets/css/error-403.css">
</head>
<body>

<button class="theme-fab" onclick="toggleTheme()" aria-label="Ganti tema">
  <i class="ti ti-sun theme-toggle-icon" aria-hidden="true"></i>
</button>

  <div class="shield"><i class="ti ti-shield-lock"></i></div>
  <div class="err-code">403</div>
  <div class="err-label">Akses Ditolak</div>
  <div class="err-title">Tidak Ada Izin</div>
  <div class="divider"></div>
  <div class="err-desc">
    Anda tidak memiliki izin untuk mengakses halaman atau aplikasi ini.
    Hubungi administrator untuk mendapatkan akses.
  </div>
  <div class="btn-col">
    <a class="btn-primary" href="/dashboard.php">Kembali ke Dashboard</a>
    <a class="btn-secondary" href="mailto:<?= htmlspecialchars(ADMIN_EMAIL) ?>">Hubungi Administrator</a>
  </div>
  <div class="err-meta">GUDEG JOGJA &middot; Balmon Yogyakarta</div>
</body>
</html>