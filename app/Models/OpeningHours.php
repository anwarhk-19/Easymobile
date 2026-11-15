<?php

namespace App\Models;

use Illuminate\Support\Carbon;

/** Helper that evaluates whether a business is open right now (Asia/Kolkata). */
final class OpeningHours
{
    public static function isOpen(?string $opens, ?string $closes, ?array $days, ?Carbon $at = null): ?bool
    {
        if (! $opens || ! $closes) {
            return null;
        }
        $now = ($at ?? Carbon::now())->copy()->setTimezone(config('app.business_timezone', 'Asia/Kolkata'));
        if (is_array($days) && $days !== [] && ! in_array(strtolower($now->format('D')), array_map('strtolower', $days), true)) {
            return false;
        }
        $time = $now->format('H:i:s');
        $opens = strlen($opens) === 5 ? $opens.':00' : $opens;
        $closes = strlen($closes) === 5 ? $closes.':00' : $closes;

        return $opens <= $closes
            ? ($time >= $opens && $time < $closes)
            : ($time >= $opens || $time < $closes);
    }
}
