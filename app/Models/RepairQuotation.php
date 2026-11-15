<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepairQuotation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'parts_cost' => 'decimal:2',
            'labour_cost' => 'decimal:2',
            'total' => 'decimal:2',
            'responded_at' => 'datetime',
        ];
    }

    public function repairRequest(): BelongsTo
    {
        return $this->belongsTo(RepairRequest::class);
    }
}
