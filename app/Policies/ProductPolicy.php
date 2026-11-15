<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function manage(User $user, Product $product): bool
    {
        return $user->role === 'merchant' && $product->shop()->withTrashed()->where('merchant_id', $user->id)->exists();
    }
}
