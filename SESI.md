# 🚀 GUDEG — Panduan Mulai Sesi Baru

Paste pesan ini di awal sesi baru:

---

**Halo! Tolong baca file `BACKLOG.md` di repo gudeg, lalu lanjutkan pekerjaan dari item pertama yang belum selesai (belum dicoret). Kerjain satu per satu, cek dulu sebelum push, update BACKLOG.md setiap item selesai.**

Untuk clone repo:
```
git clone https://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@github.com/aprianto199304012025041002-wq/gudeg.git /home/claude/gudeg
```

Lalu baca BACKLOG:
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
- **SQLite:** `/tmp/gudeg_cache.sqlite` — tabel: `token_blacklist`, `user_cache`, `rate_limit`, `pending_logs`
- **Logging:** semua error lewat `gudeg_log(level, ctx, msg)` — format JSON
- **Jangan gunakan PAT read-only** (`READ-GUDPASKOM`) untuk push — gunakan PAT di atas

## Aturan Kerja

- Kerjain satu item per satu, push setelah setiap item
- Cek hasil sebelum push — tidak boleh ada error/bug/crash
- Update BACKLOG.md setiap item selesai (coret dengan ✅ dan commit hash)
- Jangan ubah logic/fungsi vital kalau item hanya kosmetik
- Kalau item tidak perlu diubah (sudah benar), tandai ✅ dan lanjut ke berikutnya
