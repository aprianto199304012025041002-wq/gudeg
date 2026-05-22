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
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

function fail($msg) {
    echo json_encode(['valid' => false, 'error' => $msg]);
    exit;
}

// ── Helper base64url (RFC 7519) ───────────────────────────────────────────────
function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
function base64url_decode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
}

$token         = $_REQUEST['token'] ?? '';
$client_id     = $_REQUEST['client_id'] ?? '';
$client_secret = $_REQUEST['client_secret'] ?? '';

if (!$token || !$client_id || !$client_secret) {
    fail('token, client_id, dan client_secret wajib diisi.');
}

$db = getDB();

// 1. Cek aplikasi aktif
$stmt = $db->prepare("SELECT * FROM sso_applications WHERE client_id = ? AND client_secret = ? LIMIT 1");
$stmt->execute([$client_id, $client_secret]);
$app = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$app) {
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

    // FIX: gunakan base64url_encode (bukan base64_encode) agar cocok dengan token yang digenerate
    $expected = base64url_encode(hash_hmac('sha256', "$header.$body", $app['client_secret'], true));
    if (!hash_equals($expected, $sig)) fail('Tanda tangan token tidak valid.');

    // FIX: gunakan base64url_decode (bukan base64_decode) untuk decode payload JWT
    $payload = json_decode(base64url_decode($body), true);
    if (!$payload) fail('Payload token tidak dapat dibaca.');
    if (($payload['exp'] ?? 0) < time()) fail('Token sudah kedaluwarsa.');
}

$user_id = $payload['sub'] ?? null;
if (!$user_id) {
    fail('Token tidak mengandung identitas user.');
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
$stmt = $db->prepare("SELECT id FROM user_applications WHERE user_id = ? AND application_id = ? LIMIT 1");
$stmt->execute([$user_id, $app['id']]);
if (!$stmt->fetch()) {
    fail('Akses user ke aplikasi ini telah dicabut.');
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