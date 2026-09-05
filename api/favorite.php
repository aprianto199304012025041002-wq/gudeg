<?php
/**
 * api/favorite.php — Toggle favorit aplikasi untuk user yang sedang login.
 *
 * POST  { application_id: "...", csrf_token: "..." }
 * Response JSON: { ok: true, favorited: true|false }
 */
require_once __DIR__ . '/../includes/functions.php';
require_login();

header('Content-Type: application/json');
header('Cache-Control: no-store');

// Hanya terima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// Parse body — bisa form-data atau JSON
$input = [];
$raw   = file_get_contents('php://input');
if ($raw && ($decoded = json_decode($raw, true)) && is_array($decoded)) {
    $input = $decoded;
} else {
    $input = $_POST;
}

// CSRF
$csrf_token = $input['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf_token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'CSRF token tidak valid']);
    exit;
}

$application_id = trim($input['application_id'] ?? '');
if ($application_id === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'application_id wajib diisi']);
    exit;
}

$user    = get_current_user_data();
$user_id = (string)$user['id'];

try {
    $sb = getSupabase();

    // Cek apakah sudah favorit
    $existing = $sb->selectOne('user_favorites', [
        'user_id'        => $user_id,
        'application_id' => $application_id,
    ]);

    if ($existing) {
        // Sudah favorit → hapus (toggle off)
        $sb->delete('user_favorites', [
            'user_id'        => $user_id,
            'application_id' => $application_id,
        ]);
        echo json_encode(['ok' => true, 'favorited' => false]);
    } else {
        // Belum favorit → tambah (toggle on)
        $sb->insert('user_favorites', [
            'user_id'        => $user_id,
            'application_id' => $application_id,
        ]);
        echo json_encode(['ok' => true, 'favorited' => true]);
    }

    gudeg_log('INFO', 'api/favorite.php', ($existing ? 'Unfavorite' : 'Favorite') . " app $application_id oleh user $user_id");

} catch (\Throwable $e) {
    gudeg_log('ERROR', 'api/favorite.php', $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Gagal memproses permintaan']);
}
