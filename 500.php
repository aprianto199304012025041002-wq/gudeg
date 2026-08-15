<?php http_response_code(500); ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>GUDEG - Server Error</title>
<link rel="icon" type="image/x-icon" href="/assets/favicon.ico">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<link rel="apple-touch-icon" href="/assets/favicon.png">
<link rel="stylesheet" href="/assets/tabler/tabler-icons.min.css">
<!-- Apply theme before paint (no flash) -->
<script src="/assets/js/error-theme.js"></script>
<!-- Google Fonts dihapus: font-src 'self' di CSP tidak mengizinkan CDN eksternal (#75) -->
<link rel="stylesheet" href="/assets/css/error-500.css">
</head>
<body>

<button class="theme-fab" onclick="toggleTheme()" aria-label="Ganti tema">
  <i class="ti ti-sun theme-toggle-icon" aria-hidden="true"></i>
</button>

  <div class="shield"><i class="ti ti-server-off"></i></div>
  <div class="err-code">500</div>
  <div class="err-label">Kesalahan Server</div>
  <div class="err-title">Terjadi Gangguan</div>
  <div class="divider"></div>
  <div class="err-desc">
    Terjadi kesalahan pada server.
    Silakan coba beberapa saat lagi atau hubungi administrator.
  </div>
  <div class="btn-col">
    <button class="btn-primary" onclick="location.reload()">Coba Lagi</button>
    <a class="btn-secondary" href="/login.php">Kembali ke Login</a>
  </div>
  <div class="err-meta">GUDEG JOGJA &middot; Balmon Yogyakarta</div>
</body>
</html>