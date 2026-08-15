<?php http_response_code(404); ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>GUDEG - Halaman Tidak Ditemukan</title>
<link rel="icon" type="image/x-icon" href="/assets/favicon.ico">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<link rel="apple-touch-icon" href="/assets/favicon.png">
<link rel="stylesheet" href="/assets/tabler/tabler-icons.min.css">
<!-- Apply theme before paint (no flash) -->
<script src="/assets/js/error-theme.js"></script>
<!-- Google Fonts dihapus: font-src 'self' di CSP tidak mengizinkan CDN eksternal (#75) -->
<link rel="stylesheet" href="/assets/css/error-404.css">
</head>
<body>

<button class="theme-fab" id="theme-fab-btn" aria-label="Ganti tema">
  <i class="ti ti-sun theme-toggle-icon" aria-hidden="true"></i>
</button>

  <div class="shield"><i class="ti ti-map-search"></i></div>
  <div class="err-code">404</div>
  <div class="err-label">Halaman Tidak Ditemukan</div>
  <div class="err-title">Alamat Tidak Valid</div>
  <div class="divider"></div>
  <div class="err-desc">
    Halaman yang Anda cari tidak ada atau telah dipindahkan.
    Periksa kembali alamat yang Anda masukkan.
  </div>
  <a class="btn-primary" href="/login.php">Kembali ke Login</a>
  <div class="err-meta">GUDEG JOGJA &middot; Balmon Yogyakarta</div>
</body>
</html>