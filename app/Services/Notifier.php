<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\PlatformNotification;
use App\Services\Sms\SmsSender;

class Notifier
{
    public function __construct(private TemplateRenderer $renderer, private SmsSender $sms) {}

    public function notify(?User $user, string $title, string $templateKey, array $vars, ?string $link = null, bool $sms = false): void
    {
        if (! $user) {
            return;
        }
        $user->notify(new PlatformNotification($title, $this->renderer->render($templateKey, 'in_app', $vars), $link));

        if ($sms && $user->phone && isset(TemplateRenderer::DEFAULTS['sms'][$templateKey])) {
            $this->sms->send($user->phone, $this->renderer->render($templateKey, 'sms', $vars));
        }
    }

    public static function label(string $status): string
    {
        return ucwords(str_replace('_', ' ', $status));
    }
}
