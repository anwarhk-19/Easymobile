<?php

namespace App\Support;

final class Phone
{
    /** Normalises an Indian mobile number to the 10-digit national form. Returns null when invalid. */
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $raw);
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return preg_match('/^[6-9]\d{9}$/', $digits) ? $digits : null;
    }

    /** International form used by wa.me links (no plus sign). */
    public static function whatsapp(?string $raw): ?string
    {
        $n = self::normalize($raw);

        return $n ? '91'.$n : null;
    }

    public static function mask(?string $phone): ?string
    {
        return $phone ? substr($phone, 0, 2).str_repeat('•', 5).substr($phone, -3) : null;
    }
}
