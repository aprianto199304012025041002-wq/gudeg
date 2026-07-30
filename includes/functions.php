<?php
date_default_timezone_set('Asia/Jakarta');

// db.php tetap di-require karena dia yang parsing config/.env (termasuk
// SUPABASE_URL & SUPABASE_SERVICE_KEY). getDB()/MySQL di dalamnya sudah
// tidak dipakai lagi di file ini, tapi biarkan sampai semua file lain
// selesai direfactor juga.
$db_config = __DIR__ . '/../config/db.php';
if (file_exists($db_config)) {
    require_once $db_config;
}
require_once __DIR__ . '/SupabaseClient.php';

// ── Security Headers ─────────────────────────────────────────────────────────
if (!headers_sent()) {
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
    header("Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src \'self\' \'unsafe-inline\' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; font-src \'self\' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src \'self\' data:; connect-src \'self\' https://*.supabase.co; frame-ancestors \'self\'; form-action \'self\'; base-uri \'self\'");
    header("Permissions-Policy: geolocation=(), camera=(), microphone=(), payment=(), usb=(), interest-cohort=()");
    header_remove("X-Powered-By");
}


// ── Helper: base64url encoding/decoding (RFC 7519) ───────────────────────────
// Didefinisikan di sini sekali — dipakai oleh sso.php dan verify.php.
function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
function base64url_decode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
}


