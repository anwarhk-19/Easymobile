<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Generic in-app notification stored in the `notifications` table; queued so requests stay fast. */
class PlatformNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $title, public string $body, public ?string $link = null, public string $kind = 'info') {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'link' => $this->link, 'kind' => $this->kind];
    }
}
