<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/** Database-portable date bucketing expressions for reports (MySQL in production, SQLite for tests). */
final class DateBucket
{
    public static function expression(string $column, string $period): string
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            return match ($period) {
                'week' => "strftime('%Y-W%W', {$column})",
                'month' => "strftime('%Y-%m', {$column})",
                default => "date({$column})",
            };
        }
        if ($driver === 'pgsql') {
            return match ($period) {
                'week' => "to_char({$column}, 'IYYY-\"W\"IW')",
                'month' => "to_char({$column}, 'YYYY-MM')",
                default => "to_char({$column}, 'YYYY-MM-DD')",
            };
        }

        return match ($period) {
            'week' => "DATE_FORMAT({$column}, '%x-W%v')",
            'month' => "DATE_FORMAT({$column}, '%Y-%m')",
            default => "DATE({$column})",
        };
    }
}
