<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use App\Events\AuditLogEvent;
use App\Listeners\SaveAuditLog;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Menyambungkan Event Log dengan Listener
        Event::listen(AuditLogEvent::class, SaveAuditLog::class);
    }
}
