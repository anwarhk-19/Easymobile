<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    public const AVAILABILITY = ['in_stock', 'limited', 'out_of_stock', 'on_order'];
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $guarded = ['id'];

    protected $appends = ['effective_price', 'stock_freshness'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'discount_price' => 'decimal:2',
            'rating_avg' => 'decimal:2',
            'is_suspended' => 'boolean',
            'stock_confirmed_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->orderBy('sort_order');
    }

    public function compatibleModels(): BelongsToMany
    {
        return $this->belongsToMany(PhoneModel::class, 'product_compatibilities');
    }

    public function stockLogs(): HasMany
    {
        return $this->hasMany(ProductStockLog::class)->latest();
    }

    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    /** Products customers are allowed to see: active, not suspended, from an approved shop. */
    public function scopeVisible(Builder $q): Builder
    {
        return $q->where('products.status', self::STATUS_ACTIVE)
            ->where('products.is_suspended', false)
            ->whereHas('shop', fn ($s) => $s->where('status', Shop::STATUS_APPROVED));
    }

    public function isVisible(): bool
    {
        return $this->status === self::STATUS_ACTIVE && ! $this->is_suspended && $this->shop?->isPublished();
    }

    public function getEffectivePriceAttribute(): ?string
    {
        return $this->discount_price !== null && (float) $this->discount_price < (float) $this->price
            ? $this->discount_price
            : $this->price;
    }

    /** fresh (<= 2 days), recent (<= 7 days), stale (> 7 days) or unknown. */
    public function getStockFreshnessAttribute(): array
    {
        $at = $this->stock_confirmed_at ?? $this->updated_at;
        if (! $at) {
            return ['level' => 'unknown', 'label' => 'Stock not confirmed'];
        }
        $days = $at->diffInDays(now());
        $level = $days <= 2 ? 'fresh' : ($days <= 7 ? 'recent' : 'stale');

        return ['level' => $level, 'label' => 'Stock confirmed '.$at->diffForHumans(), 'confirmed_at' => $at->toIso8601String()];
    }
}
