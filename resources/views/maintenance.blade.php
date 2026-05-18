<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sistem Dalam Perbaikan | GUDEG JOGJA</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: #07101e;
            color: #c8d0e0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        /* Grid background */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(40,80,140,0.08) 1px, transparent 1px),
                linear-gradient(90deg, rgba(40,80,140,0.08) 1px, transparent 1px);
            background-size: 48px 48px;
            pointer-events: none;
            z-index: 0;
        }

        /* Radial glow di tengah */
        body::after {
            content: '';
            position: fixed;
            inset: 0;
            background: radial-gradient(ellipse at 50% 50%, rgba(12,36,80,0.7) 0%, rgba(4,8,18,0.0) 70%);
            pointer-events: none;
            z-index: 0;
        }

        .card {
            position: relative;
            z-index: 2;
            background: rgba(13,25,50,0.88);
            border: 1px solid #1e3560;
            border-radius: 6px;
            padding: 40px 32px;
            max-width: 420px;
            width: 100%;
            text-align: center;
        }

        .brand {
            font-size: 32px;
            font-weight: 700;
            letter-spacing: 5px;
            margin-bottom: 6px;
            line-height: 1;
        }
        .brand .gudeg { color: #ffffff; }
        .brand .jogja  { color: #e8b84b; }

        .tagline {
            font-size: 9px;
            color: #4a7aaa;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .subtagline {
            font-size: 8px;
            color: #2a4a6a;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            margin-bottom: 24px;
        }

        .divider {
            width: 40px;
            height: 1px;
            background: #1e3560;
            margin: 0 auto 20px;
        }

        .title {
            font-size: 16px;
            font-weight: 600;
            color: #c0d4f0;
            margin-bottom: 12px;
        }

        .desc {
            font-size: 12px;
            color: #4a6a90;
            line-height: 1.7;
            margin-bottom: 24px;
        }
        .desc .gudeg { color: #ffffff; font-weight: 600; }
        .desc .jogja  { color: #e8b84b; font-weight: 600; }

        .btn-admin {
            display: inline-block;
            padding: 9px 20px;
            background: rgba(18,40,80,0.8);
            border: 1px solid #2a4a80;
            color: #6a9acc;
            text-decoration: none;
            border-radius: 4px;
            font-size: 11px;
            transition: 0.2s;
        }
        .btn-admin:hover {
            background: rgba(26,58,120,0.9);
            border-color: #3a6ab0;
            color: #90bae0;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">
            <span class="gudeg">GUDEG</span> <span class="jogja">JOGJA</span>
        </div>
        <div class="tagline">General Unified Database and Employee Gateway</div>
        <div class="subtagline">Balai Monitor Spektrum Frekuensi Radio Kelas I Yogyakarta</div>
        <div class="divider"></div>
        <div class="title">Sistem Sedang Dalam Perbaikan</div>
        <div class="desc">
            Portal <span class="gudeg">GUDEG</span> <span class="jogja">JOGJA</span>
            Balai Monitor Spektrum Frekuensi Radio Kelas I Yogyakarta saat ini sedang dalam
            pemeliharaan rutin untuk peningkatan layanan.<br><br>
            Silakan kembali beberapa saat lagi.
        </div>
        <!-- Tombol diubah agar melakukan logout (menghapus session) lalu kembali ke halaman login -->
        <a href="/logout" class="btn-admin">Logout & Login Ulang</a>
    </div>
</body>
</html>
