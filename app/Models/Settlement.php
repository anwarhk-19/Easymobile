<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Settlement extends Model
{
    public const STATUSES = ['pending', 'processing', 'paid', 'on_hold'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'settled_at' => 'datetime',
            'gross_amount' => 'decimal:2',
            'platform_fee' => 'decimal:2',
            'adjustments' => 'decimal:2',
            'refunds' => 'decimal:2',
            'net_payable' => 'decimal:2',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
