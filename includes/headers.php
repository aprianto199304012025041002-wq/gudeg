<?php
/**
 * headers.php — Security headers dan CSP.
 * Di-include sekali via functions.php. Headers dikirim saat file ini di-load.
 */

// ── Security Headers ─────────────────────────────────────────────────────────
if (!headers_sent()) {
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
    // TODO #79 #80: hapus 'unsafe-inline' dari script-src dan style-src
    // setelah semua inline script/style dipindah ke file eksternal.
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; font-src 'self'; img-src 'self' data:; connect-src 'self' https://*.supabase.co; frame-ancestors 'self'; form-action 'self'; base-uri 'self'");
    header("Permissions-Policy: geolocation=(), camera=(), microphone=(), payment=(), usb=(), interest-cohort=()");
    header_remove("X-Powered-By");
}
