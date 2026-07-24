# 🍛 Gudeg SSO

**Gudeg SSO** adalah sistem *Single Sign-On* (SSO) berbasis PHP yang memungkinkan satu akun digunakan untuk mengakses banyak aplikasi. Backend menggunakan [Supabase](https://supabase.com/) (PostgreSQL via REST API), di-deploy ke InfinityFree via GitHub Actions.

---

## ✨ Fitur Utama

- **SSO dengan JWT** — Login sekali, akses banyak aplikasi. Token bisa pakai HMAC-SHA256 atau RSA-signed (RS256).
- **Manajemen User** — Registrasi, login, ganti password, remember me, dan session timeout otomatis.
- **Role-Based Access** — Tiga role: `admin`, `user`, dan `guest`, masing-masing dengan hak akses berbeda.
- **Panel Admin** — Kelola user (aktifkan/nonaktifkan, ubah role, reset password) dan kelola aplikasi SSO.
- **Audit Log** — Setiap aktivitas login, SSO, dan aksi admin dicatat lengkap beserta IP.
- **Keamanan** — CSRF protection, rate limiting login, session regeneration, CORS dinamis, dan sanitasi input.
- **OpenID Connect** — Discovery endpoint (`/.well-known/openid-configuration`) dan JWKS endpoint (`/jwks.json`).
- **Custom Error Pages** — Halaman error 403, 404, dan 500 yang proper.
- **APK Support** — Deteksi `GudegJogjaApp` user-agent untuk sesi 30 hari di aplikasi mobile.

---

## 🏗️ Struktur Proyek

```
gudeg-master/
├── .github/
│   └── workflows/
│       └── deploy.yml          # CI/CD — deploy ke InfinityFree via FTP
├── .htaccess                   # Konfigurasi Apache (security headers, error pages, session)
├── .well-known/
│   └── openid-configuration    # OIDC discovery endpoint (JSON statis)
├── admin/
│   ├── index.php               # Panel admin — manajemen user
│   ├── apps.php                # Panel admin — manajemen aplikasi SSO
│   └── logs.php                # Panel admin — audit log
├── config/
│   └── .gitkeep                # db.php & .env taruh di sini (JANGAN di-commit)
├── includes/
│   ├── SupabaseClient.php      # Client REST API ke Supabase (PostgREST)
│   ├── functions.php           # Helper: session, auth, log, sanitasi, CSRF
│   ├── jwt.php                 # Generate & verify JWT (RSA / HMAC)
│   └── layout.php              # Template HTML (head, nav, footer)
├── keys/
│   └── .gitkeep                # private.pem & public.pem taruh di sini (JANGAN di-commit)
├── 403.php                     # Error page 403 Forbidden
├── 404.php                     # Error page 404 Not Found
├── 500.php                     # Error page 500 Internal Server Error
├── api-logout.php              # Endpoint logout via API (untuk APK)
├── change-password.php         # Halaman ganti password
├── dashboard.php               # Halaman utama setelah login
├── index.php                   # Entry point — redirect ke login/dashboard
├── jwks.json                   # JWKS endpoint (dieksekusi sebagai PHP)
├── login.php                   # Halaman login
├── logout.php                  # Proses logout & hapus session
├── register.php                # Halaman registrasi akun baru
├── sso.php                     # Endpoint SSO — generate & redirect dengan token
├── verify.php                  # Endpoint verifikasi token untuk aplikasi klien
└── version.json                # Info versi APK (nama, URL download, catatan)
```

---

## ⚙️ Cara Setup

### 1. Prasyarat

- PHP 8.0+
- Apache dengan `mod_rewrite` dan `mod_headers`
- Akun [Supabase](https://supabase.com/)
- (Opsional) Akun [InfinityFree](https://infinityfree.net/) untuk hosting

### 2. Clone & Konfigurasi

```bash
git clone https://github.com/<username>/gudeg.git
cd gudeg
```

### 3. Buat File Konfigurasi

Buat file `config/db.php` (file ini **JANGAN** di-commit):

```php
<?php
// Supabase
define('SUPABASE_URL', 'https://xxxx.supabase.co');
define('SUPABASE_SERVICE_KEY', 'eyJ...');  // Service role key, bukan anon key!
```

Atau buat `config/.env`:

```env
SUPABASE_URL=https://xxxx.supabase.co
SUPABASE_SERVICE_KEY=eyJ...
```

### 4. Generate RSA Key (untuk Signed JWT)

```bash
openssl genrsa -out keys/private.pem 2048
openssl rsa -in keys/private.pem -pubout -out keys/public.pem
```

File `keys/` sudah diproteksi `.htaccess` dan dikecualikan dari deploy di `deploy.yml`.

### 5. Setup Database Supabase

Buat tabel berikut di Supabase SQL Editor:

```sql
-- Tabel user
create table users (
  id            bigint primary key generated always as identity,
  name          text not null,
  email         text not null unique,
  password      text not null,
  role          text not null default 'user', -- 'admin' | 'user' | 'guest'
  is_active     boolean not null default true,
  created_at    timestamp,
  deleted_at    timestamp,
  password_changed_at timestamp
);

-- Tabel aplikasi SSO
create table sso_applications (
  id               bigint primary key generated always as identity,
  name             text not null,
  description      text,
  redirect         text not null,
  client_id        text not null unique,
  client_secret    text not null,
  use_signed_token boolean not null default false,
  is_active        boolean not null default true,
  created_at       timestamp,
  updated_at       timestamp
);

-- Tabel hak akses user ke aplikasi
create table user_applications (
  user_id         bigint references users(id),
  application_id  bigint references sso_applications(id),
  primary key (user_id, application_id)
);

-- Tabel audit log user
create table user_logs (
  id          bigint primary key generated always as identity,
  user_id     bigint,
  user_name   text,
  user_email  text,
  activity    text,
  ip_address  text,
  created_at  timestamp
);

-- Tabel audit log admin
create table admin_logs (
  id          bigint primary key generated always as identity,
  admin_id    bigint,
  admin_name  text,
  action      text,
  target      text,
  ip_address  text,
  created_at  timestamp
);

-- Token blacklist (untuk invalidasi JWT)
create table token_blacklist (
  jti  text primary key,
  exp  bigint
);

-- Indexes performa (jalankan di SQL Editor Supabase)
create index if not exists idx_sessions_user_id       on sessions (user_id);
create index if not exists idx_sessions_last_activity on sessions (last_activity);
create index if not exists idx_user_logs_user_id      on user_logs (user_id);
create index if not exists idx_user_logs_created_at   on user_logs (created_at desc);
create index if not exists idx_admin_logs_created_at  on admin_logs (created_at desc);
create index if not exists idx_token_blacklist_exp    on token_blacklist (exp);
```

> 💡 File lengkap tersedia di `migrations/001_add_indexes.sql`

---

## 🔄 Alur SSO

```
User → Login ke Gudeg SSO
     → Dashboard (daftar aplikasi yang bisa diakses)
     → Klik aplikasi
     → /sso.php?client_id=<id>&state=<state>
     → Gudeg generate JWT (HMAC atau RSA)
     → Redirect ke <redirect_url>?token=<jwt>&state=<state>
     → Aplikasi klien POST ke /verify.php untuk validasi token
     → Gudeg balas { valid: true, payload: { sub, name, email, role, app } }
```

### Token Payload

```json
{
  "sub":   "42",
  "name":  "Budi Santoso",
  "email": "budi@example.com",
  "role":  "user",
  "app":   "Nama Aplikasi",
  "jti":   "a1b2c3d4e5f6g7h8",
  "iat":   1720000000,
  "exp":   1720000900,
  "iss":   "gudeg-sso"
}
```

Token berlaku **15 menit** sejak diterbitkan.

---

## 🔌 Integrasi Aplikasi Klien

### Langkah 1 — Daftarkan Aplikasi

Admin mendaftarkan aplikasi di panel `/admin/apps.php`. Sistem akan generate `client_id` dan `client_secret`.

### Langkah 2 — Redirect ke SSO

```
https://gudeg.xo.je/sso.php?client_id=<client_id>&state=<random_state>
```

### Langkah 3 — Terima Token

Setelah login berhasil, user akan diarahkan ke URL redirect yang sudah didaftarkan:

```
https://aplikasi-anda.com/callback?token=<jwt>&state=<state>
```

**Penting:** Selalu verifikasi `state` untuk mencegah CSRF.

### Langkah 4 — Verifikasi Token

```http
POST https://gudeg.xo.je/verify.php
Content-Type: application/x-www-form-urlencoded

token=<jwt>&client_id=<client_id>&client_secret=<client_secret>
```

Response sukses:

```json
{
  "valid": true,
  "payload": {
    "sub": "42",
    "name": "Budi Santoso",
    "email": "budi@example.com",
    "role": "user",
    "app": "Nama Aplikasi"
  }
}
```

Response gagal:

```json
{
  "valid": false,
  "error": "Token expired"
}
```

---

## 🛡️ Keamanan

| Fitur | Detail |
|---|---|
| CSRF Protection | Token CSRF di setiap form POST |
| Rate Limiting Login | Lockout 10 menit setelah beberapa kali gagal |
| Rate Limiting Verify | Mencegah brute force `client_secret` |
| Session Hardening | `httponly`, `strict_mode`, `SameSite=Lax` |
| CORS Dinamis | Hanya origin terdaftar di database yang diizinkan |
| Open Redirect Protection | Redirect URL diambil dari DB, bukan dari query string |
| RSA / HMAC JWT | Pilihan per-aplikasi: RS256 (signed) atau HS256 |
| Token Blacklist | JWT yang sudah logout disimpan di `token_blacklist` |
| File Protection | `.htaccess` memblokir akses ke `.pem`, `.key`, `db.php`, dotfiles |
| Error Hiding | `display_errors Off` di production |

---

## 🚀 Deploy (GitHub Actions)

Deploy otomatis ke InfinityFree via FTP setiap kali push ke branch `master`.

### Setup Secrets di GitHub

Masuk ke **Settings → Secrets and variables → Actions**, tambahkan:

| Secret | Keterangan |
|---|---|
| `FTP_SERVER` | Hostname FTP InfinityFree |
| `FTP_USERNAME` | Username FTP |
| `FTP_PASSWORD` | Password FTP |

File yang **dikecualikan** dari deploy (diatur di `deploy.yml`):

- `**/.git*` dan `**/.git*/**`
- `**/keys/private.pem`
- `**/keys/public.pem`
- `**/config/db.php`

---

## 👥 Role & Hak Akses

| Role | Dashboard | Akses Aplikasi | Panel Admin |
|---|---|---|---|
| `admin` | ✅ | Semua aplikasi aktif | ✅ |
| `user` | ✅ | Hanya aplikasi yang diberikan izin | ❌ |
| `guest` | ✅ (terbatas) | Tidak ada | ❌ |

---

## 📱 Aplikasi Mobile (APK)

`version.json` digunakan oleh APK Android untuk cek update:

```json
{
  "version": "1.2",
  "url": "https://gudeg.xo.je/gudeg-jogja.apk",
  "notes": "Versi awal rilis"
}
```

APK terdeteksi via `User-Agent: GudegJogjaApp` — session diperpanjang otomatis menjadi **30 hari**.

---

## 📄 Lisensi

Proyek ini bersifat privat. Hak cipta sepenuhnya milik pengembang.