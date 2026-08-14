# 🗂️ GUDEG — Backlog & Cue Card

> Penghubung antar sesi. Update setiap kali ada item selesai atau ditemukan item baru.
> **Terakhir diupdate:** Agustus 2026 — setelah audit baca baris per baris 57 file

---

## ✅ Sudah Selesai (sesi ini)

- [x] Favicon & judul tab browser semua halaman (favicon.ico, favicon.svg, favicon.png dibuat dari scratch)
- [x] Re-validasi role dari DB di `require_admin()` — cegah session stale
- [x] RateLimiter pindah dari file `/tmp` ke SQLite LocalCache
- [x] Fallback log ke SQLite kalau Supabase gagal (`pending_logs` queue, auto-flush)
- [x] Timeout cURL dipisah: log 3s, operasi kritis 10s
- [x] Hapus dead dependency `config/db.php`, parsing `.env` langsung di `functions.php`
- [x] Path SQLite bisa dikonfigurasi via `CACHE_PATH` di `.env`
- [x] Structured error logging via `gudeg_log(level, ctx, msg)` — JSON format
- [x] Proteksi double-submit register (session lock + finally)
- [x] `verify.php` — rate limit counter hanya naik kalau credential salah
- [x] `sso.php` — `die()` 400/404 sekarang set HTTP code dulu
- [x] `logout.php` — silent catch diganti `gudeg_log()` WARNING
- [x] `jwt.php` — error handling baca key file + CRITICAL log kalau corrupt/tidak bisa dibaca
- [x] `healthcheck.php` — tambah cek `pending_logs` queue
- [x] `robots.txt` — tambah Disallow untuk `ROTATE_KEY_GUDEG.md`, `version.json`, `jwks.json`

---

## 🔲 Backlog — Diurutkan dari Paling Mudah ke Paling Berisiko

### 🟢 Kecil / Aman (satu per satu, risiko sangat rendah)

1. ~~**`verify.php` — update komentar rate limiter**~~ ✅ sudah benar sejak sebelumnya

2. ~~**`verify.php` — silent catch cleanup token blacklist**~~ ✅ `0eba9f4`

3. ~~**`admin/logs.php` — silent catch cleanup token blacklist**~~ ✅
   Sama persis — `catch (\Throwable $e) {}` kosong. Tambah `gudeg_log('WARNING', ...)`.

4. ~~**`jwks.json` — tambah `gudeg_log()` saat key invalid**~~ ✅
   Kalau `openssl_pkey_get_public()` gagal, hanya return JSON error tanpa log. Tambah `gudeg_log('CRITICAL', ...)`.

5. ~~**`jwks.json` — duplikasi fungsi `base64url_encode_raw()`**~~ ✅
   Fungsi ini sudah ada di `functions.php` (sebagai `base64url_encode()`) dan `jwt.php` (private static). Ini versi ketiga. Bisa replace pakai `base64url_encode()` dari `functions.php`.

6. ~~**`robots.txt` — `/jwks.json` di-Disallow dua kali**~~ ✅
   Duplikasi entry. Hapus salah satu.

7. ~~**`robots.txt` — tambah entry yang kurang**~~ ✅
   Belum ada: `/query-results/`, `/git-filter-repo`, `/BACKLOG.md`, `/README.md`.

8. ~~**`git-filter-repo` — hapus dari repo**~~ ✅
   File binary tool pihak ketiga ada di root repo. Tidak seharusnya ada, tidak didokumentasikan, bisa diakses publik. Hapus dan tambah ke `.gitignore`.

9. ~~**`config/db.php` — hapus dari repo**~~ ✅
   File sudah tidak dipanggil di mana pun, sudah ada komentar deprecated. Risiko nol.

10. ~~**`version.json` — `deployed_at` otomatis dari deploy.yml**~~ ✅
    Saat ini diisi manual, rawan lupa update. Bisa di-generate otomatis di workflow.

11. ~~**`500.php` — teks "Tim teknis telah diberitahu" menyesatkan**~~ ✅
    Tidak ada mekanisme alerting di GUDEG. Ubah jadi "Silakan coba beberapa saat lagi atau hubungi administrator."

