# 🚀 GUDEG — Panduan Mulai Sesi Baru

Paste pesan ini di awal sesi baru:

---

**Halo! Tolong clone repo gudeg, baca BACKLOG.md, lalu lanjutkan pekerjaan dari item pertama yang belum selesai. Kerjain satu per satu, syntax check sebelum push, update BACKLOG.md setiap item selesai.**

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
- **Deploy:** GitHub Actions → FTP → InfinityFree
- **Live:** `gudeg.xo.je`
- **File sentral:** `includes/functions.php` — semua halaman depend ke sini
- **SQLite:** `/tmp/gudeg_cache.sqlite` — tabel: `token_blacklist`, `user_cache`, `rate_limit`, `pending_logs`, `meta`
- **Logging:** semua error lewat `gudeg_log(level, ctx, msg)` — format JSON
- **Jangan gunakan PAT read-only** untuk push — gunakan PAT di atas

## Aturan Kerja

- Kerjain satu item per satu, push setelah setiap item
- **Wajib** `php8.3 -l <file>` sebelum commit — pernah terjadi syntax error yang bikin site down
- Update BACKLOG.md setiap item selesai (coret dengan ✅)
- Jangan ubah logic/fungsi vital kalau item hanya kosmetik
- Kalau item tidak perlu diubah (sudah benar), tandai ✅ dan lanjut

---

## 🟡 Sedang / Perlu Hati-hati

75. **Google Fonts CDN bertentangan dengan CSP `font-src 'self'`**
    `login.php` dan `layout.php` load dari `fonts.googleapis.com`. CSP set `font-src 'self'` — font tidak load di browser yang patuh CSP. Pilihan: hapus Google Fonts (pakai system font), atau update CSP tambah `fonts.googleapis.com` dan `fonts.gstatic.com`.

76. **`query-supabase.yml` — nama secret tidak konsisten**
    Pakai `secrets.SUPABASE_KEY`, `deploy.yml` pakai `secrets.SUPABASE_SERVICE_KEY`. Perlu diseragamkan.

77. **`.htaccess` — `query-results/` tidak diblokir akses publik**
    Siapapun bisa akses `https://gudeg.xo.je/query-results/sso_applications.json` langsung via browser. Tambah `<Directory>` block untuk folder ini.

78. **`.htaccess` — `git-filter-repo` tidak diblokir**
    File binary di root bisa didownload publik. (File sudah dihapus dari repo, tapi kalau ter-upload lagi perlu diblokir di .htaccess juga.)

79. **`.htaccess` — CSP `script-src 'unsafe-inline'`**
    Melemahkan CSP secara signifikan. Idealnya pakai `nonce` atau `hash` per script. Butuh audit semua inline script dulu.

80. **`.htaccess` — CSP `style-src 'unsafe-inline'`**
    Sama seperti poin di atas untuk inline style.

81. **`.htaccess` — `Order allow,deny` syntax Apache 2.2**
    Di Apache 2.4 (InfinityFree) lebih proper pakai `Require all denied`. Meski backward compatible, lebih baik konsisten.

82. **`is_new_ip()` di `functions.php` — query `user_logs` dengan string exact match**
    Filter `activity => 'Login'` — kalau nilai activity berubah, fungsi selalu anggap IP baru. Lebih robust simpan IP login di tabel `sessions` atau tabel khusus.

83. **`admin/index.php` — role `admin` tidak bisa di-set dari panel**
    Dropdown hanya `user` dan `guest`. By design tapi tidak didokumentasikan — perlu komentar eksplisit kenapa.

84. **`change-password.php` — proteksi double-submit**
    Form ganti password belum punya session lock seperti `register.php`.

85. **Forgot password — belum ada**
    Butuh infrastruktur email (InfinityFree tidak punya `mail()` — harus via Resend, Mailgun, atau Supabase Edge Function), token reset, halaman `reset-password.php`.

86. **Email verification saat register — belum ada**
    Akun baru butuh aktivasi manual admin. Infrastruktur email sama seperti forgot password.

87. **OG Tags — `og:image` belum ada file-nya**
    Sekarang mengarah ke `favicon.png` (sudah difix #45), tapi idealnya ada file 1200×630px khusus OG.

88. **`functions.php` — pecah jadi file terpisah**
    Terlalu besar, campur banyak tanggung jawab. Risiko refactor sedang — semua file depend ke sini.

89. **CSRF token rotate setelah submit**
    Butuh semua form POST diubah ke PRG pattern dulu. Kompleks karena banyak halaman tidak PRG.

---

## 🔴 Kritis / Butuh Segera atau Perencanaan Matang

90. **🔴 `query-results/` — DATA NYATA DI GIT HISTORY**
    8 file berisi data sensitif sudah masuk git history:
    - `sso_applications.json` — `client_secret_plain` plaintext KOMENK, KASPER, PASAR
    - `users.json` — email + bcrypt hash + `remember_token` user nyata
    - `sessions.json` — session ID aktif + IP address
    - `admin_logs.json` — email pegawai internal `@komdigi.go.id`
    - `user_logs.json` — histori aktivitas login/SSO
    - `user_applications.json` — mapping akses user ke aplikasi
    - `tables.json` — schema DB lengkap
    - `timestamp.txt` — timestamp query terakhir

    **Langkah wajib:**
    1. `git filter-repo --path query-results/ --invert-paths` (hapus dari history)
    2. Force push ke master
    3. Regenerate semua `client_secret` KOMENK, KASPER, PASAR di panel admin GUDEG
    4. Update secret baru di repo KOMENK, KASPER, PASAR

91. **🔴 `query-supabase.yml` — Supabase URL hardcode di repo**
    `BASE="https://nscgcspkotpghgmgbwyb.supabase.co/rest/v1"` langsung di workflow. Project ID terekspos. Pindah ke `${{ secrets.SUPABASE_URL }}`.

92. **`query-supabase.yml` — input `table` tidak ada whitelist**
    User bisa query tabel sembarang termasuk `users`, `sso_applications`. Perlu whitelist tabel yang boleh di-query.

93. **`query-supabase.yml` — commit langsung ke master tanpa review**
    Workflow push hasil query ke master via `PAT_TOKEN`. Siapapun yang bisa trigger `workflow_dispatch` bisa inject data ke repo.

94. **Supabase — belum ada index `last_activity` di tabel `sessions`**
    Query di `change-password.php` bisa lambat kalau data banyak. Jalankan di Supabase SQL Editor:
    ```sql
    CREATE INDEX idx_sessions_last_activity ON sessions(last_activity DESC);
    ```

95. **Session storage terpusat**
    Session PHP file-based di InfinityFree — kalau server restart semua user ter-logout. Tabel `sessions` di Supabase sudah ada tapi hanya sebagai log. Fix ideal: custom PHP session handler. Kompleks, ada trade-off latency.
