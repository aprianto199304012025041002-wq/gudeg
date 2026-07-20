<?php
/**
 * TEST KONEKSI SUPABASE — file sementara, HAPUS setelah selesai testing.
 * Upload ke htdocs/test-supabase.php, buka di browser, lihat hasilnya.
 * JANGAN biarkan file ini nangkring lama di server produksi.
 */

require_once __DIR__ . '/includes/functions.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== TEST KONEKSI SUPABASE ===\n\n";

// 1. Cek constant ke-load dari .env
echo "[1] Cek konfigurasi .env\n";
if (!defined('SUPABASE_URL') || !SUPABASE_URL) {
    die("GAGAL: SUPABASE_URL tidak ke-load dari .env\n");
}
if (!defined('SUPABASE_SERVICE_KEY') || !SUPABASE_SERVICE_KEY) {
    die("GAGAL: SUPABASE_SERVICE_KEY tidak ke-load dari .env\n");
}
echo "OK — SUPABASE_URL = " . SUPABASE_URL . "\n";
echo "OK — SUPABASE_SERVICE_KEY ke-load (panjang: " . strlen(SUPABASE_SERVICE_KEY) . " karakter)\n\n";

// 2. Test SELECT ke tabel users (harus dapat 2 baris: Admin & satu untuk semua)
echo "[2] Test SELECT dari tabel users\n";
try {
    $users = getSupabase()->select('users', [], 'id,name,email,role');
    echo "OK — dapat " . count($users) . " baris:\n";
    foreach ($users as $u) {
        echo "   - id={$u['id']} name={$u['name']} role={$u['role']}\n";
    }
} catch (\Throwable $e) {
    die("GAGAL SELECT: " . $e->getMessage() . "\n");
}
echo "\n";

// 3. Test INSERT + DELETE ke tabel user_logs (data dummy, langsung dihapus lagi)
echo "[3] Test INSERT + DELETE ke tabel user_logs\n";
try {
    $sb = getSupabase();
    $marker = 'TEST_KONEKSI_' . time();

    $inserted = $sb->insert('user_logs', [
        'user_id'    => 1,
        'user_name'  => 'Test Koneksi',
        'user_email' => 'test@koneksi.local',
        'activity'   => $marker,
        'ip_address' => '127.0.0.1',
        'created_at' => date('Y-m-d H:i:s'),
    ]);
    echo "OK — insert berhasil, id baru = " . ($inserted['id'] ?? '?') . "\n";

    $sb->delete('user_logs', ['activity' => $marker]);
    echo "OK — cleanup (delete data dummy) berhasil\n";
} catch (\Throwable $e) {
    die("GAGAL INSERT/DELETE: " . $e->getMessage() . "\n");
}
echo "\n";

echo "=== SEMUA TEST LOLOS — koneksi Supabase jalan normal ===\n";
echo "Jangan lupa hapus file test-supabase.php ini dari server sekarang.\n";
