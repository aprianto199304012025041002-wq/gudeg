<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/LocalCache.php';
session_start_safe();

// Logout harus diproteksi CSRF agar tidak bisa dipicu dari luar
// Terima GET dengan token (untuk link biasa) atau POST dengan csrf_verify
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
} else {
    // GET: validasi token dari query string
    $token = $_GET['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        header("Location: /dashboard.php");
        exit;
    }
}

$user_id_logout = $_SESSION['user_id'] ?? null;

if ($user_id_logout) {
    log_activity($user_id_logout, $_SESSION['name'], $_SESSION['email'], 'Logout');
    // Hapus cache user agar tidak bisa dipakai setelah logout
    LocalCache::clearUser((string)$user_id_logout);
}

// Hapus sesi dari DB
$sess_id = session_id();
try {
    getSupabase()->delete('sessions', ['id' => $sess_id]);
} catch (\Throwable $e) {
    function_exists('gudeg_log') ? gudeg_log('WARNING', 'logout.php', 'gagal hapus session dari DB: ' . $e->getMessage()) : error_log('logout gagal hapus session: ' . $e->getMessage());
}

// Hapus cookie PHPSESSID di browser
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '',
        time() - 3600,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

// JANGAN kirim ?clear_biometric=1 saat logout biasa.
// Kredensial biometrik di APK hanya dihapus kalau user eksplisit
// minta hapus (misal ganti password) — bukan setiap tutup app.
// Dengan begitu fingerprint tetap tersedia saat buka app berikutnya.
header("Location: /login.php");
exit;