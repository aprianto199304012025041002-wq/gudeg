# 🍛 GUDEG SSO

**GUDEG** (General Unified Database And Employee Gateway) adalah sistem *Single Sign-On* (SSO) berbasis PHP untuk Balai Monitor Spektrum Frekuensi Radio Kelas I Yogyakarta. Satu akun dipakai untuk mengakses semua aplikasi internal: PASAR, KOMENK, KASPER, dan APK Android.

- **URL Produksi:** `https://gudeg.xo.je`
- **Hosting:** InfinityFree
- **Stack:** PHP 8.1 + Supabase (PostgREST) + SQLite LocalCache
- **Deploy:** GitHub Actions → FTP → InfinityFree (push ke `master` = auto deploy)
- **Repo:** `aprianto199304012025041002-wq/gudeg`

---

## ✨ Fitur

- **SSO JWT RS256** — Login sekali, akses banyak aplikasi. Token ditandatangani RSA private key (RS256), berlaku 15 menit, satu kali pakai (JTI blacklist).
- **Manajemen User** — Registrasi, login, ganti password, session timeout otomatis (30 menit), deteksi login dari IP baru.
- **Role-Based Access** — Tiga role: `admin`, `user`, `guest`. Role di-revalidasi dari DB setiap request admin — session stale tidak bisa bypass.
- **Panel Admin** — Kelola user (aktifkan/nonaktifkan, ubah role, reset password) dan kelola aplikasi SSO (tambah, edit, regenerate secret, hapus).
- **Favorit Aplikasi** — User bisa pin/bintangi aplikasi favorit di dashboard. Favorit ditampilkan di bagian teratas, tersimpan di tabel `user_favorites` Supabase.
- **Audit Log** — Semua aktivitas login, SSO, dan aksi admin dicatat dengan IP. Auto-cleanup log >90 hari (max sekali per 24 jam). Log yang gagal dikirim ke Supabase di-queue ke SQLite dan di-retry otomatis.
- **CSRF Protection** — Token CSRF di setiap form POST. Rotasi token setelah setiap submit sukses. Double-submit lock di register.
- **Rate Limiting** — Login: 5 gagal / 10 menit per IP+email → lockout. Verify: counter naik hanya saat `client_secret` salah, bukan saat token expired.
- **Session SQLite** — Session disimpan via `SqliteSessionHandler` (bukan file `/tmp`) — lebih persist dan portable.
- **Fallback Offline** — LocalCache SQLite dipakai saat Supabase down: token blacklist tetap aktif (fail-closed), rate limit tetap jalan, user yang sudah login tetap bisa akses.
- **CSP Nonce-Based** — Content Security Policy tanpa `unsafe-inline`. Setiap response dapat nonce unik via `csp_nonce()`.
- **Structured Logging** — Semua error via `gudeg_log(level, ctx, msg)` dalam format JSON `{ts, level, ctx, msg}` ke error log server.
- **OIDC Discovery** — Endpoint `/.well-known/openid-configuration` dan `jwks.json` untuk integrasi standar OAuth2/OIDC.
- **Health Check** — `/healthcheck.php` dipanggil otomatis setelah deploy. Cek: Supabase, RSA keys, SQLite, pending log queue, env config.
- **APK Android** — Deteksi `GudegJogjaApp` user-agent → session 30 hari, tidak timeout karena inaktivitas. APK versi terkini: `1.2`.

---

## 🏗️ Struktur Proyek

