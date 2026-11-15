<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RepairPart extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['unit_cost' => 'decimal:2'];
    }
}
