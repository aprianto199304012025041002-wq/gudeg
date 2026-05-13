<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>GUDEG JOGJA - Login</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: #1a1a1a;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: #d4d4d4;
            padding: 16px;
        }

        .card {
            width: 100%;
            max-width: 360px;
            background: #252526;
            border: 1px solid #3c3c3c;
            border-radius: 4px;
            padding: 24px 20px;
        }

        .app-name {
            text-align: center;
            font-size: 18px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 4px;
        }

        .subtitle {
            text-align: center;
            color: #858585;
            font-size: 11px;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .error {
            background: #3a1d1d;
            border: 1px solid #5c2626;
            color: #ff8f8f;
            padding: 8px 10px;
            border-radius: 3px;
            font-size: 11px;
            margin-bottom: 14px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-size: 11px;
            color: #aaaaaa;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            background: #1e1e1e;
            border: 1px solid #3c3c3c;
            color: #d4d4d4;
            padding: 9px 10px;
            border-radius: 3px;
            margin-bottom: 12px;
            font-size: 13px;
            transition: border 0.15s;
            -webkit-appearance: none;
        }

        input::placeholder {
            color: #555;
        }

        input:focus {
            outline: none;
            border-color: #007acc;
        }

        .remember {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
            font-size: 12px;
            color: #aaaaaa;
        }

        .remember input {
            width: auto;
            margin-bottom: 0;
            accent-color: #007acc;
        }

        button {
            width: 100%;
            border: none;
            background: #007acc;
            color: white;
            padding: 10px;
            border-radius: 3px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s;
            -webkit-appearance: none;
        }

        button:hover {
            background: #0090f1;
        }

        button:active {
            background: #0060a0;
        }

        .register-link {
            margin-top: 16px;
            text-align: center;
            font-size: 11px;
            color: #858585;
        }

        .register-link a {
            color: #4daafc;
            text-decoration: none;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        @media (max-width: 400px) {
            body {
                padding: 12px;
                align-items: flex-start;
                padding-top: 60px;
            }

            .card {
                padding: 20px 16px;
            }
        }
    </style>
</head>

<body>

    <div class="card">

        <div style="text-align:center; margin-bottom:15px;">
            <div style="font-size:28px;">📡</div>
        </div>

        <div class="app-name">
            GUDEG JOGJA
        </div>

        <div class="subtitle">
            General Unified Database and Employee Access Gateway
            <br>
            Balai Monitor Spektrum Frekuensi Radio Kelas I Yogyakarta
        </div>

        @if ($errors->any())
            <div class="error">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="/login">

            @csrf

            <label for="email">Email</label>

            <input
                type="email"
                id="email"
                name="email"
                value="{{ old('email') }}"
                placeholder="email@example.com"
                required
                autofocus
            >

            <label for="password">Password</label>

            <input
                type="password"
                id="password"
                name="password"
                placeholder="••••••••"
                required
            >

            <div class="remember">
                <input type="checkbox" id="remember" name="remember">

                <label for="remember" style="margin:0;">
                    Ingat saya
                </label>
            </div>

            <button type="submit">
                Masuk ke Portal
            </button>

        </form>

        <div class="register-link">
            Belum punya akun?
            <a href="/register">Daftar sekarang</a>
        </div>

    </div>

</body>
</html>