```
gudeg/
├── .github/workflows/
│   ├── deploy.yml              # Push ke master → FTP ke InfinityFree → health check
│   └── query-supabase.yml      # Query manual Supabase (diagnosa) — output ke query-results/ JANGAN di-commit
├── .htaccess                   # Security headers, error pages, blokir akses ke file sensitif
├── .well-known/
│   └── openid-configuration    # OIDC discovery endpoint (JSON statis)
├── admin/
│   ├── index.php               # Manajemen user (aktifkan, nonaktifkan, ubah role, reset password)
│   ├── apps.php                # Manajemen aplikasi SSO (tambah, edit, regenerate secret, hapus)
│   └── logs.php                # Audit log user & admin
├── api/
│   └── favorite.php            # Toggle favorit aplikasi (POST, butuh CSRF token)
├── assets/                     # CSS, JS, font (Tabler Icons), favicon (ico/png/svg), og-image
├── config/
│   └── .gitkeep                # Taruh config/.env di sini (JANGAN di-commit)
├── db/
│   └── migrations.sql          # SQL migrations — jalankan manual di Supabase SQL Editor
├── includes/
│   ├── functions.php           # Aggregator — load semua modul includes di bawah
│   ├── env.php                 # Parser config/.env
│   ├── logger.php              # gudeg_log() — structured JSON logging
│   ├── headers.php             # Security headers + CSP nonce (csp_nonce())
│   ├── session.php             # Session init, timeout, check_session_timeout()
│   ├── activity.php            # Catat aktivitas user ke Supabase (dengan queue fallback)
│   ├── csrf.php                # CSRF token: generate, verify, rotate, csrf_field()
│   ├── validation.php          # Sanitasi & validasi input
│   ├── token.php               # token_use(): cek + blacklist JTI (Supabase + SQLite)
│   ├── jwt.php                 # generate_jwt() & verify_jwt() — RSA RS256
│   ├── layout.php              # Template HTML (layout_head, layout_nav, layout_foot)
│   ├── LocalCache.php          # SQLite: token_blacklist, user_cache, rate_limit, pending_logs, meta
│   ├── RateLimiter.php         # Rate limiter berbasis SQLite (fallback file /tmp)
│   ├── SqliteSessionHandler.php # Session handler custom berbasis SQLite
│   └── SupabaseClient.php      # HTTP client ke Supabase PostgREST (select, insert, update, delete)
├── keys/
│   └── .gitkeep                # private.pem & public.pem — JANGAN di-commit, deploy via Secrets
├── query-results/              # ⚠️ Output query Supabase — data sensitif, JANGAN di-commit (ada di .gitignore)
├── scripts/
│   ├── README.md               # Panduan rotate RSA key & sync ke semua repo klien
│   ├── requirements.txt        # Dependency Python (requests)
│   └── sync_sso_public_key.py  # Sync SSO_PUBLIC_KEY ke semua repo klien sekaligus
├── 403.php                     # Error page 403
├── 404.php                     # Error page 404
├── 500.php                     # Error page 500
├── api-logout.php              # Endpoint logout via API (untuk APK Android)
├── change-password.php         # Ganti password + kelola sesi aktif
├── dashboard.php               # Daftar aplikasi SSO + section favorit
├── healthcheck.php             # Status check: Supabase, RSA keys, SQLite, env, pending logs
├── index.php                   # Entry point — redirect ke login atau dashboard
├── jwks.json                   # JWKS endpoint — expose RSA public key
├── login.php                   # Halaman login
├── logout.php                  # Proses logout + hapus session
├── register.php                # Registrasi akun baru (proteksi double-submit)
├── robots.txt                  # Block semua crawler
├── sso.php                     # SSO endpoint — generate JWT + redirect ke aplikasi klien
├── verify.php                  # Verifikasi token untuk aplikasi klien
└── version.json                # Info versi APK Android (auto-update tiap deploy) — versi terkini: 1.2
```

---

## ⚙️ Setup

### 1. Prasyarat

