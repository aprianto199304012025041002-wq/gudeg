<?php
/**
 * csrf.php - CSRF token generation, embedding, dan verification.
 *
 * Fungsi:
 *  - csrf_token()  : buat atau ambil token yang sudah ada di session
 *  - csrf_field()  : output hidden input HTML untuk form
 *  - csrf_verify() : validasi token dari POST, rotate setelah sukses
 */

/**
 * Buat (atau ambil yang sudah ada) CSRF token untuk sesi ini.
 */
function csrf_token(): string
{
    session_start_safe();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output hidden input CSRF - taruh di dalam setiap <form>.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

/**
 * Validasi CSRF token dari POST request. Mati jika tidak valid.
 * Token di-rotate setelah validasi sukses - satu token hanya berlaku satu kali.
 * Ini mencegah token replay kalau token bocor (misal dari log atau shoulder surfing).
 */
function csrf_verify(): void
{
    session_start_safe();
    $token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        gudeg_log('WARNING', 'csrf_verify', 'CSRF token tidak valid - IP: ' . get_ip());
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        die(json_encode(['error' => 'Invalid CSRF token.']));
    }
    // Rotate token setelah validasi sukses - token lama tidak bisa dipakai lagi
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
