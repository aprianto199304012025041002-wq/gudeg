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
- **File sentral:** `includes/functions.php` (aggregator) → memuat modul: `env.php`, `logger.php`, `headers.php`, `session.php`, `activity.php`, `csrf.php`, `validation.php`, `token.php`
- **SQLite:** `/tmp/gudeg_cache.sqlite` — tabel: `token_blacklist`, `user_cache`, `rate_limit`, `pending_logs`, `meta`
- **Session:** SQLite via `SqliteSessionHandler` (bukan file `/tmp`)
- **CSP:** Nonce-based (`csp_nonce()` di `includes/headers.php`) — bukan `unsafe-inline`
- **Logging:** semua error lewat `gudeg_log(level, ctx, msg)` — format JSON ke error log
- **Jangan gunakan PAT read-only** untuk push — gunakan PAT di atas
- **PHP binary di container:** `php8.3` (bukan `php`)

## Aturan Kerja

- Kerjain satu item per satu, push setelah setiap item selesai
- **WAJIB** jalankan `php8.3 -l <file>` setelah setiap edit PHP sebelum commit — pernah ada syntax error yang bikin site down karena skip langkah ini
- Update BACKLOG.md setiap item selesai, hapus item yang sudah selesai (jangan hanya dicoret)
- Baca file lengkap sebelum edit — jangan grep saja
- Kalau item sudah benar/tidak perlu diubah, tandai ✅ dan lanjut

## Tindak Lanjut Manual (belum dikerjakan)

- **GitHub Secrets:** hapus secret `SUPABASE_KEY` (lama), buat `SUPABASE_URL` = `https://nscgcspkotpghgmgbwyb.supabase.co`
- **Supabase SQL Editor:** jalankan `db/migrations.sql` (index `sessions.last_activity` dan `sessions.user_id`)

---

## 🟡 Pending — Butuh Infrastruktur Email

85. **Forgot password — belum ada fitur sama sekali**
    **Masalah:** Tidak ada halaman `forgot-password.php` atau `reset-password.php`. User yang lupa password harus minta admin reset manual via panel.
    **Catatan:** InfinityFree tidak support `mail()` PHP. Butuh layanan email eksternal (Resend, Mailgun, atau Supabase Edge Function). Ini fitur baru yang cukup besar — butuh: halaman form email, generate token reset, kirim email, halaman reset password dengan token.
    **Prioritas:** Medium — tidak ada workaround selain admin reset manual.

86. **Email verification saat register — belum ada**
    **Masalah:** User yang register langsung masuk DB dengan status `is_active = false` tanpa verifikasi email. Admin harus aktivasi manual. Tidak ada konfirmasi bahwa email yang dimasukkan valid.
    **Catatan:** Sama seperti #85, butuh infrastruktur email. Bisa dikerjakan bersamaan dengan #85.

---

## 🔴 Kritis — Butuh Konfirmasi Eksplisit

90. **🔴 DATA PRODUKSI NYATA DI GIT HISTORY**
    **Root cause:** Workflow `query-supabase.yml` pernah auto-commit hasil query ke master (sudah diperbaiki di #93, tapi history lama belum dibersihkan).
    **Data yang masuk history:**
    - `sso_applications.json` — `client_secret_plain` KOMENK, KASPER, PASAR dalam plaintext
    - `users.json` — email + bcrypt hash + `remember_token` user nyata
    - `sessions.json` — session ID aktif + IP address user
    - `admin_logs.json` — email pegawai internal `@komdigi.go.id`
    - `user_logs.json` — histori aktivitas login/SSO
    - `tables.json` — schema DB lengkap

    **Langkah penanganan (urut, jangan dilewati):**
    1. Install `git-filter-repo`: `pip install git-filter-repo`
    2. Hapus dari seluruh history: `git filter-repo --path query-results/ --invert-paths --force`
    3. Re-add remote: `git remote add origin https://<PAT>@github.com/aprianto199304012025041002-wq/gudeg.git`
    4. Force push: `git push origin master --force`
    5. Regenerate semua `client_secret` KOMENK, KASPER, PASAR di panel `/admin/apps.php`
    6. Update `client_secret` baru di config/env repo KOMENK, KASPER, PASAR masing-masing

    **⚠️ Operasi ini rewrite seluruh git history dan tidak bisa di-undo. Konfirmasi eksplisit diperlukan sebelum dikerjakan.**
