<?php

namespace App\Models;

use App\Models\Concerns\HasPublicFileUrl;
use Illuminate\Database\Eloquent\Model;

class RepairImage extends Model
{
    use HasPublicFileUrl;

    protected $guarded = ['id'];

    protected $hidden = ['path'];

    protected $appends = ['url'];

    /** Repair images are private: served through an authorised endpoint, never from the public disk. */
    public function getUrlAttribute(): string
    {
        return url("/api/v1/repair-images/{$this->id}");
    }
}
