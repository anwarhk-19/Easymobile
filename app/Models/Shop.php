<?php

namespace App\Models;

use App\Models\Concerns\HasPublicFileUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shop extends Model
{
    use HasPublicFileUrl, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SUSPENDED = 'suspended';

    protected $guarded = ['id'];

    protected $appends = ['logo_url', 'is_open_now'];

    protected function casts(): array
    {
        return [
            'open_days' => 'array',
            'pickup_available' => 'boolean',
            'delivery_available' => 'boolean',
            'is_featured' => 'boolean',
            'delivery_fee' => 'decimal:2',
            'rating_avg' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'approved_at' => 'datetime',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ShopPhoto::class)->orderBy('sort_order');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ShopDocument::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function enquiries(): HasMany
    {
        return $this->hasMany(Enquiry::class);
    }

    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_APPROVED);
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->publicUrl($this->logo_path);
    }

    public function getIsOpenNowAttribute(): ?bool
    {
        return OpeningHours::isOpen($this->opens_at, $this->closes_at, $this->open_days);
    }

    public function getMapsLinkAttribute(): ?string
    {
        if ($this->maps_url) {
            return $this->maps_url;
        }
        if ($this->latitude && $this->longitude) {
            return "https://www.google.com/maps/search/?api=1&query={$this->latitude},{$this->longitude}";
        }

        return 'https://www.google.com/maps/search/?api=1&query='.urlencode($this->name.', '.$this->address);
    }
}
