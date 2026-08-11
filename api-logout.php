<?php
/**
 * api-logout.php — endpoint logout khusus APK Android
 * Hanya menerima request dari User-Agent GudegJogjaApp
 * Tidak butuh CSRF token karena request datang dari native app (bukan browser form)
 *
 * PENTING: File ini harus SELALU mengembalikan HTTP 200 agar WebView tidak
 * mendapat ERR_HTTP_RESPONSE_CODE_FAILURE. Semua error ditangani secara diam-diam.
 */

// Matikan output error agar tidak mencemari JSON response
@ini_set('display_errors', 0);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/LocalCache.php';
session_start_safe();

// Hanya izinkan dari APK — kembalikan 200 (bukan 403) agar WebView tidak error
if (!is_from_apk()) {
    http_response_code(200);
    echo json_encode(['status' => 'ignored', 'reason' => 'not_apk']);
    exit;
}

// Logout — tangani semua exception agar response selalu 200
try {
    if (!empty($_SESSION['user_id'])) {
        log_activity(
            $_SESSION['user_id'],
            $_SESSION['name']  ?? '',
            $_SESSION['email'] ?? '',
            'Logout (APK Closed)'
        );
        // Hapus cache user agar tidak bisa dipakai setelah logout APK
        LocalCache::clearUser((string)$_SESSION['user_id']);
    }

    $sess_id = session_id();
    try {
        getSupabase()->delete('sessions', ['id' => $sess_id]);
    } catch (\Throwable $e) {
        function_exists('gudeg_log') ? gudeg_log('WARNING', 'api-logout.php', 'gagal hapus session dari DB: ' . $e->getMessage()) : error_log('api-logout gagal hapus session: ' . $e->getMessage());
        // Lanjutkan destroy lokal meski DB gagal
    }

    session_unset();
    session_destroy();
} catch (\Throwable $e) {
    function_exists('gudeg_log') ? gudeg_log('ERROR', 'api-logout.php', 'unexpected error: ' . $e->getMessage()) : error_log('api-logout unexpected error: ' . $e->getMessage());
}

http_response_code(200);
echo json_encode(['status' => 'ok']);