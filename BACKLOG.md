# 🗂️ GUDEG — Backlog & Cue Card

> Dokumen ini adalah penghubung antar sesi. Update setiap kali ada item selesai atau ditemukan item baru.
> **Terakhir diupdate:** Agustus 2026

---

## ✅ Sudah Selesai (sesi ini)

- [x] Favicon & judul tab browser semua halaman
- [x] Re-validasi role dari DB di `require_admin()` — cegah session stale
- [x] RateLimiter pindah dari file `/tmp` ke SQLite LocalCache
- [x] Fallback log ke SQLite kalau Supabase gagal (`pending_logs` queue)
- [x] Timeout cURL dipisah: log 3s, operasi kritis 10s
- [x] Hapus dead dependency `config/db.php`, parsing `.env` langsung di `functions.php`
- [x] Path SQLite bisa dikonfigurasi via `CACHE_PATH` di `.env`
- [x] Structured error logging via `gudeg_log(level, ctx, msg)` — JSON format
- [x] Proteksi double-submit register (session lock + finally)
- [x] `verify.php` — rate limit counter hanya naik kalau credential salah
- [x] `sso.php` — `die()` sekarang set HTTP code 400/404 dulu
- [x] `logout.php` — silent catch diganti `gudeg_log()` WARNING
- [x] `jwt.php` — error handling baca key file + CRITICAL log kalau corrupt
- [x] `healthcheck.php` — tambah cek `pending_logs` queue
- [x] `robots.txt` — tambah Disallow untuk `ROTATE_KEY_GUDEG.md`, `version.json`, `jwks.json`

---

## 🔲 Backlog — Diurutkan dari Paling Mudah ke Paling Berisiko

### 🟢 Kecil / Aman

1. **`verify.php` — update komentar rate limiter**
   Komentar masih bilang "file-based /tmp" padahal sudah SQLite. Satu baris, tidak ada risiko.

2. **`403.php` — email admin hardcode**
   `mailto:admin@balmon-jogja.go.id` hardcode di halaman 403. Bukan credential, tapi kalau email berubah harus ingat update di sini. Idealnya jadi konstanta di `.env`.

3. **`config/db.php` — hapus dari repo**
   File sudah tidak dipanggil di mana pun. Risiko: nol.

4. **`ROTATE_KEY_GUDEG.md` — pindah ke `.github/` atau hapus**
   Bisa dibuka publik via browser meski sudah di-Disallow di `robots.txt`. Lebih aman dipindah ke folder yang diblokir `.htaccess`.

5. **`healthcheck.php` — tambah cek `CACHE_PATH` valid**
   Kalau `CACHE_PATH` diset di `.env` tapi path tidak writable, SQLite gagal diam-diam.

6. **`sso.php` — satu `die()` access denied masih plaintext**
   Blok 403 access denied tidak pakai `json_encode` — tidak konsisten dengan yang sudah difix.

7. **`README.md` — tambah seksi troubleshooting**
   Kasus umum: SQLite tidak writable, Supabase timeout, key tidak ketemu saat deploy.

---

### 🟡 Sedang / Perlu Hati-hati

8. **Google Fonts CDN bertentangan dengan CSP `font-src 'self'`**
   Semua halaman (`login.php`, `register.php`, `403.php`, `404.php`, `500.php`, `layout.php`) load Google Fonts dari CDN eksternal, tapi CSP di `functions.php` dan `.htaccess` set `font-src 'self'`. Font tidak akan load di browser yang patuh CSP. Pilihan: hapus Google Fonts dan pakai system font, atau update CSP allow `fonts.googleapis.com` dan `fonts.gstatic.com`.

9. **`query-supabase.yml` — nama secret tidak konsisten**
   Workflow ini pakai `secrets.SUPABASE_KEY`, tapi `deploy.yml` pakai `secrets.SUPABASE_SERVICE_KEY`. Kemungkinan dua secret berbeda atau salah satu tidak ter-set. Perlu diseragamkan.

10. **Forgot password**
    Belum ada fitur sama sekali. Butuh infrastruktur email (di InfinityFree `mail()` tidak tersedia — harus via Resend, Mailgun, atau Supabase Edge Function), token reset, dan halaman `reset-password.php`.

