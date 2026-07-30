<?php
/**
 * verify.php — endpoint untuk aplikasi klien memverifikasi JWT
 *
 * GET/POST: ?token=<jwt>&client_id=<id>&client_secret=<secret>
 * Response JSON: { valid: true/false, payload: {...} }
 */
require_once __DIR__ . '/includes/jwt.php';
require_once __DIR__ . '/includes/functions.php';
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

// Auto-cleanup token blacklist yang sudah expired.
// Pisah antara token asli (jti tidak pakai prefix 'rl_') dan rate limit counter (prefix 'rl_'),
// supaya cleanup token expired tidak ikut menghapus counter rate limit yang masih aktif.
try { $sb->delete('token_blacklist', ['jti' => 'not.like.rl_%', 'exp' => 'lt.' . time()]); } catch (\Throwable $e) {}

// FIX: Rate limiting verify.php — cegah brute force client_secret
$verify_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$verify_ip = trim(explode(',', $verify_ip)[0]);
$rl_jti    = 'rl_verify_' . md5($verify_ip);
$rl_window = 60;   // 1 menit
$rl_max    = 30;   // max 30 request per menit per IP

try {
    // Rate limit counter disimpan di token_blacklist dengan prefix jti='rl_verify_*'.
    // exp = unix timestamp window_end (bukan token expiry), payload = request counter.
    // Cleanup token asli (baris di atas) sudah dipisah dengan filter 'not.like.rl_%',
    // jadi cleanup rate limit di sini tidak akan ikut menghapus token asli yang masih valid.

    // Cleanup rate limit record yang window-nya sudah habis
    $rl_window_end = time() + $rl_window;
    $sb->delete('token_blacklist', ['jti' => 'like.rl_%', 'exp' => 'lt.' . time()]);

    // Cek record rate limit untuk IP ini
    $rl_row = $sb->selectOne('token_blacklist', ['jti' => $rl_jti], 'exp,payload');

    if ($rl_row) {
        // exp = unix timestamp kapan window habis (nilai besar, tidak konflik dengan cleanup)
        // payload = jumlah request dalam window ini
        $req_count = (int)$rl_row['payload'];
        if ($req_count >= $rl_max) {
            http_response_code(429);
            fail('Terlalu banyak request. Coba lagi dalam 1 menit.');
        }
        $sb->update('token_blacklist', ['jti' => $rl_jti], ['payload' => (string)($req_count + 1)]);
    } else {
        // Request pertama dari IP ini dalam window ini
        // exp = kapan window habis (unix timestamp besar), payload = counter
        $sb->insert('token_blacklist', [
            'jti'     => $rl_jti,
            'exp'     => $rl_window_end,
            'payload' => '1',
            'used_at' => date('Y-m-d H:i:s'),
        ]);
    }
} catch (\Throwable $e) {
    // Kalau rate limit gagal karena DB error, tetap lanjut jangan block request
}

$token         = $_REQUEST['token'] ?? '';
$client_id     = $_REQUEST['client_id'] ?? '';
$client_secret = $_REQUEST['client_secret'] ?? '';

if (!$token || !$client_id || !$client_secret) {
    fail('token, client_id, dan client_secret wajib diisi.');
}

// 1. Cek aplikasi aktif
// FIX: Cari berdasarkan client_id saja, verifikasi secret pakai password_verify
// (secret di DB sekarang tersimpan sebagai bcrypt hash)
// Sertakan is_active=true di query awal agar app nonaktif langsung tidak ketemu (null),
// tanpa bocorkan alasan spesifik ke caller.
$app = $sb->selectOne('sso_applications', ['client_id' => $client_id, 'is_active' => true]);

if (!$app || !verify_client_secret($client_secret, $app['client_secret'])) {
    fail('Aplikasi tidak ditemukan atau kredensial salah.');
}

// 2. Verify token
if ($app['use_signed_token']) {
    $payload = JWT::verify($token);
    if (!$payload) {
        fail('Token tidak valid atau sudah kedaluwarsa.');
    }
} else {
    // HMAC-SHA256: key pakai client_secret_plain (sama persis dengan sso.php)
    // client_secret_plain adalah hex 64-char yang disimpan plaintext di DB,
    // hanya bisa diakses via service_role key Supabase (server-side only).
    if (empty($app['client_secret_plain'])) {
        fail('Konfigurasi aplikasi belum lengkap. Hubungi administrator.');
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) fail('Format token salah.');
    [$header, $body, $sig] = $parts;

    // Verifikasi HMAC pakai client_secret_plain — konsisten dengan sso.php
    $expected = base64url_encode(hash_hmac('sha256', "$header.$body", $app['client_secret_plain'], true));
    if (!hash_equals($expected, $sig)) fail('Tanda tangan token tidak valid.');

    $payload = json_decode(base64url_decode($body), true);
    if (!$payload) fail('Payload token tidak dapat dibaca.');
    if (($payload['exp'] ?? 0) < time()) fail('Token sudah kedaluwarsa.');
    // Validasi issuer — tolak token yang bukan dari sistem ini
    if (($payload['iss'] ?? '') !== 'gudeg-sso') fail('Issuer token tidak valid.');
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
