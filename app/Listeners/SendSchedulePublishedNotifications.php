<?php

namespace App\Listeners;

use App\Events\SchedulePublished;
use App\Models\User;
use App\Notifications\SchedulePublishedNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Ouve App\Events\SchedulePublished (PRD F7) e avisa toda a equipa da organização.
 * Descoberto automaticamente pelo Laravel (auto-discovery por type-hint no handle()).
 */
class SendSchedulePublishedNotifications
{
    public function handle(SchedulePublished $event): void
    {
        $users = User::where('organization_id', $event->schedule->organization_id)->get();

        if ($users->isEmpty()) {
            return;
        }

        Notification::send($users, new SchedulePublishedNotification($event->schedule));
    }
}
