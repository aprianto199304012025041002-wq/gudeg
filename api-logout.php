<?php
/**
 * api-logout.php — endpoint logout khusus APK Android
 * Hanya menerima request dari User-Agent GudegJogjaApp
 * Tidak butuh CSRF token karena request datang dari WebView native
 */
require_once __DIR__ . '/includes/functions.php';
session_start_safe();

// Hanya izinkan dari APK
if (!is_from_apk()) {
    http_response_code(403);
    exit;
}

// Logout
if (!empty($_SESSION['user_id'])) {
    log_activity(
        $_SESSION['user_id'],
        $_SESSION['name']  ?? '',
        $_SESSION['email'] ?? '',
        'Logout (APK Closed)'
    );
}

$sess_id = session_id();
try {
    getSupabase()->delete('sessions', ['id' => $sess_id]);
} catch (Exception $e) {}

session_unset();
session_destroy();

http_response_code(200);
echo json_encode(['status' => 'ok']);