<?php
/**
 * activity.php — Activity logging, admin action logging, IP helper, cleanup.
 *
 * Fungsi:
 *  - get_ip()            : ambil IP asli dari header (support proxy)
 *  - log_activity()      : log aktivitas user ke Supabase (dengan fallback LocalCache)
 *  - log_admin_action()  : log aksi admin ke Supabase (dengan fallback LocalCache)
 *  - cleanup_old_logs()  : hapus log lebih dari 90 hari (dipanggil dari halaman admin)
 */

function get_ip(): string
{
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return explode(',', $ip)[0];
}

function log_activity($user_id, $name, $email, $activity): void
{
    $ip  = get_ip();
    $now = date('Y-m-d H:i:s');

    $payload = [
        'user_id'    => $user_id,
        'user_name'  => $name,
        'user_email' => $email,
        'activity'   => $activity,
        'ip_address' => $ip,
        'created_at' => $now,
    ];

    // Flush log yang tertunda dari request sebelumnya (tanpa cron)
    if (class_exists('LocalCache')) {
        try {
            LocalCache::flushLogs(function (string $tbl, array $p) {
                getSupabase()->insert($tbl, $p, 3); // non-kritis, timeout pendek
            });
        } catch (\Throwable $e) {
            // flush gagal — tidak apa-apa, akan dicoba lagi nanti
        }
    }

    try {
        getSupabase()->insert('user_logs', $payload, 3); // non-kritis, timeout pendek
    } catch (\Throwable $e) {
        gudeg_log('WARNING', 'log_activity', 'Supabase gagal, antri ke SQLite: ' . $e->getMessage());
        // Supabase gagal — antri ke LocalCache, kirim ulang di request berikutnya
        if (class_exists('LocalCache')) {
            LocalCache::logQueue('user_logs', $payload);
        }
    }
}

function log_admin_action($admin_id, $admin_name, $action, $target = '-'): void
{
    $now = date('Y-m-d H:i:s');
    $ip  = get_ip();

    $payload = [
        'admin_id'    => $admin_id,
        'admin_name'  => $admin_name,
        'action'      => $action,
        'target_user' => $target,
        'ip_address'  => $ip,
        'created_at'  => $now,
    ];

    try {
        getSupabase()->insert('admin_logs', $payload, 3); // non-kritis, timeout pendek
    } catch (\Throwable $e) {
        gudeg_log('WARNING', 'log_admin_action', 'Supabase gagal, antri ke SQLite: ' . $e->getMessage());
        // Supabase gagal — antri ke LocalCache, kirim ulang di request berikutnya
        if (class_exists('LocalCache')) {
            LocalCache::logQueue('admin_logs', $payload);
        }
    }
}

/**
 * Hapus log lebih dari 90 hari secara otomatis.
 * Dipanggil dari halaman log admin — tidak perlu cron job.
 * Jalankan maksimal sekali per 24 jam via LocalCache timestamp.
 */
function cleanup_old_logs(): void
{
    if (class_exists('LocalCache')) {
        try {
            $last_ts = (int) LocalCache::metaGet('cleanup_last_ts');
            if ((time() - $last_ts) < 86400) return; // belum 24 jam
            LocalCache::metaSet('cleanup_last_ts', (string) time());
        } catch (\Throwable $e) {
            gudeg_log('WARNING', 'cleanup_old_logs', 'LocalCache error, fallback ke rand(): ' . $e->getMessage());
            if (rand(1, 100) > 5) return;
        }
    } else {
        if (rand(1, 100) > 5) return;
    }

    $cutoff = date('Y-m-d H:i:s', strtotime('-90 days'));
    try {
        getSupabase()->delete('user_logs',  ['created_at' => 'lt.' . $cutoff]);
        getSupabase()->delete('admin_logs', ['created_at' => 'lt.' . $cutoff]);
        gudeg_log('INFO', 'cleanup_old_logs', "Log lama sebelum $cutoff berhasil dibersihkan");
    } catch (\Throwable $e) {
        gudeg_log('WARNING', 'cleanup_old_logs', 'Gagal hapus log lama dari Supabase: ' . $e->getMessage());
    }
}
