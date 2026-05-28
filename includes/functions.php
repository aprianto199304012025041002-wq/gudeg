<?php
date_default_timezone_set('Asia/Jakarta');

$db_config = __DIR__ . '/../config/db.php';
if (file_exists($db_config)) {
    require_once $db_config;
}

function session_start_safe() {
    if (session_status() === PHP_SESSION_NONE) {
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
    $db = getDB();
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ip = explode(',', $ip)[0];
    $now = date('Y-m-d H:i:s');
    $stmt = $db->prepare("INSERT INTO user_logs (user_id, user_name, user_email, activity, ip_address, created_at) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$user_id, $name, $email, $activity, $ip, $now]);
}

function log_admin_action($admin_id, $admin_name, $action, $target = '-') {
    $db  = getDB();
    $now = date('Y-m-d H:i:s');
    $ip  = get_ip(); // FIX: catat IP admin yang melakukan aksi
    $stmt = $db->prepare("INSERT INTO admin_logs (admin_id, admin_name, action, target_user, ip_address, created_at) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$admin_id, $admin_name, $action, $target, $ip, $now]);
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
 * Cek apakah sesi sudah timeout.
 * Dipanggil otomatis via require_login().
 */
function check_session_timeout(): void {
    session_start_safe();
    if (empty($_SESSION['user_id'])) return;

    $last = $_SESSION['last_activity'] ?? null;
    if ($last !== null && (time() - $last) > SESSION_TIMEOUT) {
        $db = getDB();
        try {
            $db->prepare("DELETE FROM sessions WHERE id = ?")->execute([session_id()]);
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
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT 1 FROM user_logs WHERE user_id = ? AND ip_address = ? AND activity = 'Login' LIMIT 1");
        $stmt->execute([$user_id, $ip]);
        return !$stmt->fetch();
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

// ── Token replay blacklist ────────────────────────────────────────────────────

/**
 * Tandai token sebagai sudah dipakai (blacklist berdasarkan JTI).
 * Return false kalau token sudah pernah dipakai (replay attack).
 */
function token_use(string $jti, int $exp): bool {
    $db = getDB();
    try {
        // Hapus token expired dulu supaya tabel tidak membengkak
        $db->prepare("DELETE FROM token_blacklist WHERE exp < ?")->execute([time()]);

        // Cek apakah JTI sudah ada
        $stmt = $db->prepare("SELECT 1 FROM token_blacklist WHERE jti = ? LIMIT 1");
        $stmt->execute([$jti]);
        if ($stmt->fetch()) {
            return false; // sudah dipakai = replay attack
        }

        // Simpan JTI ke blacklist
        $db->prepare("INSERT INTO token_blacklist (jti, exp, used_at) VALUES (?, ?, NOW())")
           ->execute([$jti, $exp]);
        return true;
    } catch (\Throwable $e) {
        // Kalau tabel belum ada, skip blacklist (jangan crash)
        return true;
    }
}