12. ~~**`500.php` — `href="javascript:location.reload()"` pada tombol Coba Lagi**~~ ✅
    Pakai `javascript:` pseudo-protocol yang bisa diblokir CSP lebih ketat ke depan. Ganti ke `onclick="location.reload()"` di `<button>`.

13. ~~**`404.php` & `500.php` — tombol "Kembali ke Dashboard" harusnya ke `/login.php`**~~ ✅
    User yang belum login kena 404/500 akan diarahkan ke `/dashboard.php` yang redirect ke login lagi. Lebih langsung ke `/login.php`.

14. ~~**`403.php`, `404.php`, `500.php` — emoji `🛡` lebih konsisten pakai Tabler icon**~~ ✅
    Emoji render berbeda antar platform. Tabler icon (`ti ti-shield`) sudah di-load di halaman ini.

15. ~~**`403.php` — email admin hardcode**~~ ✅
    `mailto:admin@balmon-jogja.go.id` di halaman 403. Kalau email berubah harus ingat update manual.

16. ~~**`admin/logs.php` — `$filter_qs` tidak di-`htmlspecialchars()` di pagination**~~ ✅
    `href="?<?= $filter_qs ?>&page=..."` bisa merusak HTML kalau ada karakter `<` atau `&` di query string. Tambah `htmlspecialchars($filter_qs)`.

17. ~~**`admin/logs.php` — Reset button HTML tidak valid**~~ ✅
    `<a href="..."><button>Reset</button></a>` — nested `<a>` dan `<button>` tidak valid HTML. Ganti ke `<a>` saja dengan styling button.

18. ~~**`sso.php` — `die()` access denied masih plaintext**~~ ✅
    Blok 403 access denied tidak pakai `json_encode`. Satu-satunya `die()` yang belum konsisten.

19. ~~**`logout.php` — tidak hapus session cookie di client**~~ ✅
    `session_destroy()` hapus data di server tapi tidak hapus cookie `PHPSESSID` di browser. Tambah `setcookie(session_name(), '', time() - 3600, '/')` sebelum destroy.

20. ~~**`healthcheck.php` — tambah cek `CACHE_PATH` writable**~~ ✅
    Kalau `CACHE_PATH` diset di `.env` tapi path tidak writable, SQLite gagal diam-diam tanpa error di healthcheck.

21. ~~**`healthcheck.php` — tambah report versi PHP**~~ ✅
    Versi PHP di InfinityFree bisa berubah. Berguna kalau healthcheck juga report `PHP_VERSION`.

22. ~~**`healthcheck.php` — cek RSA key validasi OpenSSL, bukan hanya `file_exists()`**~~ ✅
    `file_exists()` tidak jamin key bisa di-load OpenSSL. Tambah `openssl_pkey_get_private()` check.

23. ~~**`healthcheck.php` — `require_once LocalCache.php` dipanggil dua kali**~~ ✅
    Di check #5 dan #6 keduanya ada `require_once`. Yang kedua mubazir, hapus.

24. ~~**`healthcheck.php` — akses `pdo()` via `ReflectionClass`**~~ ✅
    Cara tidak rapi untuk akses method private. Tambah method public `LocalCache::getPendingLogStats()` dan update healthcheck pakai itu.

25. ~~**`LocalCache.php` — `logQueue()` tidak validasi `$tbl`**~~ ✅
    Parameter `$tbl` diterima tanpa whitelist. Tambah `if (!in_array($tbl, ['user_logs', 'admin_logs'])) return;`.

26. ~~**`LocalCache.php` — `flushLogs()` tidak validasi `$tbl` dari DB**~~ ✅
    Saat flush, `$row['tbl']` dari SQLite langsung dipass ke `$inserter` tanpa whitelist. Sama solusinya dengan poin di atas.

27. ~~**`LocalCache.php` — nama field `cached_at` menyesatkan**~~ ✅
    Isinya bukan waktu cache dibuat, tapi waktu expiry. Lebih akurat `expires_at`. Perlu migration data kalau diubah.

28. ~~**`LocalCache.php` — cleanup token blacklist setiap inisialisasi PDO**~~ ✅
    `DELETE FROM token_blacklist WHERE exp < time()` dijalankan setiap request pertama buka SQLite, termasuk request yang tidak butuh token blacklist. Pindah ke `cleanup()` saja.

