<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    public const CUSTOMER = 'customer';

    public const MERCHANT = 'merchant';

    public const TECHNICIAN = 'technician';

    public const ADMIN = 'admin';

    public const SUPPORT = 'support';

    protected $guarded = ['id'];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }
}
