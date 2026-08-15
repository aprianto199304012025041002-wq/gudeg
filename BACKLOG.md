# 🚀 GUDEG — Panduan Mulai Sesi Baru

Paste pesan ini di awal sesi baru:

---

**Halo! Clone repo gudeg, baca BACKLOG.md, lanjutkan dari item pertama yang belum selesai. Satu item per push, wajib syntax check sebelum commit, update BACKLOG setiap item selesai.**

Clone repo:
```
git clone https://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@github.com/aprianto199304012025041002-wq/gudeg.git /home/claude/gudeg
```

Baca backlog:
```
cat /home/claude/gudeg/BACKLOG.md
```

---

## Konteks Penting

- **Hosting:** InfinityFree (bukan Hostinger)
- **Stack:** PHP 8.1 + Supabase (PostgREST) + SQLite LocalCache
- **Deploy:** GitHub Actions → FTP → InfinityFree. Push ke `master` = auto deploy.
- **Live:** `gudeg.xo.je`
- **File sentral:** `includes/functions.php` — semua halaman depend ke sini via `require_once`
- **SQLite:** `/tmp/gudeg_cache.sqlite` — tabel: `token_blacklist`, `user_cache`, `rate_limit`, `pending_logs`, `meta`
- **Logging:** semua error lewat `gudeg_log(level, ctx, msg)` di `includes/functions.php` — format JSON
- **Jangan gunakan PAT read-only** untuk push — gunakan PAT di atas
- **PHP binary di container:** `php8.3` (bukan `php`)

## Aturan Kerja

- Kerjain satu item per satu, push setelah setiap item selesai
- **WAJIB** jalankan `php8.3 -l <file>` setelah setiap edit PHP sebelum commit — pernah ada syntax error yang bikin site down karena skip langkah ini
- Update BACKLOG.md setiap item selesai (coret dengan ~~teks~~ ✅)
- Baca file lengkap sebelum edit — jangan grep saja
- Kalau item sudah benar/tidak perlu diubah, tandai ✅ dan lanjut

---

## 🟡 Sedang / Perlu Hati-hati

75. **Google Fonts CDN bertentangan dengan CSP `font-src 'self'`**
    **File:** `login.php`, `includes/layout.php`, `.htaccess`, `includes/functions.php`
    **Masalah:** `login.php` dan `layout.php` (semua halaman post-login) load Google Fonts dari `fonts.googleapis.com` via tag `<link>`. Tapi CSP di dua tempat — `functions.php` (via `header()` PHP) dan `.htaccess` (via `Header set`) — keduanya set `font-src 'self'`. Artinya font tidak akan load di browser yang patuh CSP, dan ada dua CSP header yang dikirim sekaligus (konflik).
    **Solusi pilihan A (lebih bersih):** Hapus Google Fonts, ganti ke system font stack di CSS. Tidak perlu ubah CSP.
    **Solusi pilihan B:** Update CSP di kedua tempat tambah `fonts.googleapis.com fonts.gstatic.com` di `font-src`, dan tambah `fonts.googleapis.com` di `style-src`.

76. **`query-supabase.yml` — nama secret tidak konsisten dengan `deploy.yml`**
    **File:** `.github/workflows/query-supabase.yml`
    **Masalah:** `query-supabase.yml` pakai `secrets.SUPABASE_KEY` (baris `KEY="${{ secrets.SUPABASE_KEY }}"`), sedangkan `deploy.yml` pakai `secrets.SUPABASE_SERVICE_KEY`. Keduanya adalah service role key Supabase yang sama nilainya, tapi nama secret berbeda — jadi ada dua secret dengan nilai sama di GitHub. Seragamkan ke satu nama.
    **Solusi:** Ganti `secrets.SUPABASE_KEY` di `query-supabase.yml` jadi `secrets.SUPABASE_SERVICE_KEY`, lalu hapus secret `SUPABASE_KEY` dari GitHub Settings.

