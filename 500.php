<?php http_response_code(500); ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>500 — Kesalahan Server · GUDEG JOGJA</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%}
body{
  background:#080a0e;
  color:#e2e8f0;
  font-family:'Courier New',monospace;
  font-weight:700;
  display:flex;
  flex-direction:column;
  align-items:center;
  justify-content:center;
  min-height:100vh;
  padding:2rem 1.25rem;
  text-align:center;
}
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
  font-size:.65rem;
  font-weight:700;
  text-transform:uppercase;
  letter-spacing:.14em;
  color:#64748b;
  margin-bottom:1rem;
}
.err-title{
  font-size:.85rem;
  font-weight:700;
  text-transform:uppercase;
  letter-spacing:.06em;
  margin-bottom:.75rem;
}
.divider{
  width:32px;height:1px;
  background:#1a1f2e;
  margin:.75rem auto;
}
.err-desc{
  font-size:.72rem;
  color:#64748b;
  line-height:1.7;
  max-width:280px;
  margin:0 auto 1.5rem;
}
.btn-primary{
  display:inline-block;
  background:rgba(122,162,212,.1);
  border:1px solid #7aa2d4;
  color:#7aa2d4;
  border-radius:2px;
  padding:.6rem 1.4rem;
  font-size:.7rem;
  font-weight:700;
  font-family:'Courier New',monospace;
  text-transform:uppercase;
  letter-spacing:.07em;
  text-decoration:none;
  transition:background .2s;
}
.btn-primary:hover{background:rgba(122,162,212,.18)}
.btn-secondary{
  display:inline-block;
  background:none;
  border:1px solid #1a1f2e;
  color:#64748b;
  border-radius:2px;
  padding:.6rem 1.4rem;
  font-size:.7rem;
  font-weight:700;
  font-family:'Courier New',monospace;
  text-transform:uppercase;
  letter-spacing:.07em;
  text-decoration:none;
  margin-top:.5rem;
  transition:border-color .2s;
}
.btn-secondary:hover{border-color:#64748b}
.btn-col{display:flex;flex-direction:column;align-items:center;gap:0}
.err-meta{
  font-size:.62rem;
  color:#64748b;
  opacity:.45;
  margin-top:2rem;
  letter-spacing:.06em;
  text-transform:uppercase;
}
</style>
</head>
<body>
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
</body>
</html>