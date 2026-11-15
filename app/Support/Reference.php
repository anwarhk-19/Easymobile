<?php

namespace App\Support;

final class Reference
{
    /** Generates human-friendly unique references like ORD-2611-7KQ2MX. */
    public static function make(string $prefix, string $modelClass, string $column = 'reference'): string
    {
        do {
            // Random part avoids look-alike characters (0/O, 1/I/L) so references are easy to read out over the phone.
            $random = substr(str_shuffle(str_repeat('23456789ABCDEFGHJKMNPQRSTUVWXYZ', 3)), 0, 6);
            $ref = sprintf('%s-%s-%s', $prefix, now()->format('ym'), $random);
        } while ($modelClass::where($column, $ref)->exists());

        return $ref;
    }
}
