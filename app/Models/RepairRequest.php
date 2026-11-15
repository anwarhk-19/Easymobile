<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RepairRequest extends Model
{
    public const SUBMITTED = 'submitted';
    public const ACCEPTED = 'accepted';
    public const REJECTED = 'rejected';
    public const DIAGNOSING = 'diagnosing';
    public const QUOTED = 'quoted';                 // awaiting customer approval (initial or revised)
    public const QUOTE_REJECTED = 'quote_rejected';
    public const APPROVED = 'approved';
    public const IN_PROGRESS = 'in_progress';
    public const TESTING = 'testing';
    public const READY = 'ready_for_handover';
    public const COMPLETED = 'completed';
    public const CANCELLED = 'cancelled';

    public const SERVICE_MODES = ['walk_in', 'pickup_drop', 'onsite'];
    public const PARTS_TYPES = ['original', 'oem_compatible', 'refurbished', 'aftermarket'];
    public const OPEN_STATUSES = [self::SUBMITTED, self::ACCEPTED, self::DIAGNOSING, self::QUOTED, self::APPROVED, self::IN_PROGRESS, self::TESTING, self::READY];

    protected $guarded = ['id'];

    protected $hidden = ['imei_encrypted'];

    protected function casts(): array
    {
        $dates = array_fill_keys(['accepted_at', 'diagnosed_at', 'quoted_at', 'approved_at', 'started_at', 'testing_at',
            'ready_at', 'handover_confirmed_at', 'completed_at', 'cancelled_at'], 'datetime');

        return $dates + [
            'test_checklist' => 'array',
            'data_access_consent' => 'boolean',
            'final_amount' => 'decimal:2',
            'imei_encrypted' => 'encrypted',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(TechnicianProfile::class, 'technician_profile_id')->withTrashed();
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function phoneModel(): BelongsTo
    {
        return $this->belongsTo(PhoneModel::class);
    }

    public function repairCategory(): BelongsTo
    {
        return $this->belongsTo(RepairCategory::class);
    }

    public function preferredArea(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'preferred_area_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(RepairImage::class);
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(RepairDiagnosis::class)->orderBy('id');
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(RepairQuotation::class)->orderBy('version');
    }

    public function approvedQuotation(): HasOne
    {
        return $this->hasOne(RepairQuotation::class)->where('status', 'approved')->latest('version');
    }

    public function pendingQuotation(): HasOne
    {
        return $this->hasOne(RepairQuotation::class)->where('status', 'pending')->latest('version');
    }

    public function parts(): HasMany
    {
        return $this->hasMany(RepairPart::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(RepairStatusHistory::class)->orderBy('id');
    }

    public function warranty(): HasOne
    {
        return $this->hasOne(RepairWarranty::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function deviceLabel(): string
    {
        return trim(($this->brand?->name ?? '').' '.($this->phoneModel?->name ?? $this->device_model_text ?? ''));
    }
}
