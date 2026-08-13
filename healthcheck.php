<?php
/**
 * healthcheck.php — Cek status koneksi Supabase & konfigurasi setelah deploy.
 *
 * Akses: GET /healthcheck.php?key=HEALTH_KEY
 * Dilindungi HEALTH_KEY dari environment agar tidak bisa diakses publik.
 *
 * Dipanggil otomatis oleh GitHub Actions setelah FTP deploy selesai.
 * Juga bisa dipanggil manual untuk diagnosa.
 */

require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

// ── Auth: cek HEALTH_KEY ─────────────────────────────────────────────────────
$key_from_request = $_GET['key'] ?? $_SERVER['HTTP_X_HEALTH_KEY'] ?? '';
$health_key       = defined('HEALTH_KEY') ? HEALTH_KEY : '';

if (!$health_key || !hash_equals($health_key, $key_from_request)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

$checks = [];
$all_ok = true;

// ── 1. Cek Supabase koneksi ──────────────────────────────────────────────────
try {
    $sb   = getSupabase();
    $rows = $sb->select('users', [], 'id', ['limit' => 1]);
    $checks['supabase'] = ['ok' => true, 'msg' => 'Connected'];
} catch (\Throwable $e) {
    $checks['supabase'] = ['ok' => false, 'msg' => $e->getMessage()];
    $all_ok = false;
}

// ── 2. Cek config/.env terbaca ───────────────────────────────────────────────
$checks['env'] = [
    'ok'  => defined('SUPABASE_URL') && defined('SUPABASE_SERVICE_KEY'),
    'msg' => defined('SUPABASE_URL') ? 'SUPABASE_URL defined' : 'SUPABASE_URL missing',
];
if (!$checks['env']['ok']) $all_ok = false;

// ── 3. Cek RSA keys ada ──────────────────────────────────────────────────────
$private_ok = file_exists(__DIR__ . '/keys/private.pem');
$public_ok  = file_exists(__DIR__ . '/keys/public.pem');
$checks['rsa_keys'] = [
    'ok'  => $private_ok && $public_ok,
    'msg' => $private_ok && $public_ok
        ? 'private.pem & public.pem found'
        : 'Missing: ' . (!$private_ok ? 'private.pem ' : '') . (!$public_ok ? 'public.pem' : ''),
];
if (!$checks['rsa_keys']['ok']) $all_ok = false;

// ── 4. Cek /tmp writable (untuk LocalCache & RateLimiter) ───────────────────
$tmp_ok = is_writable('/tmp');
$checks['tmp_writable'] = [
    'ok'  => $tmp_ok,
    'msg' => $tmp_ok ? '/tmp is writable' : '/tmp is NOT writable',
];
if (!$tmp_ok) $all_ok = false;

// ── 4b. Cek CACHE_PATH writable (kalau di-set di .env) ──────────────────────
$cache_path = defined('CACHE_PATH') ? CACHE_PATH : null;
if ($cache_path) {
    $cache_dir    = dirname($cache_path);
    $cache_dir_ok = is_dir($cache_dir) && is_writable($cache_dir);
    $checks['cache_path'] = [
        'ok'  => $cache_dir_ok,
        'msg' => $cache_dir_ok
            ? "CACHE_PATH dir writable: $cache_dir"
            : "CACHE_PATH dir NOT writable atau tidak ada: $cache_dir",
    ];
    if (!$cache_dir_ok) $all_ok = false;
} else {
    $checks['cache_path'] = ['ok' => true, 'msg' => 'CACHE_PATH tidak di-set, pakai default /tmp'];
}

// ── 5. Cek LocalCache SQLite ─────────────────────────────────────────────────
try {
    require_once __DIR__ . '/includes/LocalCache.php';
    // Coba operasi sederhana untuk pastikan SQLite berfungsi
    LocalCache::cleanup();
    $checks['local_cache'] = ['ok' => true, 'msg' => 'SQLite LocalCache OK'];
} catch (\Throwable $e) {
    $checks['local_cache'] = ['ok' => false, 'msg' => $e->getMessage()];
    // Ini warning, bukan critical — app masih bisa jalan tanpa cache
}

// ── 6. Cek pending logs — kalau ada log tertunda > 1 jam, Supabase mungkin down lama ──
try {
    require_once __DIR__ . '/includes/LocalCache.php';
    $pdo = (new ReflectionClass('LocalCache'))->getMethod('pdo');
    $pdo->setAccessible(true);
    $db  = $pdo->invoke(null);
    $stmt = $db->query("SELECT COUNT(*) as cnt, MIN(queued_at) as oldest FROM pending_logs");
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    $cnt  = (int)($row['cnt'] ?? 0);
    $oldest = (int)($row['oldest'] ?? 0);
    $age  = $oldest > 0 ? (time() - $oldest) : 0;
    $checks['pending_logs'] = [
        'ok'  => $cnt === 0 || $age < 3600,
        'msg' => $cnt === 0
            ? 'Tidak ada log tertunda'
            : "$cnt log tertunda, terlama " . round($age / 60) . " menit lalu",
    ];
    if (!$checks['pending_logs']['ok']) $all_ok = false;
} catch (\Throwable $e) {
    $checks['pending_logs'] = ['ok' => true, 'msg' => 'Tabel belum ada (normal jika belum ada log gagal)'];
}

// ── 7. Report versi PHP ──────────────────────────────────────────────────────
$checks['php_version'] = [
    'ok'  => true,
    'msg' => 'PHP ' . PHP_VERSION,
];

// ── 8. Cek RSA key bisa di-load OpenSSL (bukan hanya file_exists) ───────────
$private_path = __DIR__ . '/keys/private.pem';
$public_path  = __DIR__ . '/keys/public.pem';
$priv_valid   = false;
$pub_valid    = false;
if (file_exists($private_path)) {
    $priv_valid = (bool) @openssl_pkey_get_private(file_get_contents($private_path));
}
if (file_exists($public_path)) {
    $pub_valid = (bool) @openssl_pkey_get_public(file_get_contents($public_path));
}
$checks['rsa_openssl'] = [
    'ok'  => $priv_valid && $pub_valid,
    'msg' => $priv_valid && $pub_valid
        ? 'private.pem & public.pem valid di OpenSSL'
        : 'OpenSSL gagal load: ' . (!$priv_valid ? 'private.pem ' : '') . (!$pub_valid ? 'public.pem' : ''),
];
if (!$checks['rsa_openssl']['ok']) $all_ok = false;

// ── Response ─────────────────────────────────────────────────────────────────
http_response_code($all_ok ? 200 : 503);
echo json_encode([
    'ok'      => $all_ok,
    'time'    => date('Y-m-d H:i:s'),
    'checks'  => $checks,
], JSON_PRETTY_PRINT);