77. **`.htaccess` — `query-results/` tidak diblokir akses publik**
    **File:** `.htaccess`
    **Masalah:** Folder `query-results/` yang berisi output query Supabase bisa diakses langsung via browser: `https://gudeg.xo.je/query-results/result.json`. Walaupun folder sudah di `.gitignore` dan harusnya tidak ter-deploy, tidak ada jaminan — perlu diblokir di level server juga.
    **Solusi:** Tambah di `.htaccess`:
    ```apache
    <IfModule mod_authz_core.c>
        <Directory "/query-results">
            Require all denied
        </Directory>
    </IfModule>
    ```
    Atau pakai `<Files>` pattern yang match `query-results/`.

78. **`.htaccess` — beberapa file sensitif belum diblokir akses langsung**
    **File:** `.htaccess`
    **Masalah:** `BACKLOG.md`, `README.md`, `robots.txt` (sudah ok), `version.json` bisa diakses publik. `BACKLOG.md` berisi PAT GitHub dan detail teknis internal. `README.md` berisi info arsitektur detail.
    **Solusi:** Tambah `FilesMatch` untuk block akses ke `BACKLOG.md` dan `README.md` dari browser.

79. **`.htaccess` — CSP `script-src 'unsafe-inline'`**
    **File:** `.htaccess` (baris `Header set Content-Security-Policy`) dan `includes/functions.php` (baris `header("Content-Security-Policy: ...")`)
    **Masalah:** `'unsafe-inline'` di `script-src` memperbolehkan semua inline `<script>` dan event handler, melemahkan CSP secara signifikan terhadap XSS.
    **Solusi:** Audit semua halaman PHP — inventarisasi semua `<script>` inline, lalu pilih: (a) pindahkan ke file `.js` eksternal, atau (b) ganti ke `nonce`-based CSP. Ini pekerjaan besar, kerjakan setelah item lain selesai.

80. **`.htaccess` — CSP `style-src 'unsafe-inline'`**
    **File:** `.htaccess` dan `includes/functions.php`
    **Masalah:** Sama seperti #79 tapi untuk inline `<style>` dan `style=` attribute. Banyak halaman pakai inline style untuk komponen kecil.
    **Solusi:** Sama seperti #79 — audit dulu, lalu pindahkan ke file `.css` eksternal atau pakai nonce.

81. **`.htaccess` — `Order allow,deny` / `Deny from all` adalah syntax Apache 2.2**
    **File:** `.htaccess`
    **Masalah:** InfinityFree pakai Apache 2.4. Di 2.4, syntax yang benar adalah `Require all denied`. Syntax 2.2 masih diterima karena ada modul `mod_access_compat`, tapi lebih baik konsisten dengan versi yang dipakai.
    **Lokasi di file:** Ada di blok `<FilesMatch "\.( pem|key)$">`, `<Files "db.php">`, dan `<FilesMatch "^\\.">`.
    **Solusi:** Ganti semua `Order allow,deny` + `Deny from all` menjadi `Require all denied`. Wrap dengan `<IfModule mod_authz_core.c>` untuk keamanan.

82. **`is_new_ip()` — query `user_logs` pakai exact match string `'Login'`**
    **File:** `includes/functions.php`, fungsi `is_new_ip()` di sekitar baris 343
    **Masalah:** Fungsi query `user_logs` dengan filter `'activity' => 'Login'`. Kalau nilai string activity pernah berubah (misalnya jadi `'login'` lowercase atau `'Login berhasil'`), fungsi ini selalu return `true` (selalu anggap IP baru) dan kirim notifikasi palsu terus.
    **Solusi:** Definisikan konstanta `ACTIVITY_LOGIN = 'Login'` di `functions.php` dan pakai konstanta itu di semua tempat yang log aktivitas login, termasuk di `is_new_ip()`.

83. **`admin/index.php` — role `admin` tidak bisa di-set dari panel, tidak terdokumentasi**
    **File:** `admin/index.php`, sekitar baris 27-31 dan 359-363
    **Masalah:** Action `change_role` hanya izinkan nilai `user` dan `guest` (`in_array($new_role, ['user', 'guest'])`). Dropdown di HTML juga hanya punya dua pilihan itu. Tidak ada komentar kenapa `admin` tidak bisa di-set dari panel.
    **Solusi:** Tambah komentar PHP di atas blok `change_role` yang menjelaskan bahwa role `admin` harus di-set manual via Supabase SQL Editor — by design untuk cegah privilege escalation.

