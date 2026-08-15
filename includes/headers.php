<?php
/**
 * headers.php — Security headers dan CSP.
 * Di-include sekali via functions.php. Headers dikirim saat file ini di-load.
 */

// ── CSP Nonce ─────────────────────────────────────────────────────────────────
// Nonce unik per request — dipakai di script/style yang mengandung PHP data.
// Lebih aman dari 'unsafe-inline' karena penyerang tidak bisa tebak nonce.
if (!defined('_CSP_NONCE')) {
    define('_CSP_NONCE', bin2hex(random_bytes(16))); // bin2hex: pure hex, aman untuk interpolasi string
}

/**
 * Kembalikan nonce untuk dipakai di atribut nonce="{}" pada <script> dan <style>.
 * Contoh: <script nonce="<?= csp_nonce() ?>">
 */
function csp_nonce(): string {
    return _CSP_NONCE;
}

// ── Security Headers ─────────────────────────────────────────────────────────
if (!headers_sent()) {
    $nonce = _CSP_NONCE;
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
    // #79 #80: unsafe-inline dihapus — diganti nonce per request.
    // Script/style yang mengandung PHP data wajib pakai atribut nonce="<?= csp_nonce() ?>".
    // Script/style yang sudah dipindah ke file eksternal tidak perlu nonce.
    $csp = "default-src 'self'; "
           . "script-src 'self' 'nonce-" . $nonce . "'; "
           . "style-src 'self' 'nonce-" . $nonce . "'; "
           . "font-src 'self'; "
           . "img-src 'self' data:; "
           . "connect-src 'self' https://*.supabase.co; "
           . "frame-ancestors 'self'; "
           . "form-action 'self'; "
           . "base-uri 'self'";
    header('Content-Security-Policy: ' . $csp);
    header("Permissions-Policy: geolocation=(), camera=(), microphone=(), payment=(), usb=(), interest-cohort=()");
    header_remove("X-Powered-By");
}
