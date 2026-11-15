<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepairWarranty extends Model
{
    protected $guarded = ['id'];

    protected $appends = ['is_active'];

    protected function casts(): array
    {
        return ['starts_at' => 'date', 'ends_at' => 'date'];
    }

    public function repairRequest(): BelongsTo
    {
        return $this->belongsTo(RepairRequest::class);
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->ends_at !== null && $this->ends_at->endOfDay()->isFuture();
    }
}
