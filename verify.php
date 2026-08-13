<?php
/**
 * verify.php — endpoint untuk aplikasi klien memverifikasi JWT
 *
 * GET/POST: ?token=<jwt>&client_id=<id>&client_secret=<secret>
 * Response JSON: { valid: true/false, payload: {...} }
 */
require_once __DIR__ . '/includes/jwt.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/RateLimiter.php';
require_once __DIR__ . '/includes/LocalCache.php';
header('Content-Type: application/json');

// FIX: Jangan pakai Access-Control-Allow-Origin: * untuk endpoint sensitif.
// Cek Origin request dan izinkan hanya jika cocok dengan domain aplikasi yang terdaftar.
// Kalau tidak ada Origin header (curl/server-to-server), tetap lanjut tanpa CORS header.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin) {
    $sb_cors = getSupabase();
    $rows    = $sb_cors->select('sso_applications', ['is_active' => true], 'redirect');
    $origins = array_column($rows, 'redirect');
    $allowed = false;
    foreach ($origins as $redir) {
        $parsed = parse_url($redir);
        $base   = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
        if (isset($parsed['port'])) $base .= ':' . $parsed['port'];
        if (rtrim($origin, '/') === rtrim($base, '/')) {
            $allowed = true;
            break;
        }
    }
    if ($allowed) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Methods: GET, POST');
        header('Vary: Origin');
    }
    // Jika origin tidak diizinkan, tidak ada CORS header → browser blokir otomatis
}

function fail($msg) {
    echo json_encode(['valid' => false, 'error' => $msg]);
    exit;
}

// base64url_encode / base64url_decode tersedia via includes/functions.php

$sb = getSupabase(); // inisialisasi $sb — dipakai oleh semua query di bawah

// Auto-cleanup token blacklist yang sudah expired (token asli saja, bukan rate limit)
try { $sb->delete('token_blacklist', ['exp' => 'lt.' . time()]); } catch (\Throwable $e) {}

// Rate limiting via RateLimiter (SQLite LocalCache, fallback file /tmp) — pisah dari token_blacklist
// Limit: 30 request per menit per IP
$verify_ip = trim(explode(',', ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown'))[0]);
if (RateLimiter::throttled($verify_ip, 'verify')) {
    $secs = RateLimiter::secondsLeft($verify_ip, 'verify');
    http_response_code(429);
    fail('Terlalu banyak request. Coba lagi dalam ' . $secs . ' detik.');
}

$token         = $_REQUEST['token'] ?? '';
$client_id     = $_REQUEST['client_id'] ?? '';
$client_secret = $_REQUEST['client_secret'] ?? '';

if (!$token || !$client_id || !$client_secret) {
    fail('token, client_id, dan client_secret wajib diisi.');
}

// 1. Cek aplikasi aktif — counter naik hanya kalau credential salah (bukan token expired)
$app = $sb->selectOne('sso_applications', ['client_id' => $client_id, 'is_active' => true]);

if (!$app || !verify_client_secret($client_secret, $app['client_secret'])) {
    // Credential salah — ini yang dihitung sebagai percobaan mencurigakan
    RateLimiter::fail($verify_ip, 'verify');
    fail('Aplikasi tidak ditemukan atau kredensial salah.');
}

// 2. Verify token
if ($app['use_signed_token']) {
    $payload = JWT::verify($token);
    if (!$payload) {
        fail('Token tidak valid atau sudah kedaluwarsa.');
    }
} else {
    // HMAC mode tidak didukung — semua aplikasi wajib pakai RSA (use_signed_token = true)
    fail('Mode token tidak didukung. Hubungi administrator untuk mengaktifkan signed token.');
}

$user_id = $payload['sub'] ?? null;
if (!$user_id) {
    fail('Token tidak mengandung identitas user.');
}

// FIX: Cek token replay — setiap token hanya boleh diverifikasi sekali
// JTI (JWT ID) unik per token, disimpan di blacklist sampai expired
$jti = $payload['jti'] ?? null;
if ($jti) {
    if (!token_use($jti, (int)($payload['exp'] ?? time()))) {
        fail('Token sudah pernah digunakan (replay attack).');
    }
}

// 3. Cek user masih aktif
$user = $sb->selectOne('users', ['id' => $user_id], 'id,name,email,role,is_active');

if (!$user) {
    fail('User tidak ditemukan.');
}
if (!$user['is_active']) {
    fail('Akun user telah dinonaktifkan.');
}

// 4. Cek user masih punya akses ke aplikasi ini
if ($user['role'] !== 'admin') {
    $access = $sb->selectOne('user_applications', ['user_id' => $user_id, 'application_id' => $app['id']], 'user_id');
    if (!$access) {
        fail('Akses user ke aplikasi ini telah dicabut.');
    }
}

// Reset rate limit counter — request valid tidak dihitung sebagai serangan
RateLimiter::reset($verify_ip, 'verify');

echo json_encode([
    'valid'   => true,
    'payload' => [
        'user_id' => $user['id'],
        'name'    => $user['name'],
        'email'   => $user['email'],
        'role'    => $user['role'],
        'exp'     => $payload['exp'] ?? null,
    ]
]);