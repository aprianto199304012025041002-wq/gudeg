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

## 🟡 Hold — Butuh Setup Firebase (perlu PC)

87. **FCM Push Notification — APK**
    **Scope:** Notif native Android via Firebase Cloud Messaging untuk ekosistem GUDEG.
    **Trigger notif:**
    - Login baru di akun (ada device lain login)
    - Password diganti (oleh admin atau sendiri)
    - Akun diaktifkan / dinonaktifkan oleh admin
    - Ada user baru register (notif ke admin untuk approval)

    **Yang perlu disiapkan sebelum implementasi (di PC):**
    1. Buat Firebase project di console.firebase.google.com
    2. Add Android app → package name `je.xo.gudeg`
    3. Download `google-services.json` → masuk ke `apk/app/`
    4. Salin Server Key / Service Account key untuk backend GUDEG

    **Yang perlu diimplementasi:**
    - **APK** — dependency `firebase-messaging`, `MyFirebaseMessagingService.java`, kirim FCM token ke GUDEG setelah login
    - **GUDEG** — endpoint simpan FCM token, tabel `fcm_tokens` di Supabase, trigger kirim notif di titik-titik login/password/status/register
