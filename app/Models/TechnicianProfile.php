<?php

namespace App\Models;

use App\Models\Concerns\HasPublicFileUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TechnicianProfile extends Model
{
    use HasPublicFileUrl, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SUSPENDED = 'suspended';

    protected $guarded = ['id'];

    protected $appends = ['photo_url', 'is_open_now'];

    protected function casts(): array
    {
        return [
            'open_days' => 'array',
            'pickup_available' => 'boolean',
            'onsite_available' => 'boolean',
            'rating_avg' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function serviceAreas(): BelongsToMany
    {
        return $this->belongsToMany(Area::class, 'technician_service_areas');
    }

    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class, 'technician_brands');
    }

    public function phoneModels(): BelongsToMany
    {
        return $this->belongsToMany(PhoneModel::class, 'technician_phone_models');
    }

    public function repairCategories(): BelongsToMany
    {
        return $this->belongsToMany(RepairCategory::class, 'technician_repair_categories');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(TechnicianDocument::class);
    }

    public function repairRequests(): HasMany
    {
        return $this->hasMany(RepairRequest::class);
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

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->publicUrl($this->photo_path);
    }

    public function getIsOpenNowAttribute(): ?bool
    {
        return OpeningHours::isOpen($this->opens_at, $this->closes_at, $this->open_days);
    }
}
