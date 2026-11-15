<?php

namespace App\Support;

use Illuminate\Support\Carbon;

final class BusinessDays
{
    /** Adds business days (Monday–Saturday; Sunday is a holiday for most Bhatkal shops). */
    public static function add(Carbon $from, int $days = 1): Carbon
    {
        $date = $from->copy();
        while ($days > 0) {
            $date->addDay();
            if (! $date->isSunday()) {
                $days--;
            }
        }

        return $date;
    }
}
