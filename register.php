<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: /dashboard.php');
    exit;
}

$error   = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // [fungsi register tidak diubah — koneksikan ke logic asli]
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Daftar — GUDEG JOGJA</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    :root {
      --bg:       #080f1e;
      --bg2:      #0d1730;
      --bg3:      #111f3a;
      --surface:  #13233f;
      --border:   rgba(255,255,255,.07);
      --border-hi:rgba(255,255,255,.13);
      --text:     #e2eaf8;
      --muted:    #7a94b8;
      --accent:   #f5c518;
      --blue:     #2f80ed;
      --blue-dim: rgba(47,128,237,.18);
      --danger:   #e05252;
      --success:  #34c97e;
      --radius:   10px;
      --font-head:'Syne', sans-serif;
      --font-body:'DM Sans', sans-serif;
    }
    *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
    body {
      font-family: var(--font-body);
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      -webkit-font-smoothing: antialiased;
      position: relative;
      overflow: hidden;
      padding: 16px;
    }
    body::before {
      content: '';
      position: fixed;
      inset: 0;
      background:
        radial-gradient(ellipse 80% 60% at 85% 80%, rgba(47,128,237,.08) 0%, transparent 60%),
        radial-gradient(ellipse 60% 50% at 15% 20%, rgba(245,197,24,.06) 0%, transparent 55%);
      pointer-events: none;
    }
    body::after {
      content: '';
      position: fixed;
      inset: 0;
      background-image:
        linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px);
      background-size: 48px 48px;
      pointer-events: none;
      mask-image: radial-gradient(ellipse 70% 70% at 50% 50%, black, transparent);
    }

    .register-wrap {
      width: 100%;
      max-width: 400px;
      position: relative;
      z-index: 1;
    }

    .brand {
      text-align: center;
      margin-bottom: 24px;
    }
    .brand-gudeg { font-family:var(--font-head); font-size:2rem; font-weight:800; color:var(--accent); letter-spacing:.04em; line-height:1; }
    .brand-jogja { font-family:var(--font-head); font-size:2rem; font-weight:800; color:#fff; letter-spacing:.04em; }
    .brand-full  { font-size:.7rem; color:var(--muted); letter-spacing:.05em; line-height:1.5; text-transform:uppercase; margin-top:6px; }

    .card {
      background: var(--surface);
      border: 1px solid var(--border-hi);
      border-radius: 14px;
      padding: 26px 22px;
      box-shadow: 0 20px 60px rgba(0,0,0,.5);
    }
    .card h2 {
      font-family: var(--font-head);
      font-size: 1.05rem;
      font-weight: 700;
      margin-bottom: 18px;
    }

    .alert { border-radius:7px; padding:9px 13px; font-size:.84rem; margin-bottom:14px; }
    .alert-danger  { background:rgba(224,82,82,.12); border:1px solid rgba(224,82,82,.25); color:var(--danger); }
    .alert-success { background:rgba(52,201,126,.12); border:1px solid rgba(52,201,126,.25); color:var(--success); }

    .form-group { margin-bottom: 13px; }
    .form-label { display:block; font-size:.74rem; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:.05em; margin-bottom:5px; }
    .form-control {
      width:100%; background:var(--bg3); border:1px solid var(--border-hi); border-radius:7px;
      padding:10px 13px; color:var(--text); font-size:1rem; font-family:var(--font-body);
      outline:none; transition:border-color .15s, box-shadow .15s;
    }
    .form-control:focus { border-color:var(--blue); box-shadow:0 0 0 3px var(--blue-dim); }

    .btn-submit {
      width:100%; padding:11px; border:none; border-radius:8px;
      background:var(--blue); color:#fff; font-family:var(--font-head);
      font-size:.95rem; font-weight:700; letter-spacing:.03em;
      cursor:pointer; margin-top:6px; transition:opacity .15s, transform .1s;
    }
    .btn-submit:hover { opacity:.88; }
    .btn-submit:active { transform:scale(.97); }

    .card-foot { margin-top:14px; text-align:center; font-size:.82rem; color:var(--muted); }
    .card-foot a { color:var(--blue); font-weight:500; }
    .card-foot a:hover { text-decoration:underline; }
  </style>
</head>
<body>
<div class="register-wrap">
  <div class="brand">
    <div><span class="brand-gudeg">GUDEG</span><span class="brand-jogja"> JOGJA</span></div>
    <div class="brand-full">General Unified Database<br>and Employee Gateway</div>
  </div>

  <div class="card">
    <h2>Buat Akun Baru</h2>

    <?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label class="form-label" for="name">Nama Lengkap</label>
        <input class="form-control" type="text" id="name" name="name" placeholder="Nama lengkap" required/>
      </div>
      <div class="form-group">
        <label class="form-label" for="username">Username</label>
        <input class="form-control" type="text" id="username" name="username" placeholder="username" autocomplete="username" required/>
      </div>
      <div class="form-group">
        <label class="form-label" for="email">Email</label>
        <input class="form-control" type="email" id="email" name="email" placeholder="email@domain.com" required/>
      </div>
      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <input class="form-control" type="password" id="password" name="password" placeholder="Min. 8 karakter" required/>
      </div>
      <div class="form-group">
        <label class="form-label" for="confirm_password">Konfirmasi Password</label>
        <input class="form-control" type="password" id="confirm_password" name="confirm_password" placeholder="••••••••" required/>
      </div>
      <button type="submit" class="btn-submit">DAFTAR SEKARANG</button>
    </form>
  </div>

  <div class="card-foot">
    Sudah punya akun? <a href="/login.php">Masuk di sini</a>
  </div>
</div>
</body>
</html>