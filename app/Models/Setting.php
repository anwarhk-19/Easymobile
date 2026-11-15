<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $guarded = ['id'];

    public const DEFAULTS = [
        'platform_name' => 'Bhatkal Mobile Hub',
        'support_phone' => '919000000000',
        'support_email' => 'support@bhatkalmobilehub.in',
        'reviews_auto_approve' => false,
        'stock_stale_days' => 7,
        'ticket_ack_business_hours' => 24,
        'default_city' => 'Bhatkal',
        'max_open_order_requests_per_customer' => 10,
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = Cache::rememberForever('settings:all', fn () => static::query()->pluck('value', 'key')
            ->map(fn ($v) => json_decode($v, true))->all());

        return array_key_exists($key, $all) ? $all[$key] : ($default ?? self::DEFAULTS[$key] ?? null);
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => json_encode($value)]);
        Cache::forget('settings:all');
    }

    /** @return array<string, mixed> */
    public static function allWithDefaults(): array
    {
        $out = [];
        foreach (self::DEFAULTS as $k => $v) {
            $out[$k] = static::get($k, $v);
        }

        return $out;
    }
}
