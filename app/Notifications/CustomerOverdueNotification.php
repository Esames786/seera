<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** In-app only; published with a deterministic daily ID, never queued or emailed. */
class CustomerOverdueNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['database'];
    }
}
