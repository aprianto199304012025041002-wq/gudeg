# 🍛 GUDEG SSO

**GUDEG** (General Unified Database And Employee Gateway) adalah sistem *Single Sign-On* (SSO) berbasis PHP untuk Balai Monitor Spektrum Frekuensi Radio Yogyakarta. Satu akun dipakai untuk mengakses banyak aplikasi internal. Backend menggunakan [Supabase](https://supabase.com/) (PostgreSQL via PostgREST REST API), di-deploy ke InfinityFree via GitHub Actions.

---

## ✨ Fitur Utama

- **SSO dengan JWT RS256** — Login sekali, akses banyak aplikasi. Token ditandatangani dengan RSA private key (RS256).
- **Manajemen User** — Registrasi, login, ganti password, session timeout otomatis, dan deteksi login dari IP baru.
- **Role-Based Access** — Tiga role: `admin`, `user`, dan `guest`, masing-masing dengan hak akses berbeda.
- **Panel Admin** — Kelola user (aktifkan/nonaktifkan, ubah role, reset password) dan kelola aplikasi SSO.
- **Audit Log** — Setiap aktivitas login, SSO, dan aksi admin dicatat lengkap beserta IP. Auto-cleanup log > 90 hari. Log yang gagal dikirim ke Supabase di-queue ke SQLite dan dikirim ulang otomatis di request berikutnya.
- **Keamanan** — CSRF protection, rate limiting login & verify, session regeneration, CORS dinamis, sanitasi input, token replay blacklist, re-validasi role dari DB di setiap request admin.
- **Fallback Offline** — LocalCache SQLite di `/tmp` dipakai saat Supabase down: token blacklist tetap aktif (fail-closed), user yang sudah login tetap bisa jalan, rate limit tetap berjalan.
- **Rate Limiter SQLite** — RateLimiter disimpan di SQLite (lebih persist dari file `/tmp`), dengan fallback ke file kalau SQLite error.
- **Structured Logging** — Semua error dicatat via `gudeg_log()` dalam format JSON `{ts, level, ctx, msg}` untuk kemudahan trace.
- **OpenID Connect** — Discovery endpoint (`/.well-known/openid-configuration`) dan JWKS endpoint (`/jwks.json`).
- **Health Check** — Endpoint `/healthcheck.php` dipanggil otomatis setelah deploy. Mencakup cek Supabase, RSA keys, SQLite, dan pending log queue.
- **Custom Error Pages** — Halaman error 403, 404, dan 500 yang proper.
- **APK Support** — Deteksi `GudegJogjaApp` user-agent untuk sesi 30 hari di aplikasi mobile.

---

## 🏗️ Struktur Proyek

```
gudeg/
├── .github/
│   └── workflows/
│       ├── deploy.yml              # CI/CD — deploy ke InfinityFree via FTP
│       └── query-supabase.yml      # ⚠️  Workflow query Supabase manual (diagnosa) — output ke query-results/ yang TIDAK boleh di-commit
├── .htaccess                       # Konfigurasi Apache (security headers, error pages, session)
├── .well-known/
│   └── openid-configuration        # OIDC discovery endpoint (JSON statis)
├── admin/
│   ├── index.php                   # Panel admin — manajemen user
│   ├── apps.php                    # Panel admin — manajemen aplikasi SSO
│   └── logs.php                    # Panel admin — audit log (user & admin)
├── assets/                         # CSS, JS, font lokal (Tabler self-hosted; Google Fonts masih dimuat dari CDN — lihat BACKLOG #75)
├── config/
│   └── .gitkeep                    # .env taruh di sini (JANGAN di-commit)
├── includes/
│   ├── LocalCache.php              # SQLite fallback — token blacklist, user cache, rate limit, pending log queue
│   ├── RateLimiter.php             # Rate limiter — 5 gagal / 10 menit per IP+email (SQLite, fallback file)
│   ├── SupabaseClient.php          # Client REST API ke Supabase (PostgREST), timeout per jenis operasi
│   ├── functions.php               # Helper: env loader, session, auth, log, sanitasi, CSRF, token blacklist, gudeg_log()
│   ├── jwt.php                     # Generate & verify JWT RSA (RS256), dengan error handling & CRITICAL log
│   └── layout.php                  # Template HTML (head, nav, footer)
├── keys/
│   └── .gitkeep                    # private.pem & public.pem taruh di sini (JANGAN di-commit)
├── query-results/                  # ⚠️  Output workflow query Supabase — JANGAN di-commit! Berisi data sensitif (session IDs, email, hash). Sudah di .gitignore.
├── 403.php                         # Error page 403 Forbidden
├── 404.php                         # Error page 404 Not Found
├── 500.php                         # Error page 500 Internal Server Error
├── ROTATE_KEY_GUDEG.md             # Panduan rotate RSA key
├── api-logout.php                  # Endpoint logout via API (untuk APK Android)
├── change-password.php             # Halaman ganti password & kelola sesi aktif
├── dashboard.php                   # Halaman utama setelah login (daftar aplikasi)
├── healthcheck.php                 # Cek status Supabase, RSA keys, SQLite, dan pending logs
├── index.php                       # Entry point — redirect ke login/dashboard
├── jwks.json                       # JWKS endpoint — expose RSA public key
├── login.php                       # Halaman login
├── logout.php                      # Proses logout & hapus session
├── register.php                    # Halaman registrasi akun baru (proteksi double-submit)
├── robots.txt                      # Instruksi crawler (block semua halaman & file sensitif)
├── sso.php                         # Endpoint SSO — generate JWT & redirect ke aplikasi klien
├── verify.php                      # Endpoint verifikasi token untuk aplikasi klien
└── version.json                    # Info versi APK (nama, URL download, catatan)
```

---

## ⚙️ Cara Setup

### 1. Prasyarat

- PHP 8.0+ dengan ekstensi: `openssl`, `pdo_sqlite`, `curl`
- Apache dengan `mod_rewrite` dan `mod_headers`
- Akun [Supabase](https://supabase.com/)
- Akun [InfinityFree](https://infinityfree.net/) untuk hosting

### 2. Clone

```bash
git clone https://github.com/aprianto199304012025041002-wq/gudeg.git
cd gudeg
```

### 3. Buat File Konfigurasi

Buat `config/.env` (file ini **JANGAN** di-commit):

```env
SUPABASE_URL=https://xxxx.supabase.co
SUPABASE_SERVICE_KEY=eyJ...
HEALTH_KEY=random_string_panjang_untuk_proteksi_healthcheck
CACHE_PATH=/tmp/gudeg_cache.sqlite   # opsional, default /tmp/gudeg_cache.sqlite
```

> Parsing `.env` dilakukan langsung di `includes/functions.php` — tidak ada dependency ke file lain.

### 4. Generate RSA Key

```bash
openssl genrsa -out keys/private.pem 2048
openssl rsa -in keys/private.pem -pubout -out keys/public.pem
```

File `keys/` sudah diproteksi `.htaccess` dan **tidak pernah di-commit** — deploy ke server via GitHub Secrets (lihat bagian Deploy).

### 5. Setup Database Supabase

Jalankan SQL berikut di **Supabase SQL Editor**:

```sql
-- Tabel user
CREATE TABLE users (
  id                  BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
  name                TEXT NOT NULL,
  email               TEXT NOT NULL UNIQUE,
  password            TEXT NOT NULL,
  role                TEXT NOT NULL DEFAULT 'guest', -- 'admin' | 'user' | 'guest'
  is_active           BOOLEAN NOT NULL DEFAULT false,
  created_at          TIMESTAMP,
  deleted_at          TIMESTAMP,
  password_changed_at TIMESTAMP
);

-- Tabel sesi aktif
CREATE TABLE sessions (
  id            TEXT PRIMARY KEY,  -- session_id PHP
  user_id       BIGINT REFERENCES users(id),
  ip_address    TEXT,
  user_agent    TEXT,
  payload       TEXT,
  last_activity TEXT,
  created_at    TIMESTAMP
);
CREATE INDEX idx_sessions_user_id ON sessions(user_id);

-- Tabel aplikasi SSO
CREATE TABLE sso_applications (
  id               BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
  name             TEXT NOT NULL,
  description      TEXT,
  redirect         TEXT NOT NULL,
  client_id        TEXT NOT NULL UNIQUE,
  client_secret    TEXT NOT NULL,      -- bcrypt hash, bukan plaintext
  use_signed_token BOOLEAN NOT NULL DEFAULT true,
  is_active        BOOLEAN NOT NULL DEFAULT true,
  created_at       TIMESTAMP,
  updated_at       TIMESTAMP
);

-- Tabel hak akses user ke aplikasi
CREATE TABLE user_applications (
  user_id         BIGINT REFERENCES users(id),
  application_id  BIGINT REFERENCES sso_applications(id),
  PRIMARY KEY (user_id, application_id)
);

-- Tabel audit log aktivitas user
CREATE TABLE user_logs (
  id          BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
  user_id     BIGINT,
  user_name   TEXT,
  user_email  TEXT,
  activity    TEXT,
  ip_address  TEXT,
  created_at  TIMESTAMP
);

-- Tabel audit log aksi admin
CREATE TABLE admin_logs (
  id          BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
  admin_id    BIGINT,
  admin_name  TEXT,
  action      TEXT,
  target_user TEXT,
  ip_address  TEXT,
  created_at  TIMESTAMP
);

-- Token blacklist (cegah JWT replay attack)
CREATE TABLE token_blacklist (
  jti     TEXT PRIMARY KEY,
  exp     BIGINT,
  used_at TIMESTAMP
);
```

---

## 🔄 Alur SSO

```
User → Login ke GUDEG SSO
     → Dashboard (daftar aplikasi yang bisa diakses)
     → Klik aplikasi
     → /sso.php?client_id=<id>&state=<random>
     → GUDEG generate JWT RS256 (berlaku 15 menit)
     → Redirect ke <redirect_url>?token=<jwt>&state=<state>
     → Aplikasi klien POST ke /verify.php untuk validasi token
     → GUDEG cek: signature, expiry, issuer, JTI replay, user aktif, akses app
     → Balas { valid: true, payload: { user_id, name, email, role, exp } }
```

### Token Payload (RS256)

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

Token berlaku **15 menit** dan hanya bisa diverifikasi **satu kali** (JTI di-blacklist setelah verify).

---

## 🔌 Integrasi Aplikasi Klien

### Langkah 1 — Daftarkan Aplikasi

Admin mendaftarkan aplikasi di `/admin/apps.php`. Sistem generate `client_id` dan `client_secret`. **Simpan `client_secret` saat itu juga — tidak akan ditampilkan lagi.**

### Langkah 2 — Redirect ke SSO

```
https://gudeg.xo.je/sso.php?client_id=<client_id>&state=<random_state>
```

### Langkah 3 — Terima Token

Setelah login, user diarahkan ke redirect URL yang terdaftar:

```
https://aplikasi-anda.com/callback?token=<jwt>&state=<state>
```

**Selalu verifikasi `state` untuk mencegah CSRF.**

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
    "user_id": "42",
    "name":    "Budi Santoso",
    "email":   "budi@example.com",
    "role":    "user",
    "exp":     1720000900
  }
}
```

Response gagal:

```json
{
  "valid": false,
  "error": "Token sudah pernah digunakan (replay attack)."
}
```

> **Rate limit:** `verify.php` dibatasi per IP. Counter hanya naik kalau `client_secret` salah — bukan kalau token expired biasa.

---

## 🛡️ Keamanan

| Fitur | Detail |
|---|---|
| CSRF Protection | Token CSRF di setiap form POST |
| Rate Limiting Login | 5 gagal dalam 10 menit per IP+email → lockout (SQLite, fallback file) |
| Rate Limiting Verify | Counter naik hanya saat credential salah, bukan token expired |
| Role Re-validasi | `require_admin()` re-query DB setiap request — session stale tidak bisa akses admin |
| Session Hardening | `httponly`, `strict_mode`, `SameSite=Lax`, regenerasi setelah login |
| Session Timeout | 30 menit tidak aktif → auto logout (kecuali APK) |
| CORS Dinamis | Hanya origin yang terdaftar di DB yang diizinkan |
| Open Redirect Protection | Redirect URL diambil dari DB, bukan dari query string |
| JWT RS256 | Semua token ditandatangani RSA — tidak bisa dipalsukan tanpa private key |
| Token Replay Protection | JTI di-blacklist di Supabase + LocalCache SQLite setelah satu kali verify |
| Fallback Fail-Closed | Kalau Supabase & SQLite dua-duanya error saat cek JTI → token **ditolak** |
| Double-Submit Protection | Register dilindungi session lock — cegah race condition klik dua kali |
| File Protection | `.htaccess` blokir akses ke `.pem`, `.key`, `.env`, dotfiles |
| Error Hiding | `display_errors Off` di production |
| Input Sanitization | Semua input di-trim + batas panjang sebelum masuk DB |
| Log Auto-Cleanup | Log > 90 hari dihapus otomatis (max sekali per 24 jam) |
| Structured Logging | `gudeg_log(level, ctx, msg)` output JSON — mudah di-filter dan di-trace |

---

## 🚀 Deploy (GitHub Actions)

Deploy otomatis ke InfinityFree via FTP setiap push ke branch `master`. File sensitif **tidak pernah di-commit** — dibuat di runner dari GitHub Secrets lalu langsung di-upload ke server.

### Setup Secrets di GitHub

Masuk ke **Settings → Secrets and variables → Actions**, tambahkan:

| Secret | Keterangan |
|---|---|
| `FTP_SERVER` | Hostname FTP InfinityFree |
| `FTP_USERNAME` | Username FTP |
| `FTP_PASSWORD` | Password FTP |
| `SUPABASE_URL` | URL project Supabase (`https://xxxx.supabase.co`) |
| `SUPABASE_SERVICE_KEY` | Service role key Supabase untuk `deploy.yml` (bukan anon key) |
| `SUPABASE_KEY` | Service role key Supabase untuk `query-supabase.yml` (sama nilainya dengan `SUPABASE_SERVICE_KEY`) |
| `PAT_TOKEN` | GitHub Personal Access Token — dipakai `query-supabase.yml` untuk commit hasil query |
| `HEALTH_KEY` | String acak untuk proteksi endpoint `/healthcheck.php` |
| `RSA_PRIVATE_KEY` | Isi `keys/private.pem` (termasuk header `-----BEGIN...-----`) |
| `RSA_PUBLIC_KEY` | Isi `keys/public.pem` |

### Health Check

Setelah deploy, workflow otomatis memanggil:

```
GET https://gudeg.xo.je/healthcheck.php?key=<HEALTH_KEY>
```

Response mencakup: status Supabase, env config, RSA keys, `/tmp` writable, SQLite LocalCache, dan pending log queue.

---

## 👥 Role & Hak Akses

| Role | Dashboard | Akses Aplikasi SSO | Panel Admin | Default saat Register |
|---|---|---|---|---|
| `admin` | ✅ | Semua aplikasi aktif | ✅ | — (diset manual) |
| `user` | ✅ | Hanya yang diberi izin admin | ❌ | — (diupgrade admin dari guest) |
| `guest` | ✅ (terbatas) | Tidak ada | ❌ | ✅ |

Akun baru dari `register.php` langsung berstatus `guest` dan **tidak aktif** (`is_active = false`) sampai diaktifkan admin.

---

## 📱 Aplikasi Mobile (APK)

`version.json` digunakan APK Android untuk cek update:

```json
{
  "version":     "1.2",
  "url":         "https://gudeg.xo.je/gudeg-jogja.apk",
  "notes":       "Catatan rilis",
  "deployed_at": "2026-08-01T07:35:03.189523"
}
```

APK terdeteksi via `User-Agent: GudegJogjaApp` — session otomatis diperpanjang menjadi **30 hari** dan tidak pernah timeout karena inaktivitas.

---

## 🔑 Panduan Rotate RSA Key

> Berdasarkan pemeriksaan git history, `keys/private.pem` tidak terbukti pernah ter-commit. Panduan ini disimpan untuk prosedur bila diperlukan di masa mendatang.

### 1. Generate key baru
```bash
openssl genrsa -out keys/private.pem 2048
openssl rsa -in keys/private.pem -pubout -out keys/public.pem
openssl rsa -in keys/private.pem -check -noout  # RSA key ok
```

### 2. Bersihkan git history (kalau key sempat ter-commit)
```bash
pip install git-filter-repo
git filter-repo --path keys/ --invert-paths --force
git remote add origin https://github.com/aprianto199304012025041002-wq/gudeg.git
git push origin master --force
```

### 3. Deploy key baru ke server GUDEG
Upload `keys/private.pem` dan `keys/public.pem` via FTP InfinityFree ke folder `keys/`.

### 4. Update public key di semua aplikasi klien SSO
- **PASAR** (Hostinger) — update `SSO_PUBLIC_KEY` di `config.local.php`
- **KASPER** dan **KOMENK** — update konstanta/env var yang menyimpan public key

### 5. Invalidate semua session aktif
```sql
-- Jalankan di Supabase SQL Editor
DELETE FROM sessions;
```

### 6. Test SSO flow
Buka PASAR → login → redirect ke GUDEG → callback → pastikan token baru diterima.

> User yang sedang aktif akan di-logout otomatis. Lakukan rotate di luar jam kerja.

---

## 📄 Lisensi

Proyek ini bersifat privat. Hak cipta sepenuhnya milik pengembang.
