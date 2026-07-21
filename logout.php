<?php
require_once __DIR__ . '/includes/functions.php';
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

if (!empty($_SESSION['user_id'])) {
    log_activity($_SESSION['user_id'], $_SESSION['name'], $_SESSION['email'], 'Logout');
}

// Hapus sesi dari DB
$sess_id = session_id();
try {
    getSupabase()->delete('sessions', ['id' => $sess_id]);
} catch (Exception $e) {}

session_destroy();

// JANGAN kirim ?clear_biometric=1 saat logout biasa.
// Kredensial biometrik di APK hanya dihapus kalau user eksplisit
// minta hapus (misal ganti password) — bukan setiap tutup app.
// Dengan begitu fingerprint tetap tersedia saat buka app berikutnya.
header("Location: /login.php");
exit;