<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** Stored in the database and shown in the bell menu, as a toast and as a desktop notification. */
class AiJobFinished extends Notification
{
    public function __construct(
        public string $title,
        public string $body,
        public string $status = 'success', // success, warning, danger
        public ?string $url = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'status' => $this->status, 'url' => $this->url];
    }
}
