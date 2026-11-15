<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Verification documents are stored on the private disk and only streamed to authorised users. */
class ShopDocument extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['path'];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
