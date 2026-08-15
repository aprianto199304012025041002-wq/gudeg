<?php
/**
 * session.php — Session management, auth guards, timeout.
 *
 * Fungsi:
 *  - session_start_safe()      : start session dengan SqliteSessionHandler dan cookie params
 *  - require_login()           : redirect ke login jika belum auth
 *  - require_admin()           : redirect jika bukan admin
 *  - get_current_user_data()   : ambil data user dari session
 *  - check_session_timeout()   : logout otomatis setelah SESSION_TIMEOUT menit tidak aktif
 *  - is_from_apk()             : deteksi request dari APK Android
 */

// ── Konstanta session ─────────────────────────────────────────────────────────
define('SESSION_TIMEOUT', 30 * 60); // 30 menit tidak aktif → auto logout

// ── Activity constants ────────────────────────────────────────────────────────
// Definisikan di sini — pakai di semua log_activity() dan is_new_ip().
// Jangan tulis string literal 'Login' langsung di kode — rentan typo dan inkonsistensi.
define('ACTIVITY_LOGIN', 'Login');

function session_start_safe(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        // Daftarkan SQLite session handler (#95) — cegah session hilang saat /tmp di-clear.
        // Handler dibuat hanya sekali; kalau gagal (misal SQLite tidak tersedia),
        // PHP akan fallback ke default file handler secara otomatis.
        static $handler_registered = false;
        if (!$handler_registered) {
            try {
                require_once __DIR__ . '/SqliteSessionHandler.php';
                $handler = new SqliteSessionHandler();
                session_set_save_handler($handler, true);
                $handler_registered = true;
            } catch (\Throwable $e) {
                function_exists('gudeg_log')
                    ? gudeg_log('WARNING', 'session_start_safe', 'SqliteSessionHandler gagal, fallback ke file session: ' . $e->getMessage())
                    : error_log('session_start_safe: SqliteSessionHandler gagal: ' . $e->getMessage());
            }
        }

        $ua       = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                    || ($_SERVER['SERVER_PORT'] ?? 80) == 443;

        if (stripos($ua, 'GudegJogjaApp') !== false) {
            // APK: perpanjang umur cookie session jadi 30 hari
            session_set_cookie_params([
                'lifetime' => 30 * 24 * 3600,
                'secure'   => $is_https,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            // Web: session cookie standar + secure flag
            session_set_cookie_params([
                'lifetime' => 0,
                'secure'   => $is_https,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        session_start();
    }
}

/**
 * Deteksi apakah request berasal dari APK Android (WebView GUDEG JOGJA).
 * Dicek dari User-Agent yang di-set khusus di MainActivity.java.
 */
function is_from_apk(): bool
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return stripos($ua, 'GudegJogjaApp') !== false;
}

/**
 * Pastikan user sudah login. Redirect ke login.php jika belum.
 * Juga panggil check_session_timeout() untuk auto-logout.
 */
function require_login(): void
{
    session_start_safe();
    check_session_timeout();

    if (empty($_SESSION['user_id'])) {
        $redirect = urlencode($_SERVER['REQUEST_URI']);
        header("Location: /login.php?redirect=$redirect");
        exit;
    }
}

/**
 * Pastikan user adalah admin. Redirect ke 403 jika bukan.
 */
function require_admin(): void
{
    require_login();

    if (($_SESSION['role'] ?? '') !== 'admin') {
        header("Location: /403.php");
        exit;
    }
}

/**
 * Ambil data user yang sedang login dari session.
 * Return null jika belum login.
 */
function get_current_user_data(): ?array
{
    if (empty($_SESSION['user_id'])) return null;
    return [
        'id'    => $_SESSION['user_id'],
        'name'  => $_SESSION['name'],
        'email' => $_SESSION['email'],
        'role'  => $_SESSION['role'],
    ];
}

/**
 * Cek apakah sesi sudah timeout.
 * Dipanggil otomatis via require_login().
 * Request dari APK tidak pernah timeout (selama akun masih aktif).
 */
function check_session_timeout(): void
{
    session_start_safe();
    if (empty($_SESSION['user_id'])) return;

    // APK: skip timeout sepenuhnya, hanya update last_activity untuk log
    if (is_from_apk()) {
        $_SESSION['last_activity'] = time();
        return;
    }

    $last = $_SESSION['last_activity'] ?? null;
    if ($last !== null && (time() - $last) > SESSION_TIMEOUT) {
        $ip      = get_ip();
        $user_id = $_SESSION['user_id'] ?? 'unknown';
        gudeg_log('INFO', 'check_session_timeout', "Session timeout — user_id=$user_id IP=$ip");

        try {
            getSupabase()->delete('sessions', ['id' => session_id()]);
        } catch (\Throwable $e) {
            gudeg_log('WARNING', 'check_session_timeout', 'Gagal hapus session dari Supabase: ' . $e->getMessage());
        }

        session_unset();
        session_destroy();

        $redirect = urlencode($_SERVER['REQUEST_URI']);
        header("Location: /login.php?redirect=$redirect&reason=timeout");
        exit;
    }

    $_SESSION['last_activity'] = time();
}
