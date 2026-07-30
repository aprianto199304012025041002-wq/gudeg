<?php
/**
 * sso.php — final
 *
 * Improvement #3: Ganti HMAC key dari client_id → client_secret_plain
 * ────────────────────────────────────────────────────────────────────
 * client_id bersifat publik (dikirim via query string). Memakai
 * client_id sebagai HMAC key berarti siapapun yang tahu client_id
 * bisa memalsukan token.
 *
 * Solusi: pakai client_secret_plain (hex 64-char random) yang disimpan
 * di DB dan hanya bisa diakses via service_role key Supabase.
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/jwt.php';
require_login();

$user = get_current_user_data();
$sb   = getSupabase();

$client_id = $_GET['client_id'] ?? '';

if (!$client_id) {
    die('client_id diperlukan.');
}

// Fetch the SSO application
$app = $sb->selectOne('sso_applications', ['client_id' => $client_id, 'is_active' => true]);

if (!$app) {
    die('Aplikasi tidak ditemukan atau tidak aktif.');
}

// Pastikan app sudah punya client_secret_plain (hasil migration).
// Kalau belum ada, tolak — jangan fallback ke client_id yang tidak aman.
if (empty($app['client_secret_plain'])) {
    http_response_code(503);
    error_log("SSO: app '{$app['name']}' belum punya client_secret_plain. Jalankan migration SQL.");
    die('Konfigurasi aplikasi belum lengkap. Hubungi administrator.');
}

// Check user has access to this app (admin always has access)
if ($user['role'] !== 'admin') {
    $access = $sb->selectOne('user_applications', ['user_id' => $user['id'], 'application_id' => $app['id']], 'user_id');
    if (!$access) {
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

// base64url_encode tersedia via includes/functions.php

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
    // RSA-signed JWT — tidak butuh client_secret_plain
    $token = JWT::generate($payload, 900);
} else {
    $header = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $body   = base64url_encode(json_encode(array_merge($payload, [
        'iat' => time(),
        'exp' => time() + 900,
        'iss' => 'gudeg-sso',
    ])));

    // HMAC key = client_secret_plain (plaintext hex, hanya ada di DB server-side)
    // App client menyimpan nilai yang sama di environment mereka untuk verifikasi.
    $sig   = base64url_encode(hash_hmac('sha256', "$header.$body", $app['client_secret_plain'], true));
    $token = "$header.$body.$sig";
}

// ─── Log aktivitas SSO ────────────────────────────────────────────────────────
$host = parse_url($app['redirect'], PHP_URL_HOST) ?? $app['redirect'];
log_activity($user['id'], $user['name'], $user['email'], 'Akses SSO ke ' . $host);

// ─── Redirect ke aplikasi client dengan token ─────────────────────────────────
$separator    = strpos($app['redirect'], '?') !== false ? '&' : '?';
$redirect_url = $app['redirect'] . $separator . 'token=' . urlencode($token);

$state = $_GET['state'] ?? '';
if ($state) {
    $redirect_url .= '&state=' . urlencode($state);
}

header("Location: $redirect_url");
exit;