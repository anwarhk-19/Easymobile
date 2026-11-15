<?php

namespace App\Models;

use App\Models\Concerns\HasPublicFileUrl;
use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    use HasPublicFileUrl;

    protected $guarded = ['id'];

    protected $appends = ['url'];

    public function getUrlAttribute(): ?string
    {
        return $this->publicUrl($this->path);
    }
}
