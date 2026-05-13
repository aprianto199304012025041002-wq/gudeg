<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\CheckPasswordExpiration;
use App\Http\Middleware\CheckMaintenanceMode;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin'           => AdminMiddleware::class,
            'password.expire' => CheckPasswordExpiration::class,
        ]);

        // Aktifkan pengecekan maintenance di semua rute web
        $middleware->appendToGroup('web', CheckMaintenanceMode::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
