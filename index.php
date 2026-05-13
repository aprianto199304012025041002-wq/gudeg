<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/* ---------------------------------------------------
 * FIX INFINITYFREE HTTPS & 404 POST REDIRECT
 * Memaksa Laravel mengenali koneksi sebagai HTTPS
 * agar form (termasuk login) selalu terkirim dengan aman.
 * --------------------------------------------------- */
$_SERVER['HTTPS'] = 'on';
$_SERVER['SERVER_PORT'] = 443;

if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
}
/* --------------------------------------------------- */

if (file_exists($maintenance = __DIR__.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
