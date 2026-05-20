<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/jwt.php';
require_login();

$user = get_current_user_data();
$db   = getDB();

$client_id = $_GET['client_id'] ?? '';

if (!$client_id) {
    die('client_id diperlukan.');
}

// Fetch the SSO application
$stmt = $db->prepare("SELECT * FROM sso_applications WHERE client_id = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$client_id]);
$app = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$app) {
    die('Aplikasi tidak ditemukan atau tidak aktif.');
}

// Check user has access to this app (admin always has access)
if ($user['role'] !== 'admin') {
    $access = $db->prepare("SELECT 1 FROM user_applications WHERE user_id = ? AND application_id = ? LIMIT 1");
    $access->execute([$user['id'], $app['id']]);
    if (!$access->fetch()) {
        log_activity($user['id'], $user['name'], $user['email'],
            'Akses DITOLAK (Tidak ada izin) ke ' . parse_url($app['redirect'], PHP_URL_HOST));
        http_response_code(403);
        die('Anda tidak memiliki izin untuk mengakses aplikasi ini.');
    }
}

// Generate token
$payload = [
    'sub'   => (string) $user['id'],
    'name'  => $user['name'],
    'email' => $user['email'],
    'role'  => $user['role'],
    'app'   => $app['name'],
    'jti'   => bin2hex(random_bytes(8)),
];

if ($app['use_signed_token']) {
    // RSA-signed JWT
    $token = JWT::generate($payload, 3600);
} else {
    // Simple HMAC-signed token using client_secret as key
    $header  = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $body    = base64_encode(json_encode(array_merge($payload, ['iat' => time(), 'exp' => time() + 3600, 'iss' => 'gudeg-sso'])));
    $sig     = base64_encode(hash_hmac('sha256', "$header.$body", $app['client_secret'], true));
    $token   = "$header.$body.$sig";
}

// Log the SSO access
$host = parse_url($app['redirect'], PHP_URL_HOST) ?? $app['redirect'];
log_activity($user['id'], $user['name'], $user['email'], 'Akses SSO ke ' . $host);

// Redirect to the app with token
$redirect_url = $app['redirect'] . (strpos($app['redirect'], '?') !== false ? '&' : '?') . 'token=' . urlencode($token);
header("Location: $redirect_url");
exit;
