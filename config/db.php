<?php
// CATATAN: file ini sudah tidak di-require dari functions.php.
// Parsing .env sekarang dilakukan langsung di includes/functions.php.
// File ini dibiarkan ada untuk referensi, tapi tidak dipanggil di mana pun.
date_default_timezone_set('Asia/Jakarta');

$env_file = __DIR__ . '/.env';
if (file_exists($env_file)) {
    foreach (file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            [$k, $v] = explode('=', $line, 2);
            if (!defined(trim($k))) define(trim($k), trim($v));
        }
    }
}