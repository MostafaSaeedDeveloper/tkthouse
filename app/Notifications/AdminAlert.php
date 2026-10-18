<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** A bell notification for a staff member. Stored in the database only; `data` is what the bell renders. */
class AdminAlert extends Notification
{
    /** @param array{type: string, title: string, body?: ?string, url?: ?string, icon?: ?string} $payload */
    public function __construct(private readonly array $payload)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->payload['type'],
            'title' => $this->payload['title'],
            'body' => $this->payload['body'] ?? null,
            'url' => $this->payload['url'] ?? null,
            'icon' => $this->payload['icon'] ?? 'fa-bell',
        ];
    }
}
