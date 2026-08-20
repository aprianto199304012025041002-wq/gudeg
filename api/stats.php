<?php
/**
 * api/stats.php - Endpoint statistik GUDEG untuk Grafana Infinity datasource.
 * Read-only, pakai service_role key via SupabaseClient.
 * Tidak butuh login — data yang dikembalikan hanya agregat, bukan PII.
 * Dilindungi oleh STATS_KEY (shared secret) di header X-Stats-Key.
 */

require_once __DIR__ . '/../includes/env.php';

// -- CORS & headers -----------------------------------------------------------
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://aprianto199304012025041002yk.grafana.net');
header('Access-Control-Allow-Headers: X-Stats-Key, Content-Type');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// -- Auth: shared secret key --------------------------------------------------
$stats_key = defined('STATS_KEY') ? STATS_KEY : '';
$req_key   = $_SERVER['HTTP_X_STATS_KEY'] ?? '';

if ($stats_key === '' || $req_key !== $stats_key) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// -- Query Supabase -----------------------------------------------------------
try {
    $sb = getSupabase();

    // 1. Total user
    $total_users = $sb->rpc('stats_total_users')[0] ?? 0;

    // 2. User aktif 7 hari
    $active_7d = $sb->rpc('stats_active_users_7d')[0] ?? 0;

    // 3. Session aktif sekarang
    $active_sessions = count($sb->select('sessions', [], 'id'));

    // 4. User per role & status
    $users_raw = $sb->select('users', [], 'role,is_active');
    $roles = [];
    $active_count = 0;
    $inactive_count = 0;
    foreach ($users_raw as $u) {
        $r = $u['role'] ?? 'unknown';
        $roles[$r] = ($roles[$r] ?? 0) + 1;
        if ($u['is_active']) $active_count++;
        else $inactive_count++;
    }

    // 5. Registrasi per hari 30 hari terakhir
    $reg_per_day_raw = $sb->rpc('stats_registrations_per_day');
    $reg_per_day = array_map(fn($r) => [
        'time' => $r['day'],
        'value' => (int)$r['total']
    ], $reg_per_day_raw);

    // 6. Session per hari 30 hari terakhir
    $sessions_per_day_raw = $sb->rpc('stats_sessions_per_day');
    $sessions_per_day = array_map(fn($r) => [
        'time' => $r['day'],
        'value' => (int)$r['total']
    ], $sessions_per_day_raw);

    // 7. Login per hari 30 hari terakhir
    $logins_per_day_raw = $sb->rpc('stats_logins_per_day');
    $logins_per_day = array_map(fn($r) => [
        'time' => $r['day'],
        'value' => (int)$r['total']
    ], $logins_per_day_raw);

    // 8. Aktivitas terbaru 10 log
    $recent_logs = $sb->select('user_logs', [], 'user_name,activity,ip_address,created_at', [
        'limit' => 10,
        'order' => 'created_at.desc'
    ]);

    // 9. User terdaftar terbaru
    $recent_users = $sb->select('users', [], 'email,role,is_active,created_at', [
        'limit' => 5,
        'order' => 'created_at.desc'
    ]);

    // 10. Total log aktivitas
    $total_logs_raw = $sb->selectRaw('user_logs', [], 'id', null, null, null, true);
    $total_logs = $total_logs_raw['total'] ?? 0;

    // -- Response -------------------------------------------------------------
    echo json_encode([
        'generated_at' => date('c'),
        'summary' => [
            'total_users'       => (int)$total_users,
            'active_users_7d'   => (int)$active_7d,
            'active_sessions'   => (int)$active_sessions,
            'active_accounts'   => $active_count,
            'inactive_accounts' => $inactive_count,
            'total_logs'        => (int)$total_logs,
        ],
        'users_by_role'         => $roles,
        'registrations_per_day' => $reg_per_day,
        'sessions_per_day'      => $sessions_per_day,
        'logins_per_day'        => $logins_per_day,
        'recent_activity'       => $recent_logs,
        'recent_users'          => $recent_users,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
