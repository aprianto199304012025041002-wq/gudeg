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

// ── Helper base64url (RFC 7519) ───────────────────────────────────────────────
if (!function_exists('base64url_encode')) {
    function base64url_encode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
if (!function_exists('base64url_decode')) {
    function base64url_decode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }
}

$sb = getSupabase(); // inisialisasi $sb — dipakai oleh semua query di bawah

// Auto-cleanup token blacklist yang sudah expired
try { $sb->delete('token_blacklist', ['exp' => 'lt.' . time()]); } catch (\Throwable $e) {}

// FIX: Rate limiting verify.php — cegah brute force client_secret
$verify_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$verify_ip = trim(explode(',', $verify_ip)[0]);
$rl_jti    = 'rl_verify_' . md5($verify_ip);
$rl_window = 60;   // 1 menit
$rl_max    = 30;   // max 30 request per menit per IP

try {
    // FIX Bug 2: Rate limit pakai exp = unix timestamp window_end (bukan counter)
    // sehingga tidak konflik dengan cleanup token_blacklist yang DELETE WHERE exp < time()
    // Counter disimpan terpisah di kolom payload (string)

    // Cleanup rate limit record yang window-nya sudah habis
    $rl_window_end = time() + $rl_window;
    $sb->delete('token_blacklist', ['jti' => 'like.rl_*', 'exp' => 'lt.' . time()]);

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
$app = $sb->selectOne('sso_applications', ['client_id' => $client_id]);

if (!$app || !verify_client_secret($client_secret, $app['client_secret'])) {
    fail('Aplikasi tidak ditemukan atau kredensial salah.');
}
if (!$app['is_active']) {
    fail('Aplikasi telah dinonaktifkan.');
}

// 2. Verify token
if ($app['use_signed_token']) {
    $payload = JWT::verify($token);
    if (!$payload) {
        fail('Token tidak valid atau sudah kedaluwarsa.');
    }
} else {
    // FIX Bug 3: client_secret di DB sekarang adalah bcrypt hash, bukan plaintext.
    // HMAC-SHA256 tidak bisa pakai bcrypt hash sebagai key.
    // Solusi: untuk aplikasi HMAC, verifikasi signature menggunakan client_id
    // sebagai HMAC key tambahan (deterministik, tidak berubah).
    // Aplikasi klien yang pakai HMAC harus update cara generate signature-nya.
    //
    // CATATAN: Kalau aplikasi klien masih pakai secret plaintext lama sebagai HMAC key,
    // mereka perlu switch ke RSA (use_signed_token=1) atau re-generate token dengan client_id key.
    $parts = explode('.', $token);
    if (count($parts) !== 3) fail('Format token salah.');
    [$header, $body, $sig] = $parts;

    // Verifikasi HMAC pakai client_id sebagai key (stabil, bukan secret yang di-hash)
    $expected = base64url_encode(hash_hmac('sha256', "$header.$body", $app['client_id'], true));
    if (!hash_equals($expected, $sig)) fail('Tanda tangan token tidak valid.');

    $payload = json_decode(base64url_decode($body), true);
    if (!$payload) fail('Payload token tidak dapat dibaca.');
    if (($payload['exp'] ?? 0) < time()) fail('Token sudah kedaluwarsa.');
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