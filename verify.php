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

$token        = $_REQUEST['token'] ?? '';
$client_id    = $_REQUEST['client_id'] ?? '';
$client_secret = $_REQUEST['client_secret'] ?? '';

if (!$token || !$client_id || !$client_secret) {
    fail('token, client_id, dan client_secret wajib diisi.');
}

// Verify application
$db   = getDB();
$stmt = $db->prepare("SELECT * FROM sso_applications WHERE client_id = ? AND client_secret = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$client_id, $client_secret]);
$app  = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$app) {
    fail('Aplikasi tidak ditemukan atau kredensial salah.');
}

// Verify token
if ($app['use_signed_token']) {
    $payload = JWT::verify($token);
    if (!$payload) {
        fail('Token tidak valid atau sudah kedaluwarsa.');
    }
} else {
    // HMAC verify
    $parts = explode('.', $token);
    if (count($parts) !== 3) fail('Format token salah.');
    [$header, $body, $sig] = $parts;
    $expected = base64_encode(hash_hmac('sha256', "$header.$body", $app['client_secret'], true));
    if (!hash_equals($expected, $sig)) fail('Tanda tangan token tidak valid.');
    $payload = json_decode(base64_decode($body), true);
    if (!$payload) fail('Payload token tidak dapat dibaca.');
    if (($payload['exp'] ?? 0) < time()) fail('Token sudah kedaluwarsa.');
}

echo json_encode([
    'valid'   => true,
    'payload' => [
        'user_id' => $payload['sub'] ?? null,
        'name'    => $payload['name'] ?? null,
        'email'   => $payload['email'] ?? null,
        'role'    => $payload['role'] ?? null,
        'exp'     => $payload['exp'] ?? null,
    ]
]);