29. ~~**`RateLimiter.php` — `md5()` untuk key hash**~~ ✅
    MD5 sudah deprecated untuk keamanan. Ganti ke `hash('sha256', ...)`. Tidak ada risiko langsung karena hanya dipakai sebagai lookup key, bukan password.

30. ~~**`RateLimiter.php` — `@unlink()` pakai error suppressor**~~ ✅
    Ganti ke cek return value atau try/catch tanpa `@`.

31. ~~**`SupabaseClient.php` — tidak ada `CURLOPT_CONNECTTIMEOUT`**~~ ✅
    Tanpa ini, kalau server Supabase tidak bisa dikoneksi (bukan lambat), PHP nunggu sampai OS timeout. Tambah `CURLOPT_CONNECTTIMEOUT => 5` di `selectRaw()` dan `request()`.

32. ~~**`SupabaseClient.php` — `selectRaw()` tidak punya parameter `$timeout`**~~ ✅
    Berbeda dengan `request()` yang sudah punya `$timeout` parameter, `selectRaw()` masih hardcode 10 detik.

33. ~~**`functions.php` — komentar CSP tidak akurat**~~ ✅
    Komentar bilang "semua aset sudah self-hosted, tidak ada CDN" padahal `login.php` dan `layout.php` masih load Google Fonts dari CDN.

34. ~~**`functions.php` — `csrf_verify()` die dengan plaintext**~~ ✅
    `die('Invalid CSRF token.')` tidak set HTTP code dulu dan return plaintext. Tambah `http_response_code(403)` dan return JSON atau redirect.

35. ~~**`functions.php` — `check_session_timeout()` silent catch**~~ ✅
    `catch (\Throwable $e) {}` kosong saat delete session dari Supabase. Tambah `gudeg_log('WARNING', ...)`.

36. ~~**`functions.php` — `cleanup_old_logs()` silent catch**~~ ✅
    Dua catch kosong setelah delete user_logs dan admin_logs. Tambah `gudeg_log('WARNING', ...)`.

37. ~~**`functions.php` — duplikasi parsing IP antara `log_activity()` dan `get_ip()`**~~ ✅
    `log_activity()` parse IP sendiri inline, `log_admin_action()` pakai `get_ip()`. Seragamkan: `log_activity()` juga pakai `get_ip()`.

38. ~~**`functions.php` — `cleanup_old_logs()` pakai `user_cache` untuk menyimpan metadata cleanup**~~ ✅
    Key `__cleanup_last__` disimpan di tabel `user_cache` yang seharusnya hanya untuk data user. Pisah ke tabel `meta` atau pakai tabel `rate_limit` dengan key khusus.

39. ~~**`jwt.php` — key file dibaca ulang setiap `generate()`/`verify()`**~~ ✅
    Tidak ada caching key ke static variable. Setiap request baca file dari disk. Cache ke static variable untuk hemat I/O.

40. ~~**`jwt.php` — `openssl_sign()` return value tidak dicek**~~ ✅
    Kalau gagal, `$signature` kosong dan token invalid tapi tidak ada exception. Tambah `if (!$signature) throw new Exception(...)`.

41. ~~**`jwt.php` — tidak validasi `alg` di header saat `verify()`**~~ ✅
    Header JWT tidak dicek bahwa `alg === 'RS256'`. Tambah validasi ini sebagai defense in depth.

42. ~~**`jwt.php` — duplikasi `base64url_encode/decode` dari `functions.php`**~~ ✅
    Implementasi private static yang berbeda dari versi global di `functions.php`. Bisa pakai yang global.

43. ~~**`layout.php` — `global $user` anti-pattern**~~ ✅
    `layout_head()` depend ke global variable `$user`. Kalau halaman lupa set `$user`, error tidak jelas. Lebih bersih pass sebagai parameter.

44. ~~**`layout.php` — `og:url` hardcode ke root**~~ ✅
    `<meta property="og:url" content="https://gudeg.xo.je/">` selalu root, tidak peduli halaman yang dibuka.

