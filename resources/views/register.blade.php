<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GUDEG JOGJA - Registrasi</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background-color: #080c18;
            background-image:
                radial-gradient(ellipse 80% 70% at 50% 50%, #111e3a 0%, #080c18 70%),
                linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 100% 100%, 48px 48px, 48px 48px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: #ccd6f6;
            padding: 16px;
        }

        .wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
            max-width: 420px;
        }

        /* Brand */
        .brand { text-align: center; margin-bottom: 28px; }

        .brand-name {
            font-size: 34px;
            font-weight: 800;
            letter-spacing: 3px;
            line-height: 1;
            margin-bottom: 8px;
        }
        .brand-name .gudeg { color: #ffffff; }
        .brand-name .jogja { color: #e8b84b; }

        .brand-tagline {
            font-size: 10.5px;
            color: #6b7a9f;
            letter-spacing: 2px;
            text-transform: uppercase;
            font-weight: 500;
            margin-bottom: 4px;
        }
        .brand-sub {
            font-size: 10px;
            color: #4a5878;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        /* Card */
        .card {
            width: 100%;
            background: #131929;
            border: 1px solid #1e2d4a;
            border-radius: 10px;
            padding: 26px 24px;
        }

        .card-title {
            font-size: 17px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 5px;
        }
        .card-sub {
            font-size: 12px;
            color: #8a9bbf;
            margin-bottom: 22px;
            line-height: 1.5;
        }
        .card-sub b { color: #e8b84b; font-weight: 700; }

        /* Error */
        .error {
            background: #1f0d10;
            border: 1px solid #4a1a22;
            color: #ff8f8f;
            padding: 8px 12px;
            border-radius: 5px;
            font-size: 11px;
            margin-bottom: 16px;
        }

        /* Form */
        label {
            display: block;
            font-size: 10.5px;
            color: #6b7a9f;
            margin-bottom: 5px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            background: #0d1220;
            border: 1px solid #1e2d4a;
            color: #ccd6f6;
            padding: 9px 12px;
            border-radius: 5px;
            margin-bottom: 14px;
            font-size: 13px;
            font-family: inherit;
            min-height: 38px;
            transition: border-color 0.15s;
            -webkit-appearance: none;
            appearance: none;
        }
        input::placeholder { color: #2e3d5c; }
        input:focus { outline: none; border-color: #3d6aff; }

        button[type="submit"] {
            width: 100%;
            background: #2d4fff;
            border: none;
            color: #fff;
            padding: 11px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
            letter-spacing: 0.3px;
            margin-top: 4px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: opacity 0.15s;
            -webkit-appearance: none;
            min-height: 40px;
        }
        button[type="submit"]:hover { opacity: 0.88; }
        button[type="submit"]::after { content: '→'; margin-left: auto; opacity: 0.7; font-size: 16px; }

        .divider {
            border: none;
            border-top: 1px solid #1a2640;
            margin-bottom: 14px;
        }

        .bottom-link {
            text-align: center;
            font-size: 11px;
            color: #4d5d7e;
        }
        .bottom-link a {
            color: #5a7fff;
            text-decoration: none;
            font-weight: 600;
        }
        .bottom-link a:hover { text-decoration: underline; }

        .footer {
            margin-top: 20px;
            font-size: 11px;
            color: #1e2d44;
            text-align: center;
        }

        @media (max-width: 400px) {
            body { padding: 12px; align-items: flex-start; padding-top: 30px; }
            .card { padding: 20px 16px; }
            .brand-name { font-size: 28px; }
        }
    </style>
</head>
<body>
    <div class="wrapper">

        <div class="brand">
            <div class="brand-name">
                <span class="gudeg">GUDEG</span> <span class="jogja">JOGJA</span>
            </div>
            <div class="brand-tagline">General Unified Database and Employee Gateway</div>
            <div class="brand-sub">Balai Monitor Spektrum Frekuensi Radio Kelas I Yogyakarta</div>
        </div>

        <div class="card">
            <div class="card-title">Buat Akun Baru</div>
            <div class="card-sub">Daftarkan diri Anda ke portal <b>GUDEG JOGJA</b>.</div>

            @if ($errors->any())
                <div class="error">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="/register">
                @csrf

                <label for="name">Nama Lengkap</label>
                <input type="text" id="name" name="name"
                    value="{{ old('name') }}"
                    placeholder="Nama lengkap"
                    required autofocus>

                <label for="email">Email</label>
                <input type="email" id="email" name="email"
                    value="{{ old('email') }}"
                    placeholder="email@example.com"
                    required>

                <label for="password">Password</label>
                <input type="password" id="password" name="password"
                    placeholder="Minimal 8 karakter"
                    required>

                <label for="password_confirmation">Konfirmasi Password</label>
                <input type="password" id="password_confirmation" name="password_confirmation"
                    placeholder="Ulangi password"
                    required>

                <button type="submit">Daftar ke Portal</button>
            </form>

            <hr class="divider">

            <div class="bottom-link">
                Sudah punya akun? <a href="/">Login di sini</a>
            </div>
        </div>

        <div class="footer">© {{ date('Y') }} QoS Balmon Jogja Team</div>

    </div>
</body>
</html>
