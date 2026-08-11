<?php http_response_code(403); ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>GUDEG - Akses Ditolak</title>
<link rel="icon" type="image/png" href="/assets/favicon.png">
<link rel="apple-touch-icon" href="/assets/favicon.png">
<link rel="stylesheet" href="/assets/tabler/tabler-icons.min.css">
<!-- Apply theme before paint (no flash) -->
<script>
(function(){
  try {
    var t = localStorage.getItem('gudeg_theme') || 'dark';
    if (t === 'light') { t = 'bright'; localStorage.setItem('gudeg_theme', 'bright'); }
    document.documentElement.setAttribute('data-theme', t);
  } catch(e) {}
})();
</script>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=DM+Sans:opsz,wght@9..40,300;400;500;600&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%}

:root,[data-theme="dark"]{
  --bg:#080b10;--surface:#0f1520;--border:#1e2a3a;--border-hi:#2a3a50;
  --accent:#00e5ff;--accent-glow:rgba(0,229,255,.18);--accent-bg:rgba(0,229,255,.06);--accent-bdr:rgba(0,229,255,.2);
  --text:#f1f5f9;--muted-hi:#6b8099;--muted:#3a5070;
  --danger:#ef4444;--danger-glow:rgba(239,68,68,.18);
  --sans:'DM Sans','Segoe UI',system-ui,-apple-system,sans-serif;
  --mono:'Space Mono','Courier New',monospace;
  --glass-hi:rgba(15,21,32,.95);
  --shadow-float:0 8px 32px rgba(0,0,0,.7),0 2px 8px rgba(0,0,0,.5),0 0 0 1px rgba(255,255,255,.04);
  --radius:8px;--transition-fast:.12s cubic-bezier(.4,0,.2,1);
}
[data-theme="bright"]{
  --bg:#f0f4f8;--surface:#ffffff;--border:#d1dbe8;--border-hi:#b8c8da;
  --accent:#0284c7;--accent-glow:rgba(2,132,199,.12);--accent-bg:rgba(2,132,199,.07);--accent-bdr:rgba(2,132,199,.25);
  --text:#0f172a;--muted-hi:#475569;--muted:#94a3b8;
  --danger:#dc2626;--danger-glow:rgba(220,38,38,.12);
  --glass-hi:rgba(255,255,255,.97);
  --shadow-float:0 4px 20px rgba(0,0,0,.1),0 1px 4px rgba(0,0,0,.07),0 0 0 1px rgba(0,0,0,.05);
}

body{
  background:var(--bg);color:var(--text);font-family:var(--sans);
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  min-height:100vh;padding:2rem 1.25rem;text-align:center;
  transition:background .22s,color .22s;
}

.theme-fab{
  position:fixed;top:calc(14px + env(safe-area-inset-top));right:14px;z-index:999;
  display:flex;align-items:center;justify-content:center;
  width:36px;height:36px;
  background:var(--glass-hi);backdrop-filter:blur(16px) saturate(160%);
  border:1px solid var(--border-hi);border-radius:var(--radius);
  cursor:pointer;color:var(--muted-hi);
  box-shadow:var(--shadow-float);
  transition:border-color var(--transition-fast),color var(--transition-fast),box-shadow var(--transition-fast);
  -webkit-tap-highlight-color:transparent;
}
.theme-fab:hover{border-color:var(--accent);color:var(--accent);box-shadow:var(--shadow-float),0 0 12px var(--accent-glow);}
.theme-fab i{font-size:17px}

.shield{font-size:2rem;margin-bottom:1rem;opacity:.25}
.err-code{
  font-family:var(--mono);font-size:5rem;font-weight:700;
  letter-spacing:.05em;line-height:1;
  color:rgba(239,68,68,.18);margin-bottom:.5rem;
}
.err-label{
  font-family:var(--mono);font-size:.75rem;font-weight:700;
  text-transform:uppercase;letter-spacing:.14em;
  color:var(--danger);opacity:.7;margin-bottom:1rem;
}
.err-title{
  font-family:var(--mono);font-size:.95rem;font-weight:700;
  text-transform:uppercase;letter-spacing:.06em;margin-bottom:.75rem;
}
.divider{width:32px;height:1px;background:var(--border);margin:.75rem auto;}
.err-desc{
  font-size:.9rem;color:var(--muted-hi);line-height:1.7;
  max-width:300px;margin:0 auto 1.5rem;
}
.btn-primary{
  display:inline-block;background:var(--accent-bg);border:1px solid var(--accent-bdr);
  color:var(--accent);border-radius:var(--radius);
  padding:.65rem 1.5rem;font-family:var(--mono);font-size:.78rem;font-weight:700;
  text-transform:uppercase;letter-spacing:.08em;text-decoration:none;
  transition:background var(--transition-fast),border-color var(--transition-fast),box-shadow var(--transition-fast);
}
.btn-primary:hover{background:rgba(0,229,255,.12);border-color:var(--accent);box-shadow:0 0 16px var(--accent-glow);}
[data-theme="bright"] .btn-primary:hover{background:rgba(2,132,199,.1);box-shadow:0 0 12px var(--accent-glow);}
.btn-secondary{
  display:inline-block;background:none;border:1px solid var(--border);
  color:var(--muted-hi);border-radius:var(--radius);
  padding:.65rem 1.5rem;font-family:var(--mono);font-size:.78rem;font-weight:700;
  text-transform:uppercase;letter-spacing:.08em;text-decoration:none;margin-top:.5rem;
  transition:border-color var(--transition-fast),color var(--transition-fast);
}
.btn-secondary:hover{border-color:var(--border-hi);color:var(--text);}
.btn-col{display:flex;flex-direction:column;align-items:center;gap:0}
.err-meta{
  font-family:var(--mono);font-size:.7rem;color:var(--muted);
  margin-top:2rem;letter-spacing:.08em;text-transform:uppercase;
}
</style>
</head>
<body>

<button class="theme-fab" onclick="toggleTheme()" aria-label="Ganti tema">
  <i class="ti ti-sun theme-toggle-icon" aria-hidden="true"></i>
</button>

  <div class="shield">🛡</div>
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
    <a class="btn-secondary" href="mailto:admin@balmon-jogja.go.id">Hubungi Administrator</a>
  </div>
  <div class="err-meta">GUDEG JOGJA &middot; Balmon Yogyakarta</div>

<script>
(function() {
  var THEME_KEY = 'gudeg_theme';
  function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    try { localStorage.setItem(THEME_KEY, theme); } catch(e) {}
    document.querySelectorAll('.theme-toggle-icon').forEach(function(ic) {
      ic.className = 'ti ' + (theme === 'bright' ? 'ti-moon' : 'ti-sun') + ' theme-toggle-icon';
    });
  }
  function getTheme() {
    try { return localStorage.getItem(THEME_KEY) || 'dark'; } catch(e) { return 'dark'; }
  }
  window.toggleTheme = function() { applyTheme(getTheme() === 'dark' ? 'bright' : 'dark'); };
  applyTheme(getTheme());
})();
</script>
</body>
</html>