45. ~~**`layout.php` — `og:image` mengarah ke file yang tidak ada**~~ ✅
    `/assets/og-image.png` tidak ada di repo maupun server.

46. ~~**`login.php` — `gudeg_remember_email` cookie `secure` flag `false`**~~ ✅
    Cookie bisa dikirim via HTTP bukan hanya HTTPS. Di production yang sudah full HTTPS, flag ini harusnya `true`.

47. ~~**`login.php` — hidden input `redirect` tidak dibaca saat POST**~~ ✅
    `$redirect` hanya dibaca dari `$_GET` di awal, tidak dari `$_POST['redirect']`. Artinya hidden input `redirect` di form tidak pernah dipakai saat POST — mubazir.

48. ~~**`login.php` — cleanup session lama ada N+1 query**~~ ✅
    `foreach ($old_sessions as $s) { $sb->delete(...) }` — satu delete per session format lama. Kalau banyak, banyak request ke Supabase.

49. **`admin/apps.php` — `redirect` URL tidak divalidasi format**
    Bisa diisi string sembarang, bukan harus URL valid `https://`. Kalau diisi URL berbahaya, JWT dikirim ke sana. Tambah `filter_var($redirect, FILTER_VALIDATE_URL)`.

50. **`admin/apps.php` — tidak ada proteksi double-submit**
    Admin bisa klik "Daftarkan Aplikasi" dua kali dan bikin dua app identik. Tambah session lock seperti `register.php`.

51. **`admin/index.php` — `reset_password` tidak ada try/catch untuk delete sessions**
    `$sb->delete('sessions', ['user_id' => $target_id])` dipanggil tanpa try/catch. Kalau gagal, session target tidak terhapus tapi tidak ada log.

52. **`admin/index.php` — `delete_user` 4 operasi Supabase tanpa try/catch**
    Kalau satu operasi gagal di tengah, data setengah terhapus tanpa log dan tanpa rollback.

53. **`admin/index.php` — `update_apps` N+1 insert**
    `foreach ($app_ids as $aid) { $sb->insert(...) }` — satu request per aplikasi. Belum ada bulk insert di Supabase PostgREST, tapi bisa dipertimbangkan batching.

54. **`admin/index.php` — `select('user_applications')` tanpa filter**
    Fetch semua baris tanpa filter apapun. Seharusnya filter by user_id dari daftar user yang di-render.

55. **`change-password.php` — `logout_session` & `logout_all_sessions` tanpa try/catch**
    `$sb->delete('sessions', ...)` dipanggil tanpa error handling. Tambah try/catch dan `gudeg_log()`.

56. **`change-password.php` — silent catch saat hapus sessions lain setelah ganti password**
    `catch (\Throwable $e) {}` kosong. Tambah `gudeg_log('WARNING', ...)`.

57. **`verify.php` — tidak ada `OPTIONS` preflight handler**
    Browser kirim `OPTIONS` sebelum `POST` cross-origin. Sekarang `OPTIONS` diproses seperti request biasa dan return error karena tidak ada token. Tambah handler di awal: `if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }`.

58. **`verify.php` — CORS query ke Supabase setiap request**
    Fetch semua redirect URL dari DB untuk validasi CORS setiap ada `Origin` header. Bisa di-cache ke `LocalCache` dengan TTL pendek (5 menit).

59. **`verify.php` — `$_REQUEST` lebih baik `$_POST`**
    `$_REQUEST` ambil dari GET, POST, dan Cookie. Credential tidak seharusnya dikirim via GET (muncul di server log). Ganti ke `$_POST`.

60. **`sso.php` — `$state` tidak divalidasi panjang**
    `$_GET['state']` langsung di-`urlencode` tanpa batas panjang. URL bisa terlalu panjang. Tambah `substr($state, 0, 128)`.

61. **`.ftp-deploy-sync-state.json` — ada di repo padahal sudah di-gitignore**
    File sudah di `.gitignore` tapi sudah masuk git history. Perlu `git rm --cached .ftp-deploy-sync-state.json`.

62. **`.gitignore` — banyak entry yang kurang**
    Belum ada: `query-results/`, `git-filter-repo`, `assets/favicon-16.png`, `assets/favicon-32.png`, `BACKLOG.md` (opsional).