- PHP 8.1+ dengan ekstensi: `openssl`, `pdo_sqlite`, `curl`
- Apache dengan `mod_rewrite` dan `mod_headers`
- Akun [Supabase](https://supabase.com/)
- Akun InfinityFree untuk hosting

### 2. Clone

```bash
git clone https://github.com/aprianto199304012025041002-wq/gudeg.git
cd gudeg
```

### 3. Buat `config/.env`

```env
SUPABASE_URL=https://xxxx.supabase.co
SUPABASE_SERVICE_KEY=eyJ...
HEALTH_KEY=random_string_panjang
CACHE_PATH=/tmp/gudeg_cache.sqlite
```

> Parsing `.env` dilakukan di `includes/env.php` — tidak ada dependency library.

### 4. Generate RSA Key

```bash
openssl genrsa -out keys/private.pem 2048
openssl rsa -in keys/private.pem -pubout -out keys/public.pem
```

File `keys/` diproteksi `.htaccess` dan tidak pernah di-commit — di-deploy ke server via GitHub Secrets.

### 5. Setup Database Supabase

Jalankan `db/migrations.sql` di Supabase SQL Editor secara berurutan. Tabel yang diperlukan:

| Tabel | Keterangan |
|---|---|
| `users` | Akun user |
| `sessions` | Sesi aktif (index: `user_id`, `last_activity`) |
| `sso_applications` | Aplikasi klien SSO terdaftar |
| `user_applications` | Hak akses user ke aplikasi |
| `user_favorites` | Favorit aplikasi per user |
| `user_logs` | Audit log aktivitas user |
| `admin_logs` | Audit log aksi admin |
| `token_blacklist` | JTI token yang sudah dipakai |

---

## 🔄 Alur SSO

```
User → Login ke GUDEG
     → Dashboard → klik aplikasi
     → /sso.php?client_id=<id>&state=<random>
     → GUDEG generate JWT RS256 (berlaku 15 menit)
     → Redirect ke <redirect_url>?token=<jwt>&state=<state>
     → Aplikasi klien verifikasi token lokal (RS256 via public key)
     → Atau POST /verify.php (token + client_id + client_secret) untuk verifikasi server-side
     → Balas { valid: true, payload: { user_id, name, email, role, exp } }
```

### Token Payload

```json
{
  "sub":   "42",
  "name":  "Budi Santoso",
  "email": "budi@example.com",
  "role":  "user",
  "app":   "PASAR",
  "jti":   "a1b2c3d4e5f6g7h8",
  "iat":   1720000000,
  "exp":   1720000900,
  "iss":   "gudeg-sso"
}
```

Token berlaku **15 menit** dan hanya bisa dipakai **satu kali** — JTI di-blacklist setelah verify.

---

## 🔌 Integrasi Aplikasi Klien

### 1. Daftarkan Aplikasi

Admin mendaftarkan aplikasi di `/admin/apps.php`. Sistem generate `client_id` dan `client_secret` — **simpan `client_secret` saat itu juga, tidak bisa dilihat lagi.**

### 2. Redirect ke SSO

```
GET https://gudeg.xo.je/sso.php?client_id=<client_id>&state=<random_state>
```

### 3. Terima Token

```
GET https://aplikasi-anda.com/callback?token=<jwt>&state=<state>
```

**Selalu verifikasi `state` untuk mencegah CSRF.**

### 4. Verifikasi Token (server-side)

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

### 5. Verifikasi Token (lokal RS256)

Aplikasi klien dapat memverifikasi token langsung tanpa memanggil `/verify.php`, menggunakan RSA public key GUDEG (`SSO_PUBLIC_KEY`) yang di-deploy via GitHub Secrets. Ini yang dipakai PASAR dan KOMENK.

### Daftar Aplikasi Klien Terdaftar

| Aplikasi | Client ID | Mode Verify |
|---|---|---|
| PASAR | `5d1a8495f13ca5d1` | RS256 lokal |
| KOMENK | `fea056cf6cdea01d` | RS256 lokal |
| KASPER | `1d4b1ae1b885ed27` | RS256 lokal |

---

## 🛡️ Keamanan

| Fitur | Detail |
|---|---|
| JWT RS256 | Token ditandatangani RSA — tidak bisa dipalsukan tanpa private key |
| Token Replay Protection | JTI di-blacklist di Supabase + SQLite setelah satu verify |
| Fallback Fail-Closed | Kalau Supabase & SQLite dua-duanya error saat cek JTI → token **ditolak** |
| CSRF Protection | Token CSRF di setiap form POST, rotasi setelah submit sukses |
| Double-Submit Lock | Register dilindungi session lock — cegah race condition |
| Rate Limiting Login | 5 gagal / 10 menit per IP+email → lockout (SQLite, fallback file) |
| Rate Limiting Verify | Counter naik hanya saat `client_secret` salah, bukan token expired |
| CSP Nonce-Based | Tidak ada `unsafe-inline` — nonce unik per response |
| Role Re-validasi | `require_admin()` re-query DB setiap request |
| Session Hardening | `httponly`, `secure`, `strict_mode`, `SameSite=Lax`, regenerasi setelah login |
| Session Timeout | 30 menit inaktif → auto logout (kecuali APK) |
| CORS Dinamis | Hanya origin yang terdaftar di DB yang diizinkan |
| Open Redirect Protection | Redirect URL dari DB, bukan dari query string |
| File Protection | `.htaccess` blokir akses ke `.pem`, `.key`, `.env`, dotfiles, `query-results/` |
| Error Hiding | `display_errors Off` di production |
| Structured Logging | `gudeg_log(level, ctx, msg)` — format JSON ke error log |

---

## 🚀 Deploy

Push ke `master` → GitHub Actions otomatis deploy ke InfinityFree via FTP → health check otomatis.

### GitHub Secrets yang Dibutuhkan

| Secret | Keterangan |
|---|---|
| `FTP_HOST` | Hostname FTP InfinityFree |
| `FTP_USER` | Username FTP |
| `FTP_PASS` | Password FTP |
| `SUPABASE_URL` | URL project Supabase |
| `SUPABASE_SERVICE_KEY` | Service role key Supabase |
| `HEALTH_KEY` | String acak proteksi `/healthcheck.php` |
| `RSA_PRIVATE_KEY` | Isi `keys/private.pem` |
| `RSA_PUBLIC_KEY` | Isi `keys/public.pem` |

---

## 👥 Role & Hak Akses

| Role | Dashboard | Akses Aplikasi SSO | Panel Admin | Default Saat Register |
|---|---|---|---|---|
| `admin` | ✅ | Semua aplikasi aktif | ✅ | — |
| `user` | ✅ | Hanya yang diberi izin | ❌ | — |
| `guest` | ✅ (terbatas) | Tidak ada | ❌ | ✅ |

Akun baru dari `register.php` langsung berstatus `guest` dan `is_active = false` sampai diaktifkan admin.

---

## 📱 APK Android

`version.json` dipakai APK untuk cek update. Di-update otomatis setiap deploy oleh workflow.

- Versi terkini: **1.2**
- URL unduh: `https://gudeg.xo.je/gudeg-jogja.apk`

APK terdeteksi via `User-Agent: GudegJogjaApp` → session 30 hari, tidak timeout karena inaktivitas.

---

## 🔑 Rotate RSA Key

```bash
# 1. Generate key baru
openssl genrsa -out keys/private.pem 2048
openssl rsa -in keys/private.pem -pubout -out keys/public.pem

# 2. Update GitHub Secrets: RSA_PRIVATE_KEY dan RSA_PUBLIC_KEY (manual, repo gudeg)

# 3. Sync SSO_PUBLIC_KEY ke semua aplikasi klien sekaligus
cd scripts && pip install -r requirements.txt
export GITHUB_PAT=github_pat_xxx
python3 sync_sso_public_key.py ../keys/public.pem

# 4. Invalidate semua session aktif (jalankan di Supabase SQL Editor)
DELETE FROM sessions;
```

> Lakukan rotate di luar jam kerja — semua user yang sedang login akan di-logout. Detail lengkap di `scripts/README.md`.

---

## 🟡 Backlog

Lihat `BACKLOG.md` untuk item yang sedang/belum dikerjakan.

---

## 📄 Lisensi

Proyek privat — hak cipta milik Balai Monitor Spektrum Frekuensi Radio Kelas I Yogyakarta.
