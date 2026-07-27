<?php http_response_code(500); ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>500 — Kesalahan Server · GUDEG JOGJA</title>
<link rel="stylesheet" href="/assets/tabler/tabler-icons.min.css">
<!-- Apply theme before paint (no flash) -->
<script>
(function(){
  try {
    var t = localStorage.getItem('gudeg_theme') || 'dark';
    document.documentElement.setAttribute('data-theme', t);
  } catch(e) {}
})();
</script>
<style>
*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%}

/* DARK (default) */
:root,
[data-theme="dark"] {
  --bg:         #080a0e;
  --surface:    #0c0e13;
  --border:     #1a1f2e;
  --accent:     #7aa2d4;
  --text:       #e2e8f0;
  --text-dim:   #64748b;
  --sans:       'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
}

/* LIGHT */
[data-theme="light"] {
  --bg:         #f0f4f8;
  --surface:    #ffffff;
  --border:     #d0dae6;
  --accent:     #2a6ab4;
  --text:       #1e2e44;
  --text-dim:   #64748b;
}

body{
  background:var(--bg);
  color:var(--text);
  font-family:var(--sans);
  display:flex;
  flex-direction:column;
  align-items:center;
  justify-content:center;
  min-height:100vh;
  padding:2rem 1.25rem;
  text-align:center;
  transition:background .2s, color .2s;
}

/* Theme toggle FAB */
.theme-fab{
  position:fixed;
  top:calc(14px + env(safe-area-inset-top)); right:14px;
  z-index:999;
  display:flex;align-items:center;justify-content:center;
  width:36px;height:36px;
  background:var(--surface);
  border:1px solid var(--border);
  border-radius:8px;
  cursor:pointer;
  color:var(--text-dim);
  transition:border-color .15s, color .15s, background .2s;
  -webkit-tap-highlight-color:transparent;
}
.theme-fab:hover{border-color:var(--accent);color:var(--accent)}
.theme-fab i{font-size:17px}

.shield{font-size:2rem;margin-bottom:1rem;opacity:.3}
.err-code{
  font-size:4.5rem;
  font-weight:700;
  letter-spacing:.05em;
  line-height:1;
  color:rgba(100,116,139,.15);
  margin-bottom:.5rem;
}
.err-label{
  font-size:.8rem;
  font-weight:500;
  text-transform:uppercase;
  letter-spacing:.12em;
  color:var(--text-dim);
  margin-bottom:1rem;
}
.err-title{
  font-size:1rem;
  font-weight:600;
  text-transform:uppercase;
  letter-spacing:.05em;
  margin-bottom:.75rem;
}
.divider{
  width:32px;height:1px;
  background:var(--border);
  margin:.75rem auto;
}
.err-desc{
  font-size:.9rem;
  color:var(--text-dim);
  line-height:1.7;
  max-width:280px;
  margin:0 auto 1.5rem;
}
.btn-primary{
  display:inline-block;
  background:rgba(122,162,212,.1);
  border:1px solid var(--accent);
  color:var(--accent);
  border-radius:2px;
  padding:.6rem 1.4rem;
  font-size:.85rem;
  font-weight:500;
  font-family:var(--sans);
  text-transform:uppercase;
  letter-spacing:.07em;
  text-decoration:none;
  transition:background .2s;
}
.btn-primary:hover{background:rgba(122,162,212,.18)}
.btn-secondary{
  display:inline-block;
  background:none;
  border:1px solid var(--border);
  color:var(--text-dim);
  border-radius:2px;
  padding:.6rem 1.4rem;
  font-size:.85rem;
  font-weight:500;
  font-family:var(--sans);
  text-transform:uppercase;
  letter-spacing:.07em;
  text-decoration:none;
  margin-top:.5rem;
  transition:border-color .2s;
}
.btn-secondary:hover{border-color:var(--text-dim)}
.btn-col{display:flex;flex-direction:column;align-items:center;gap:0}
.err-meta{
  font-size:.75rem;
  color:var(--text-dim);
  opacity:.65;
  margin-top:2rem;
  letter-spacing:.06em;
  text-transform:uppercase;
}
</style>
</head>
<body>

<button class="theme-fab" onclick="toggleTheme()" aria-label="Ganti tema">
  <i class="ti ti-sun theme-toggle-icon" aria-hidden="true"></i>
</button>

  <div class="shield">🛡</div>
  <div class="err-code">500</div>
  <div class="err-label">Kesalahan Server</div>
  <div class="err-title">Terjadi Gangguan</div>
  <div class="divider"></div>
  <div class="err-desc">
    Terjadi kesalahan pada server. Tim teknis telah diberitahu.
    Silakan coba beberapa saat lagi.
  </div>
  <div class="btn-col">
    <a class="btn-primary" href="javascript:location.reload()">Coba Lagi</a>
    <a class="btn-secondary" href="/dashboard.php">Kembali ke Dashboard</a>
  </div>
  <div class="err-meta">GUDEG JOGJA &middot; Balmon Yogyakarta</div>

<script>
(function() {
  var THEME_KEY = 'gudeg_theme';
  function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    try { localStorage.setItem(THEME_KEY, theme); } catch(e) {}
    document.querySelectorAll('.theme-toggle-icon').forEach(function(ic) {
      ic.className = 'ti ' + (theme === 'light' ? 'ti-moon' : 'ti-sun') + ' theme-toggle-icon';
    });
  }
  function getTheme() {
    try { return localStorage.getItem(THEME_KEY) || 'dark'; } catch(e) { return 'dark'; }
  }
  window.toggleTheme = function() { applyTheme(getTheme() === 'dark' ? 'light' : 'dark'); };
  applyTheme(getTheme());
})();
</script>
</body>
</html>