11. **Email verification saat register**
    Akun baru butuh aktivasi manual admin. Idealnya ada email verifikasi dulu. Terhubung dengan infrastruktur email yang sama seperti forgot password.

12. **OG Tags — `og:image` belum diset**
    `login.php` dan `register.php` sudah punya OG tags tapi belum ada gambar preview. Butuh buat aset visual (1200×630px) dulu.

13. **`functions.php` — pecah jadi file terpisah**
    Terlalu besar: campur security headers, CSRF, session, password, log, token blacklist. Risiko refactor sedang — semua file lain depend ke sini.

14. **CSRF token rotate setelah submit**
    Token tidak di-rotate setelah form berhasil. Fix butuh semua halaman dengan form POST diubah ke PRG pattern dulu (`register.php`, `change-password.php`, `admin/*.php`).

15. **`change-password.php` — proteksi double-submit**
    Sama seperti `register.php` yang sudah difix — form ganti password belum punya session lock.

---

### 🔴 Besar / Kritis / Butuh Segera atau Perencanaan

16. **🔴 `query-results/` tidak di-gitignore — DATA NYATA SUDAH DI GIT HISTORY**
    Enam file berisi data sensitif sudah masuk git history dan bisa dilihat siapapun yang punya akses repo:
    - `sso_applications.json` — `client_secret_plain` plaintext untuk KOMENK, KASPER, PASAR
    - `users.json` — email + bcrypt hash + `remember_token` user nyata
    - `sessions.json` — session ID aktif + IP address user
    - `admin_logs.json` — email pegawai internal (`@komdigi.go.id`)
    - `user_logs.json` — histori aktivitas login/SSO user nyata
    - `user_applications.json` — mapping akses user ke aplikasi

    **Yang harus dilakukan:**
    1. Tambah `query-results/` ke `.gitignore` sekarang
    2. Hapus `query-results/` dari git tracking (`git rm -r --cached query-results/`)
    3. Bersihkan dari git history (`git filter-repo --path query-results/ --invert-paths`)
    4. Regenerate semua `client_secret` di panel admin GUDEG (KOMENK, KASPER, PASAR) karena plaintext sudah terpublik
    5. Update GitHub Secrets di repo PASAR, KOMENK dengan secret baru

17. **🔴 `query-supabase.yml` — Supabase project URL hardcode di repo**
    `BASE="https://nscgcspkotpghgmgbwyb.supabase.co/rest/v1"` hardcode langsung di workflow file. Siapapun yang baca repo tahu project ID Supabase. Harus dipindah ke secret `SUPABASE_URL` yang sudah ada, konsisten dengan `deploy.yml`.

18. **Supabase session index**
    Tabel `sessions` belum ada index di `last_activity`. Query di `change-password.php` bisa lambat kalau data banyak. Butuh akses Supabase SQL Editor: `CREATE INDEX idx_sessions_last_activity ON sessions(last_activity DESC)`.

19. **Session storage terpusat**
    Session PHP file-based di InfinityFree — kalau server restart semua user ter-logout. Tabel `sessions` di Supabase sudah ada tapi hanya sebagai log, bukan sumber kebenaran. Fix ideal: custom session handler PHP. Kompleks, ada trade-off latency.

---

## 📌 Catatan Konteks untuk Sesi Berikutnya

- **Stack:** PHP 8.1 + Supabase (PostgreSQL via PostgREST) + SQLite LocalCache
- **Hosting:** InfinityFree — tidak ada Redis, tidak ada cron job, `/tmp` volatile tapi lebih persist dari file individual
- **Deploy:** GitHub Actions → FTP → InfinityFree. Secrets di GitHub Actions include RSA keys.
- **Repo:** `github.com/aprianto199304012025041002-wq/gudeg.git`
- **Live:** `gudeg.xo.je`
- **`functions.php`** adalah file sentral — semua halaman depend ke sini via `require_once`
- **LocalCache SQLite** (`/tmp/gudeg_cache.sqlite`) punya tabel: `token_blacklist`, `user_cache`, `rate_limit`, `pending_logs`
- **Supabase project ID:** `nscgcspkotpghgmgbwyb` (terekspos di `query-supabase.yml`)
- **PAT GitHub** yang dipakai di sesi ini: sudah dipakai untuk clone, jangan commit ke repo