63. **`deploy.yml` — `exclude` tidak sertakan beberapa file**
    Belum exclude: `query-results/`, `BACKLOG.md`, `ROTATE_KEY_GUDEG.md`, `git-filter-repo`, `config/db.php`, `assets/favicon-16.png`, `assets/favicon-32.png`.

64. **`deploy.yml` — inject RSA key via `printf '%s'` berisiko newline**
    Kalau key punya newline yang tidak terbaca `printf`, key corrupt tapi tidak langsung ketahuan sampai user coba SSO.

65. **`deploy.yml` — `sleep 15` terlalu pendek untuk InfinityFree**
    InfinityFree butuh lebih lama propagate file. Sering menyebabkan healthcheck gagal padahal deploy berhasil. Naikkan ke 30-45 detik.

66. **`ROTATE_KEY_GUDEG.md` — klaim "private key pernah ter-commit" tidak terbukti**
    Commit `e6a61a7` label "rotate RSA key" tapi empty, tidak ada konten key di history. Dokumen menyesatkan — perlu diklarifikasi.

67. **`ROTATE_KEY_GUDEG.md` — disebut "via FTP (Hostinger)" padahal InfinityFree**
    Inkonsistensi hosting. Langkah 6 salah nama hosting.

68. **`ROTATE_KEY_GUDEG.md` — tidak ada langkah update KOMENK dan KASPER**
    Hanya ada langkah update PASAR, padahal KOMENK dan KASPER juga pakai public key yang sama untuk verify JWT.

69. **`ROTATE_KEY_GUDEG.md` — tidak ada langkah invalidate session aktif setelah rotate**
    Setelah key di-rotate, JWT yang sedang beredar jadi invalid. Perlu langkah eksplisit untuk handle ini.

70. **`README.md` — assets dideskripsikan "tidak ada CDN" tapi faktanya ada Google Fonts**
    Inkonsistensi dokumentasi.

71. **`README.md` — `query-results/` tidak ada peringatan data sensitif**
    Tidak ada catatan bahwa folder ini berisi data nyata dan tidak boleh di-commit.

72. **`README.md` — `query-supabase.yml` tidak terdokumentasikan**
    Hanya `deploy.yml` yang disebut di struktur proyek.

73. **`README.md` — `PAT_TOKEN` secret tidak terdokumentasikan**
    `query-supabase.yml` pakai `secrets.PAT_TOKEN` tapi tidak ada di tabel secrets README.

74. **`README.md` — inkonsistensi nama secret `SUPABASE_KEY` vs `SUPABASE_SERVICE_KEY`**
    README dokumentasikan `SUPABASE_SERVICE_KEY` tapi `query-supabase.yml` pakai `SUPABASE_KEY`.

---

### 🟡 Sedang / Perlu Hati-hati

75. **Google Fonts CDN bertentangan dengan CSP `font-src 'self'`**
    `login.php` dan `layout.php` (semua halaman post-login) load dari `fonts.googleapis.com`. CSP di `functions.php` dan `.htaccess` set `font-src 'self'` — font tidak load di browser yang patuh CSP. Pilihan: hapus Google Fonts (pakai system font), atau update CSP tambah `fonts.googleapis.com` dan `fonts.gstatic.com`.

76. **`query-supabase.yml` — nama secret tidak konsisten**
    Pakai `secrets.SUPABASE_KEY`, `deploy.yml` pakai `secrets.SUPABASE_SERVICE_KEY`. Perlu diseragamkan.

77. **`.htaccess` — `query-results/` tidak diblokir akses publik**
    Siapapun bisa akses `https://gudeg.xo.je/query-results/sso_applications.json` langsung via browser. Tambah `<Directory>` block untuk folder ini.

78. **`.htaccess` — `git-filter-repo` tidak diblokir**
    File binary di root bisa didownload publik.

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
    `/assets/og-image.png` tidak ada. Butuh buat aset visual (1200×630px).

88. **`functions.php` — pecah jadi file terpisah**
    Terlalu besar, campur banyak tanggung jawab. Risiko refactor sedang — semua file depend ke sini.