84. **`change-password.php` — form ganti password tidak ada proteksi double-submit**
    **File:** `change-password.php`
    **Masalah:** Form ganti password (action POST biasa, bukan `logout_session`/`logout_all_sessions`) tidak punya mekanisme disable button setelah submit. User bisa klik "Simpan" dua kali dan kirim dua request ganti password berturut-turut.
    **Solusi:** Tambah JS di akhir file — sama seperti yang sudah dikerjakan di `admin/apps.php` (#50): `form.addEventListener('submit', () => btn.disabled = true)`.

85. **Forgot password — belum ada fitur sama sekali**
    **Masalah:** Tidak ada halaman `forgot-password.php` atau `reset-password.php`. User yang lupa password harus minta admin reset manual via panel.
    **Catatan:** InfinityFree tidak support `mail()` PHP. Butuh layanan email eksternal (Resend, Mailgun, atau Supabase Edge Function). Ini fitur baru yang cukup besar — butuh: halaman form email, generate token reset, kirim email, halaman reset password dengan token.
    **Prioritas:** Medium — tidak ada workaround selain admin reset manual.

86. **Email verification saat register — belum ada**
    **Masalah:** User yang register langsung masuk DB dengan status `is_active = false` tanpa verifikasi email. Admin harus aktivasi manual. Tidak ada konfirmasi bahwa email yang dimasukkan valid.
    **Catatan:** Sama seperti #85, butuh infrastruktur email. Bisa dikerjakan bersamaan dengan #85.

87. **OG Tags — tidak ada file `og:image` yang proper**
    **File:** `includes/layout.php`
    **Masalah:** `og:image` sekarang mengarah ke `favicon.png` (fix dari #45) tapi ukurannya kecil. OG image yang proper untuk preview di WhatsApp/Telegram/Twitter harusnya 1200×630px.
    **Solusi:** Buat file `assets/og-image.png` ukuran 1200×630px berisi logo/nama GUDEG, lalu update `layout.php` mengarah ke file tersebut.

88. **`functions.php` — terlalu besar, perlu dipecah**
    **File:** `includes/functions.php` (~477 baris)
    **Masalah:** File ini campur banyak tanggung jawab: env loader, session management, auth, logging, sanitasi, CSRF, CORS, token blacklist, rate limit helpers, cleanup. Semua halaman depend ke sini.
    **Solusi:** Pecah ke beberapa file, misalnya `includes/auth.php`, `includes/session.php`, `includes/sanitize.php`. Tapi ini **risiko tinggi** — semua file harus di-update `require_once`-nya. Kerjakan terakhir setelah semua item lain selesai.

89. **CSRF token tidak di-rotate setelah setiap submit form**
    **File:** `includes/functions.php`, fungsi `csrf_token()` dan semua halaman dengan form POST
    **Masalah:** Token CSRF dibuat sekali per session dan tidak pernah di-rotate. Kalau token bocor (misal via XSS), attacker bisa pakai token yang sama berkali-kali selama session aktif.
    **Solusi:** Rotate token setelah setiap `csrf_verify()` berhasil. Ini butuh PRG (Post/Redirect/Get) pattern di semua form supaya tidak double-submit setelah reload — kompleks karena banyak halaman belum PRG.

---

## 🔴 Kritis / Butuh Segera atau Perencanaan Matang

90. **🔴 `query-results/` — DATA PRODUKSI NYATA DI GIT HISTORY**
    **File:** `.github/workflows/query-supabase.yml` (root penyebab), folder `query-results/`
    **Masalah:** Workflow `query-supabase.yml` pernah dijalankan dan hasilnya di-commit ke master. Data yang masuk history:
    - `sso_applications.json` — `client_secret_plain` KOMENK, KASPER, PASAR dalam plaintext
    - `users.json` — email + bcrypt hash + `remember_token` user nyata
    - `sessions.json` — session ID aktif + IP address user
    - `admin_logs.json` — email pegawai internal `@komdigi.go.id`
    - `user_logs.json` — histori aktivitas login/SSO
    - `user_applications.json` — mapping akses per user
    - `tables.json` — schema DB lengkap (struktur tabel terekspos)
    - `timestamp.txt` — timestamp query terakhir

    **Langkah penanganan (urut, jangan dilewati):**
    1. Install `git-filter-repo`: `pip install git-filter-repo`
    2. Hapus dari seluruh history: `git filter-repo --path query-results/ --invert-paths --force`
    3. Re-add remote: `git remote add origin https://<PAT>@github.com/aprianto199304012025041002-wq/gudeg.git`
    4. Force push: `git push origin master --force`
    5. Di GitHub: Settings → Branches → hapus protection kalau ada, lalu force push berhasil
    6. Regenerate semua `client_secret` KOMENK, KASPER, PASAR di panel `/admin/apps.php` GUDEG
    7. Update `client_secret` baru di config/env repo KOMENK, KASPER, PASAR masing-masing

91. **🔴 `query-supabase.yml` — Supabase project URL hardcode di file workflow**
    **File:** `.github/workflows/query-supabase.yml`, baris `BASE="https://nscgcspkotpghgmgbwyb.supabase.co/rest/v1"`
    **Masalah:** Project ID Supabase (`nscgcspkotpghgmgbwyb`) terekspos di repo. Siapapun yang akses repo bisa tahu project ID dan mencoba serangan langsung ke Supabase API.
    **Solusi:** Ganti hardcode URL dengan `${{ secrets.SUPABASE_URL }}` — secret ini sudah ada di GitHub (`deploy.yml` sudah pakai). Ubah baris `BASE=` menjadi `BASE="${{ secrets.SUPABASE_URL }}/rest/v1"` (atau sesuaikan format URL-nya).

92. **`query-supabase.yml` — input `table` tidak ada whitelist, bisa query tabel sembarang**
    **File:** `.github/workflows/query-supabase.yml`
    **Masalah:** Input `table` dari `workflow_dispatch` langsung dipakai di URL tanpa validasi: `"${BASE}/${{ github.event.inputs.table }}?..."`. Siapapun yang bisa trigger workflow ini bisa query tabel `users` (password hash), `sso_applications` (client secrets), atau tabel lain yang sensitif.
    **Solusi:** Tambah step validasi sebelum query — whitelist tabel yang boleh: misalnya hanya `sessions`, `user_logs`, `admin_logs`. Kalau tabel tidak ada di whitelist, exit dengan error.

93. **`query-supabase.yml` — auto-commit hasil query langsung ke master**
    **File:** `.github/workflows/query-supabase.yml`, step `Commit result`
    **Masalah:** Workflow otomatis commit `query-results/` ke `master` via PAT. Ini yang menyebabkan data produksi masuk history (#90). Setelah #90 dibersihkan, workflow ini harus diubah supaya tidak commit ke repo.
    **Solusi:** Hapus step `Commit result` dari workflow. Kalau output query masih dibutuhkan, gunakan GitHub Actions artifact (`actions/upload-artifact`) yang tersimpan sementara tanpa masuk git history.

94. **Supabase — belum ada index di kolom `last_activity` tabel `sessions`**
    **Bukan di codebase** — ini perubahan di Supabase langsung.
    **Masalah:** `change-password.php` dan `check_session_timeout()` di `functions.php` query tabel `sessions` yang difilter/diurutkan berdasarkan `last_activity`. Tanpa index, query ini full table scan.
    **Solusi:** Jalankan di Supabase SQL Editor:
    ```sql
    CREATE INDEX IF NOT EXISTS idx_sessions_last_activity ON sessions(last_activity DESC);
    CREATE INDEX IF NOT EXISTS idx_sessions_user_id ON sessions(user_id);
    ```

95. **Session storage PHP masih file-based, volatile di InfinityFree**
    **Masalah:** PHP session default disimpan sebagai file di `/tmp` InfinityFree. Kalau server restart atau `/tmp` di-clear, semua user ter-logout sekaligus. Tabel `sessions` di Supabase sudah ada tapi hanya dipakai sebagai log aktivitas, bukan sebagai session storage yang sebenarnya.
    **Solusi ideal:** Implementasi custom PHP session handler (`session_set_save_handler()`) yang baca/tulis ke tabel `sessions` Supabase atau ke SQLite LocalCache. Ini kompleks karena ada trade-off latency (setiap request = query Supabase untuk baca session). Kerjakan setelah semua item lain selesai.
