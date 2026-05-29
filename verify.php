<?php
/**
 * verify.php — endpoint untuk aplikasi klien memverifikasi JWT
 *
 * GET/POST: ?token=<jwt>&client_id=<id>&client_secret=<secret>
 * Response JSON: { valid: true/false, payload: {...} }
 */
require_once __DIR__ . '/includes/jwt.php';
require_once __DIR__ . '/config/db.php';
header('Content-Type: application/json');

// FIX: Jangan pakai Access-Control-Allow-Origin: * untuk endpoint sensitif.
// Cek Origin request dan izinkan hanya jika cocok dengan domain aplikasi yang terdaftar.
// Kalau tidak ada Origin header (curl/server-to-server), tetap lanjut tanpa CORS header.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin) {
    $db_cors = getDB();
    $origins = $db_cors->query("SELECT redirect FROM sso_applications WHERE is_active = 1")->fetchAll(PDO::FETCH_COLUMN);
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

$db = getDB(); // inisialisasi $db — dipakai oleh semua query di bawah

// Auto-cleanup token blacklist yang sudah expired
try { $db->prepare("DELETE FROM token_blacklist WHERE exp < ?")->execute([time()]); } catch (\Throwable $e) {}

// FIX: Rate limiting verify.php — cegah brute force client_secret
$verify_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$verify_ip = trim(explode(',', $verify_ip)[0]);
$rl_jti    = 'rl_verify_' . md5($verify_ip);
$rl_window = 60;   // 1 menit
$rl_max    = 30;   // max 30 request per menit per IP

try {
    // Cleanup rate limit record yang sudah expired (window habis)
    $db->prepare("DELETE FROM token_blacklist WHERE jti LIKE 'rl_%' AND exp < ?")
       ->execute([time()]);

    // Cek apakah sudah ada record untuk IP ini dalam window aktif
    $rl_stmt = $db->prepare("SELECT exp FROM token_blacklist WHERE jti = ? LIMIT 1");
    $rl_stmt->execute([$rl_jti]);
    $rl_row = $rl_stmt->fetch(PDO::FETCH_ASSOC);

    if ($rl_row) {
        // exp menyimpan: unix timestamp kapan window habis (bagian bawah 20 bit)
        // dan count request (bagian atas)
        // Pendekatan lebih simpel: exp = window_end, count disimpan di used_at trick
        // SIMPAN: jti = key, exp = count request, used_at = window end time sebagai string
        $req_count = (int)$rl_row['exp'];
        if ($req_count >= $rl_max) {
            http_response_code(429);
            fail('Terlalu banyak request. Coba lagi dalam 1 menit.');
        }
        $db->prepare("UPDATE token_blacklist SET exp = exp + 1 WHERE jti = ?")
           ->execute([$rl_jti]);
    } else {
        // Request pertama dari IP ini — buat window baru
        // exp = jumlah request (mulai dari 1), used_at = kapan window ini mulai
        $db->prepare("INSERT INTO token_blacklist (jti, exp, used_at) VALUES (?, 1, NOW())")
           ->execute([$rl_jti]);
    }
} catch (\Throwable $e) {
    // Kalau rate limit gagal karena DB error, tetap lanjut
    // (jangan block legitimate request karena masalah infrastruktur)
    if (!isset($db)) $db = getDB();
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
$stmt = $db->prepare("SELECT * FROM sso_applications WHERE client_id = ? LIMIT 1");
$stmt->execute([$client_id]);
$app = $stmt->fetch(PDO::FETCH_ASSOC);

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
    $parts = explode('.', $token);
    if (count($parts) !== 3) fail('Format token salah.');
    [$header, $body, $sig] = $parts;
    $expected = base64url_encode(hash_hmac('sha256', "$header.$body", $app['client_secret'], true));
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
$stmt = $db->prepare("SELECT id, name, email, role, is_active FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    fail('User tidak ditemukan.');
}
if (!$user['is_active']) {
    fail('Akun user telah dinonaktifkan.');
}

// 4. Cek user masih punya akses ke aplikasi ini
if ($user['role'] !== 'admin') {
    $stmt = $db->prepare("SELECT 1 FROM user_applications WHERE user_id = ? AND application_id = ? LIMIT 1");
    $stmt->execute([$user_id, $app['id']]);
    if (!$stmt->fetch()) {
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