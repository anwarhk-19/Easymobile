<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Development / pilot driver: writes outgoing SMS to the log channel.
 * Replace with an approved DLT-registered SMS gateway driver (MSG91, Twilio, etc.) via config('services.sms.driver').
 */
class LogSmsSender implements SmsSender
{
    public function send(string $phone, string $message): void
    {
        Log::channel(config('services.sms.log_channel', 'stack'))->info('[SMS] to '.$phone.': '.$message);
    }
}
