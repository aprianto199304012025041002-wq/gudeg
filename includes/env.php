<?php
/**
 * env.php — Load config/.env dan inisialisasi SupabaseClient.
 * Dipanggil pertama kali oleh functions.php.
 */
date_default_timezone_set('Asia/Jakarta');

// ── Load config/.env ──────────────────────────────────────────────────────────
// db.php dulu yang handle ini, tapi MySQL sudah tidak dipakai.
// Sekarang parsing .env langsung di sini supaya tidak ada dead dependency.
(function () {
    $env_file = __DIR__ . '/../config/.env';
    if (!file_exists($env_file)) return;
    foreach (file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v);
            if ($k !== '' && !defined($k)) define($k, $v);
        }
    }
})();

require_once __DIR__ . '/SupabaseClient.php';
