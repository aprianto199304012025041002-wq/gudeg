<?php
/**
 * headers.php - Security headers dan CSP.
 * Di-include sekali via functions.php. Headers dikirim saat file ini di-load.
 */

// CSP Nonce - nonce unik per request, aman pengganti unsafe-inline (#79 #80)
if (!defined('_CSP_NONCE')) {
    define('_CSP_NONCE', bin2hex(random_bytes(16)));
}

function csp_nonce(): string {
    return _CSP_NONCE;
}

// Security Headers
if (!headers_sent()) {
    $n = _CSP_NONCE;
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    header('Content-Security-Policy: '
        . "default-src 'self'; "
        . "script-src 'self' 'nonce-" . $n . "'; "
        . "style-src 'self' 'nonce-" . $n . "'; "
        . "font-src 'self'; "
        . "img-src 'self' data:; "
        . "connect-src 'self' https://*.supabase.co; "
        . "frame-ancestors 'self'; "
        . "form-action 'self'; "
        . "base-uri 'self'"
    );
    header('Permissions-Policy: geolocation=(), camera=(), microphone=(), payment=(), usb=(), interest-cohort=()');
    header_remove('X-Powered-By');
}
