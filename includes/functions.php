<?php
/**
 * functions.php — Aggregator utama GUDEG SSO.
 *
 * File ini HANYA berisi require_once ke modul-modul terpisah.
 * Semua file di seluruh aplikasi cukup require file ini satu kali —
 * tidak perlu diubah meski functions.php dipecah (#88).
 *
 * Urutan require penting:
 *  1. env.php       — load .env dan SupabaseClient (tidak ada dependency)
 *  2. logger.php    — gudeg_log() (dipakai oleh semua modul berikutnya)
 *  3. headers.php   — kirim security headers (harus sebelum output apapun)
 *  4. session.php   — session management + konstanta (butuh logger)
 *  5. activity.php  — log aktivitas (butuh logger + getSupabase)
 *  6. csrf.php      — CSRF helpers (butuh session + logger)
 *  7. validation.php — input validation (butuh getSupabase + konstanta)
 *  8. token.php     — JWT helpers + blacklist (butuh logger + getSupabase)
 */

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/headers.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/activity.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/token.php';
