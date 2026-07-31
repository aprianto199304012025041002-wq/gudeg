<?php
/**
 * sso.php — final
 *
 * Semua app aktif menggunakan RSA-signed JWT (use_signed_token = true).
 * ────────────────────────────────────────────────────────────────────
 * client_id bersifat publik (dikirim via query string). Memakai
 * client_id sebagai HMAC key berarti siapapun yang tahu client_id
 * bisa memalsukan token.
 *
 * HMAC mode tersedia sebagai fallback untuk app yang tidak pakai RSA.
 * di DB dan hanya bisa diakses via service_role key Supabase.
 *
 * Fix: debounce log SSO 60 detik — cegah log spam saat app klien
 * redirect berulang ke sso.php dalam waktu singkat.
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
    // RSA-signed JWT
    $token = JWT::generate($payload, 900);
} else {
    $header = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $body   = base64url_encode(json_encode(array_merge($payload, [
        'iat' => time(),
        'exp' => time() + 900,
        'iss' => 'gudeg-sso',
    ])));

    // HMAC key = client_secret dari DB (hanya untuk app dengan use_signed_token = false)
    $sig   = base64url_encode(hash_hmac('sha256', "$header.$body", $app['client_secret_plain'], true));
    $token = "$header.$body.$sig";
}

// ─── Log aktivitas SSO (debounce 60 detik) ───────────────────────────────────
// Cegah log spam: skip jika user sudah log akses ke app yang sama < 60 detik lalu
$host         = parse_url($app['redirect'], PHP_URL_HOST) ?? $app['redirect'];
$activity_str = 'Akses SSO ke ' . $host;

try {
    // Cek apakah ada log yang sama dalam 60 detik terakhir
    $recent = $sb->select(
        'user_logs',
        [
            'user_id'  => $user['id'],
            'activity' => $activity_str,
        ],
        'id,created_at',
        ['limit' => 1, 'order' => 'created_at.desc']
    );

    // Hanya log jika belum ada aktivitas SSO ke app ini dalam 60 detik
    $should_log = true;
    if (!empty($recent)) {
        $last_time = strtotime($recent[0]['created_at'] ?? 0);
        if ($last_time && (time() - $last_time) < 60) {
            $should_log = false;
        }
    }

    if ($should_log) {
        log_activity($user['id'], $user['name'], $user['email'], $activity_str);
    }
} catch (\Throwable $e) {
    // Jika cek debounce gagal, tetap log agar tidak ada aktivitas yang terlewat
    error_log('SSO debounce check gagal: ' . $e->getMessage());
    log_activity($user['id'], $user['name'], $user['email'], $activity_str);
}

// ─── Redirect ke aplikasi client dengan token ─────────────────────────────────
$separator    = strpos($app['redirect'], '?') !== false ? '&' : '?';
$redirect_url = $app['redirect'] . $separator . 'token=' . urlencode($token);

$state = $_GET['state'] ?? '';
if ($state) {
    $redirect_url .= '&state=' . urlencode($state);
}

header("Location: $redirect_url");
exit;