89. **CSRF token rotate setelah submit**
    Butuh semua form POST diubah ke PRG pattern dulu. Kompleks karena banyak halaman tidak PRG.

---

### 🔴 Kritis / Butuh Segera atau Perencanaan Matang

90. **🔴 `query-results/` tidak di-gitignore — DATA NYATA DI GIT HISTORY**
    8 file berisi data sensitif sudah masuk git history:
    - `sso_applications.json` — `client_secret_plain` plaintext KOMENK, KASPER, PASAR
    - `users.json` — email + bcrypt hash + `remember_token` user nyata
    - `sessions.json` — session ID aktif + IP address
    - `admin_logs.json` — email pegawai internal `@komdigi.go.id`
    - `user_logs.json` — histori aktivitas login/SSO
    - `user_applications.json` — mapping akses user ke aplikasi
    - `tables.json` — schema DB lengkap (struktur tabel terekspos)
    - `timestamp.txt` — timestamp query terakhir

    **Langkah wajib:**
    1. Tambah `query-results/` ke `.gitignore`
    2. `git rm -r --cached query-results/`
    3. `git filter-repo --path query-results/ --invert-paths` (hapus dari history)
    4. Force push ke master
    5. Regenerate semua `client_secret` KOMENK, KASPER, PASAR di panel admin GUDEG
    6. Update secret baru di repo KOMENK, KASPER, PASAR

91. **🔴 `query-supabase.yml` — Supabase URL hardcode di repo**
    `BASE="https://nscgcspkotpghgmgbwyb.supabase.co/rest/v1"` langsung di workflow. Project ID Supabase terekspos. Pindah ke `${{ secrets.SUPABASE_URL }}`.

92. **`query-supabase.yml` — input `table` tidak ada whitelist**
    User bisa query tabel sembarang termasuk `users`, `sso_applications`. Perlu whitelist tabel yang boleh di-query.

93. **`query-supabase.yml` — commit langsung ke master tanpa review**
    Workflow push hasil query ke master via `PAT_TOKEN`. Siapapun yang bisa trigger `workflow_dispatch` bisa inject data ke repo.

94. **Supabase session index `last_activity`**
    Belum ada index di kolom `last_activity`. Query di `change-password.php` bisa lambat kalau data banyak. Jalankan di Supabase SQL Editor: `CREATE INDEX idx_sessions_last_activity ON sessions(last_activity DESC)`.

95. **Session storage terpusat**
    Session PHP file-based di InfinityFree — kalau server restart semua user ter-logout. Tabel `sessions` di Supabase sudah ada tapi hanya sebagai log. Fix ideal: custom PHP session handler. Kompleks, ada trade-off latency.

---

## 📌 Catatan Konteks untuk Sesi Berikutnya

- **Stack:** PHP 8.1 + Supabase (PostgreSQL via PostgREST) + SQLite LocalCache
- **Hosting:** InfinityFree (BUKAN Hostinger) — tidak ada Redis, tidak ada cron job, `/tmp` volatile
- **Deploy:** GitHub Actions → FTP → InfinityFree. Secrets include RSA keys dan SUPABASE_SERVICE_KEY.
- **Repo:** `github.com/aprianto199304012025041002-wq/gudeg.git` — **private**
- **Live:** `gudeg.xo.je`
- **`functions.php`** adalah file sentral — semua halaman depend ke sini via `require_once`
- **`LocalCache` SQLite** (`/tmp/gudeg_cache.sqlite`) punya 4 tabel: `token_blacklist`, `user_cache`, `rate_limit`, `pending_logs`
- **Supabase project ID:** `nscgcspkotpghgmgbwyb` (terekspos di `query-supabase.yml`)
- **Secret di GitHub Actions gudeg:** `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`, `SUPABASE_URL`, `SUPABASE_SERVICE_KEY`, `HEALTH_KEY`, `RSA_PRIVATE_KEY`, `RSA_PUBLIC_KEY`, `PAT_TOKEN` (untuk query-supabase.yml), kemungkinan juga `SUPABASE_KEY` (berbeda dari SERVICE_KEY, perlu dicek)
- **Google Fonts** masih di-load dari CDN di `login.php` dan `layout.php` — bertentangan dengan CSP `font-src 'self'`
