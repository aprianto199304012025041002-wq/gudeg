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

## Tindak Lanjut Manual

✅ Semua selesai (16 Agustus 2026):
- Secret `SUPABASE_KEY` lama dihapus, `SUPABASE_URL` sudah ada di GitHub Secrets
- `db/migrations.sql` sudah dijalankan di Supabase SQL Editor

---

## 🟡 Hold — Butuh Setup SMTP

85. **Forgot password — belum ada fitur sama sekali**
    **Masalah:** Tidak ada halaman `forgot-password.php` atau `reset-password.php`. User yang lupa password harus minta admin reset manual via panel.
    **Catatan:** InfinityFree tidak support `mail()` PHP. Butuh layanan email eksternal (Resend, Mailgun, atau Supabase Edge Function). Ini fitur baru yang cukup besar — butuh: halaman form email, generate token reset, kirim email, halaman reset password dengan token.
    **Prioritas:** Medium — tidak ada workaround selain admin reset manual.

86. **Email verification saat register — belum ada**
    **Masalah:** User yang register langsung masuk DB dengan status `is_active = false` tanpa verifikasi email. Admin harus aktivasi manual. Tidak ada konfirmasi bahwa email yang dimasukkan valid.
    **Catatan:** Sama seperti #85, butuh infrastruktur email. Bisa dikerjakan bersamaan dengan #85.


