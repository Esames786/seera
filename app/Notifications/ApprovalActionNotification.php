<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ApprovalActionNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $event,
        public readonly string $module,
        public readonly string $reference,
        public readonly string $sourceType,
        public readonly int $sourceId,
        public readonly int $instanceId,
    ) {}

    public function via(object $notifiable): array
    {
        // In-app only. No email, SMS, push, remote delivery or background job.
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'event' => $this->event,
            'module' => $this->module,
            'reference' => $this->reference,
            'source_type' => $this->sourceType,
            'source_id' => $this->sourceId,
            'approval_instance_id' => $this->instanceId,
        ];
    }
}
