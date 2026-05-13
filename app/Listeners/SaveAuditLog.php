<?php

namespace App\Listeners;

use App\Events\AuditLogEvent;
use Illuminate\Support\Facades\DB;

class SaveAuditLog
{
    public function handle(AuditLogEvent $event): void
    {
        try {
            if ($event->type === 'user') {
                DB::table('user_logs')->insert([
                    'user_id'    => $event->data['user']->id,
                    'user_name'  => $event->data['user']->name,
                    'user_email' => $event->data['user']->email,
                    'activity'   => $event->data['activity'],
                    'ip_address' => $event->data['ip'],
                    'created_at' => now(),
                ]);
            } elseif ($event->type === 'admin') {
                DB::table('admin_logs')->insert([
                    'admin_id'    => $event->data['admin']->id,
                    'admin_name'  => $event->data['admin']->name,
                    'action'      => $event->data['action'],
                    'target_user' => $event->data['target_user'],
                    'created_at'  => now(),
                ]);
            }
        } catch (\Exception $e) {}
    }
}
