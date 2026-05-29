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
        log_activity(
            $user['id'],
            $user['name'],
            $user['email'],
            'Akses DITOLAK (Tidak ada izin) ke ' . parse_url($app['redirect'], PHP_URL_HOST)
        );
        http_response_code(403);
        die('Anda tidak memiliki izin untuk mengakses aplikasi ini.');
    }
}

// ─── Helper: base64url encoding (standar JWT / RFC 7519) ─────────────────────
function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

// ─── Buat payload ─────────────────────────────────────────────────────────────
$payload = [
    'sub'   => (string) $user['id'],
    'name'  => $user['name'],
    'email' => $user['email'],
    'role'  => $user['role'],
    'app'   => $app['name'],
    'jti'   => bin2hex(random_bytes(8)),
];

// ─── Generate token ───────────────────────────────────────────────────────────
if ($app['use_signed_token']) {
    // RSA-signed JWT — gunakan class JWT yang sudah ada
    $token = JWT::generate($payload, 900); // FIX: 15 menit (dari 1 jam)
} else {
    // HMAC-SHA256 dengan base64url (RFC 7519 compliant)
    $header = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $body   = base64url_encode(json_encode(array_merge($payload, [
        'iat' => time(),
        'exp' => time() + 900, // FIX: 15 menit (dari 1 jam)
        'iss' => 'gudeg-sso',
    ])));
    // FIX Bug 3: pakai client_id sebagai HMAC key (client_secret di DB sudah bcrypt hash)
    $sig    = base64url_encode(hash_hmac('sha256', "$header.$body", $app['client_id'], true));
    $token  = "$header.$body.$sig";
}

// ─── Log aktivitas SSO ────────────────────────────────────────────────────────
$host = parse_url($app['redirect'], PHP_URL_HOST) ?? $app['redirect'];
log_activity($user['id'], $user['name'], $user['email'], 'Akses SSO ke ' . $host);

// ─── Redirect ke aplikasi client dengan token ─────────────────────────────────
// FIX: Selalu gunakan redirect dari DB, bukan query string (cegah open redirect)
$separator    = strpos($app['redirect'], '?') !== false ? '&' : '?';
$redirect_url = $app['redirect'] . $separator . 'token=' . urlencode($token);

// Kembalikan state ke klien jika ada (standar OAuth2, klien wajib verifikasi)
$state = $_GET['state'] ?? '';
if ($state) {
    $redirect_url .= '&state=' . urlencode($state);
}

header("Location: $redirect_url");
exit;