function session_start_safe() {
    if (session_status() === PHP_SESSION_NONE) {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
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

function require_login() {
    session_start_safe();
    if (empty($_SESSION['user_id'])) {
        $redirect = urlencode($_SERVER['REQUEST_URI']);
        header("Location: /login.php?redirect=$redirect");
        exit;
    }
    // Cek session timeout setiap request
    check_session_timeout();
}

function require_admin() {
    require_login();
    if ($_SESSION['role'] !== 'admin') {
        header("Location: /dashboard.php");
        exit;
    }
}

function get_current_user_data() {
    session_start_safe();
    if (empty($_SESSION['user_id'])) return null;
    return [
        'id'    => $_SESSION['user_id'],
        'name'  => $_SESSION['name'],
        'email' => $_SESSION['email'],
        'role'  => $_SESSION['role'],
    ];
}

function log_activity($user_id, $name, $email, $activity) {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ip = explode(',', $ip)[0];
    $now = date('Y-m-d H:i:s');
    try {
        getSupabase()->insert('user_logs', [
            'user_id'    => $user_id,
            'user_name'  => $name,
            'user_email' => $email,
            'activity'   => $activity,
            'ip_address' => $ip,
            'created_at' => $now,
        ]);
    } catch (\Throwable $e) {
        error_log('log_activity gagal: ' . $e->getMessage());
    }
}

function log_admin_action($admin_id, $admin_name, $action, $target = '-') {
    $now = date('Y-m-d H:i:s');
    $ip  = get_ip(); // FIX: catat IP admin yang melakukan aksi
    try {
        getSupabase()->insert('admin_logs', [
            'admin_id'    => $admin_id,
            'admin_name'  => $admin_name,
            'action'      => $action,
            'target_user' => $target,
            'ip_address'  => $ip,
            'created_at'  => $now,
        ]);
    } catch (\Throwable $e) {
        error_log('log_admin_action gagal: ' . $e->getMessage());
    }
}

function get_ip() {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return explode(',', $ip)[0];
}

// ── CSRF helpers ─────────────────────────────────────────────────────────────

/**
 * Buat (atau ambil yang sudah ada) CSRF token untuk sesi ini.
 */
function csrf_token(): string {
    session_start_safe();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output hidden input CSRF — taruh di dalam setiap <form>.
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

/**
 * Validasi CSRF token dari POST request. Mati jika tidak valid.
 */
function csrf_verify(): void {
    session_start_safe();
    $token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }
}

// ── Login attempt rate limiting (simpan di session, cocok untuk InfinityFree) ─

/**
 * Catat percobaan login gagal.
 * Kunci berdasarkan IP + email agar tidak mudah di-bypass.
 */
function login_fail(string $email): void {
    session_start_safe();
    $key = 'lf_' . md5(get_ip() . strtolower($email));
    $_SESSION[$key]['count'] = ($_SESSION[$key]['count'] ?? 0) + 1;
    $_SESSION[$key]['last']  = time();
}

/**
 * Cek apakah IP+email sedang di-throttle.
 * Batas: 5 percobaan dalam 10 menit.
 */
function login_throttled(string $email): bool {
    session_start_safe();
    $key    = 'lf_' . md5(get_ip() . strtolower($email));
    $data   = $_SESSION[$key] ?? null;
    if (!$data) return false;
    // Reset setelah 10 menit
    if (time() - $data['last'] > 600) {
        unset($_SESSION[$key]);
        return false;
    }
    return $data['count'] >= 5;
}

/**
 * Reset hitungan gagal login (dipanggil saat login berhasil).
 */
function login_reset(string $email): void {
    session_start_safe();
    $key = 'lf_' . md5(get_ip() . strtolower($email));
    unset($_SESSION[$key]);
}

// ── Session timeout ───────────────────────────────────────────────────────────

define('SESSION_TIMEOUT', 30 * 60); // 30 menit tidak aktif → auto logout

/**
 * Deteksi apakah request berasal dari APK Android (WebView GUDEG JOGJA).
 * Dicek dari User-Agent yang di-set khusus di MainActivity.java.
 */
function is_from_apk(): bool {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return stripos($ua, 'GudegJogjaApp') !== false;
}

/**
 * Cek apakah sesi sudah timeout.
 * Dipanggil otomatis via require_login().
 * Request dari APK tidak pernah timeout (selama akun masih aktif).
 */
function check_session_timeout(): void {
    session_start_safe();
    if (empty($_SESSION['user_id'])) return;

    // APK: skip timeout sepenuhnya, hanya update last_activity untuk log
    if (is_from_apk()) {
        $_SESSION['last_activity'] = time();
        return;
    }

    $last = $_SESSION['last_activity'] ?? null;
    if ($last !== null && (time() - $last) > SESSION_TIMEOUT) {
        try {
            getSupabase()->delete('sessions', ['id' => session_id()]);
        } catch (\Throwable $e) {}

        session_unset();
        session_destroy();

        $redirect = urlencode($_SERVER['REQUEST_URI']);
        header("Location: /login.php?redirect=$redirect&reason=timeout");
        exit;
    }

    $_SESSION['last_activity'] = time();
}

// ── Password strength ─────────────────────────────────────────────────────────

/**
 * Validasi kekuatan password.
 * Return string pesan error, atau null jika valid.
 * Syarat: 8+ karakter, huruf besar, huruf kecil, angka, karakter spesial.
 */
function validate_password_strength(string $password): ?string {
    if (strlen($password) < 8) {
        return 'Password minimal 8 karakter.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return 'Password harus mengandung minimal 1 huruf besar.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        return 'Password harus mengandung minimal 1 huruf kecil.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        return 'Password harus mengandung minimal 1 angka.';
    }
    if (!preg_match('/[\W_]/', $password)) {
        return 'Password harus mengandung minimal 1 karakter spesial (!, @, #, dll).';
    }
    return null;
}

// ── Login IP detection ────────────────────────────────────────────────────────

/**
 * Cek apakah IP ini baru (belum pernah dipakai user ini login).
 * Return true jika IP baru/mencurigakan.
 */
function is_new_ip(int $user_id, string $ip): bool {
    try {
        $rows = getSupabase()->select('user_logs', [
            'user_id'    => $user_id,
            'ip_address' => $ip,
            'activity'   => 'Login',
        ], 'id', ['limit' => 1]);
        return empty($rows);
    } catch (\Throwable $e) {
        return false;
    }
}

// ── Client secret hashing ─────────────────────────────────────────────────────

/**
 * Hash client_secret sebelum disimpan ke DB.
 * Pakai password_hash agar tidak bisa di-reverse kalau DB bocor.
 */
function hash_client_secret(string $secret): string {
    return password_hash($secret, PASSWORD_BCRYPT);
}

/**
 * Verifikasi client_secret dari request terhadap hash di DB.
 */
function verify_client_secret(string $plain, string $hash): bool {
    return password_verify($plain, $hash);
}

// ── Auto-cleanup log lama ────────────────────────────────────────────────────

/**
 * Hapus log lebih dari 90 hari secara otomatis.
 * Dipanggil dari halaman log admin — tidak perlu cron job.
 * Jalankan dengan probabilitas 5% per request supaya tidak berat.
 */
function cleanup_old_logs(): void {
    // Jalankan hanya ~5% request supaya tidak memperlambat setiap page load
    if (rand(1, 100) > 5) return;

    $cutoff = date('Y-m-d H:i:s', strtotime('-90 days'));
    try {
        getSupabase()->delete('user_logs', ['created_at' => 'lt.' . $cutoff]);
        getSupabase()->delete('admin_logs', ['created_at' => 'lt.' . $cutoff]);
    } catch (\Throwable $e) {}
}

// ── Input sanitization ───────────────────────────────────────────────────────

/**
 * Potong dan bersihkan string input — cegah spam DB dan buffer overflow.
 * Gunakan di semua form input sebelum simpan ke DB.
 */
function sanitize_input(string $value, int $max_len = 255): string {
    return substr(trim($value), 0, $max_len);
}

/**
 * Validasi panjang input, return pesan error atau null jika valid.
 */
function validate_length(string $field, string $value, int $max): ?string {
    if (strlen($value) > $max) {
        return "$field terlalu panjang (maksimal $max karakter).";
    }
    return null;
}

// ── Token replay blacklist ────────────────────────────────────────────────────

/**
 * Tandai token sebagai sudah dipakai (blacklist berdasarkan JTI).
 * Return false kalau token sudah pernah dipakai (replay attack).
 */
function token_use(string $jti, int $exp): bool {
    try {
        $sb = getSupabase();

        // Hapus token expired dulu supaya tabel tidak membengkak
        try {
            $sb->delete('token_blacklist', ['exp' => 'lt.' . time()]);
        } catch (\Throwable $e) {
            // Kalau memang tidak ada baris yang cocok, delete() tetap aman —
            // ini cuma jaga-jaga kalau ada error jaringan sesaat.
        }

        // Cek apakah JTI sudah ada
        $existing = $sb->selectOne('token_blacklist', ['jti' => $jti], 'jti');
        if ($existing !== null) {
            return false; // sudah dipakai = replay attack
        }

        // Simpan JTI ke blacklist
        $sb->insert('token_blacklist', [
            'jti'     => $jti,
            'exp'     => $exp,
            'used_at' => date('Y-m-d H:i:s'),
        ]);
        return true;
    } catch (\Throwable $e) {
        // Kalau tabel belum ada / Supabase lagi bermasalah, skip blacklist (jangan crash)
        error_log('token_use gagal: ' . $e->getMessage());
        return true;
    }
}