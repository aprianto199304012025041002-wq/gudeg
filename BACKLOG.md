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

### 🟢 Kecil / Aman (bisa dikerjain kapan saja, risiko sangat rendah)

1. **`config/db.php` — hapus dari repo**
   File sudah tidak dipanggil di mana pun (ada catatan di dalamnya). Bisa dihapus bersih dari repo. Risiko: nol, karena sudah tidak ada yang `require` dia.

2. **`ROTATE_KEY_GUDEG.md` — pindah ke dalam `.github/` atau hapus**
   File ini bisa dibuka publik via browser meski sudah di-Disallow di `robots.txt`. Lebih aman dipindah ke folder yang diblokir `.htaccess`, atau hapus saja karena sudah ada langkah-langkahnya di sini.

3. **`healthcheck.php` — tambah cek `CACHE_PATH` valid**
   Kalau `CACHE_PATH` diset di `.env` tapi path-nya tidak writable, SQLite akan gagal diam-diam. Healthcheck bisa verifikasi path ini bisa ditulis.

4. **`sso.php` — response `die()` masih plaintext di beberapa cabang**
   Ada `die()` di blok access denied (403) yang tidak pakai `json_encode` — tidak konsisten dengan yang sudah difix. Cek seluruh `die()` di file ini.

5. **`README.md` — tambah seksi troubleshooting**
   Kasus umum: SQLite tidak writable di `/tmp`, Supabase timeout, key tidak ketemu saat deploy. Dokumentasi langkah debug-nya.

---

### 🟡 Sedang / Perlu Hati-hati

6. **Forgot password**
   Belum ada fitur sama sekali. User yang lupa password harus minta reset ke admin. Butuh: email sending (pilihan: Supabase Auth email, atau SMTP sendiri), token reset, halaman baru `reset-password.php`. Di InfinityFree, `mail()` PHP tidak tersedia — harus via Supabase Edge Function atau API email eksternal (Resend, Mailgun, dll).

7. **Email verification saat register**
   Akun baru langsung masuk DB tapi butuh aktivasi manual admin. Idealnya ada email verifikasi dulu sebelum admin perlu aktivasi. Terhubung dengan infrastruktur email yang sama seperti forgot password.

8. **OG Tags (Open Graph)**
   `login.php` dan `register.php` sudah punya OG tags tapi gambar (`og:image`) belum diset — hanya teks. Butuh buat satu gambar preview (1200×630px) dan upload ke server, lalu tambah `<meta property="og:image">`. Kecil tapi butuh aset visual dulu.

9. **`functions.php` — pecah jadi file terpisah**
   File ini terlalu besar: campur security headers, CSRF, session, password validation, IP detection, log, token blacklist. Idealnya dipecah jadi `Auth.php`, `Security.php`, `Logger.php`. Risiko refactor sedang — semua file lain depend ke `functions.php`, perlu pastikan semua `require_once` diupdate.

10. **CSRF token rotate setelah submit**
    Saat ini token CSRF tidak di-rotate setelah form submit berhasil — token yang sama bisa dipakai ulang selama session hidup. Fix-nya butuh semua halaman yang punya form POST diubah ke PRG pattern (redirect setelah sukses), baru token bisa di-rotate di redirect. Saat ini `register.php`, `change-password.php`, dan `admin/*.php` tidak PRG.

11. **`change-password.php` — proteksi double-submit**
    Sama seperti `register.php` yang sudah difix — form ganti password juga bisa di-submit dua kali cepat. Tambah session lock yang sama.

---

### 🔴 Besar / Berisiko / Butuh Perencanaan

12. **CSP (Content Security Policy)**
    Belum ada header `Content-Security-Policy` sama sekali. Ini salah satu header keamanan paling penting untuk cegah XSS. Menambahkannya butuh audit semua inline `<script>`, `<style>`, dan external resource yang dipakai — kalau terlalu ketat bisa membreak UI. Butuh testing menyeluruh.

13. **Supabase session index**
    Tabel `sessions` di Supabase sudah ada `idx_sessions_user_id` tapi tidak ada index di `last_activity`. Query di `change-password.php` yang fetch semua sesi aktif user bisa lambat kalau data banyak. Butuh akses Supabase SQL Editor untuk tambah index — `CREATE INDEX idx_sessions_last_activity ON sessions(last_activity DESC)`.

14. **Private key di git history**
    Sudah dicek — **tidak terbukti ada** konten key di history. Commit `e6a61a7` label "rotate RSA key" tapi empty. Tapi kalau suatu saat key pernah ter-commit di history yang lebih awal (sebelum repo ini), perlu `git filter-repo` untuk bersihkan. Lihat `ROTATE_KEY_GUDEG.md` untuk langkah lengkapnya. **PERINGATAN: force push ke master — koordinasi dulu kalau ada collaborator.**

15. **Session storage terpusat**
    Saat ini session PHP file-based di server InfinityFree — kalau server restart, semua user ter-logout. Tabel `sessions` di Supabase sudah ada dan diisi saat login, tapi tidak dipakai sebagai sumber kebenaran. Fix ideal: custom session handler PHP yang baca/tulis ke Supabase atau SQLite. Kompleks, butuh testing menyeluruh, dan ada trade-off latency.

---

## 📌 Catatan Konteks untuk Sesi Berikutnya

- **Stack:** PHP 8.1 + Supabase (PostgreSQL via PostgREST) + SQLite LocalCache
- **Hosting:** InfinityFree — tidak ada Redis, tidak ada cron job, `/tmp` volatile tapi lebih persist dari file individual
- **Deploy:** GitHub Actions → FTP → InfinityFree. Secrets di GitHub Actions include RSA keys.
- **Repo:** `github.com/aprianto199304012025041002-wq/gudeg.git`
- **Live:** `gudeg.xo.je`
- **`functions.php`** adalah file sentral — semua halaman depend ke sini via `require_once`
- **LocalCache SQLite** (`/tmp/gudeg_cache.sqlite`) punya tabel: `token_blacklist`, `user_cache`, `rate_limit`, `pending_logs`
- **PAT GitHub** yang dipakai di sesi ini: sudah dipakai untuk clone, jangan commit ke repo
