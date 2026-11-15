<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $user->isStaff() || $order->customer_id === $user->id || $this->ownsShop($user, $order);
    }

    public function actAsCustomer(User $user, Order $order): bool
    {
        return $order->customer_id === $user->id;
    }

    public function actAsMerchant(User $user, Order $order): bool
    {
        return $this->ownsShop($user, $order);
    }

    private function ownsShop(User $user, Order $order): bool
    {
        return $user->role === 'merchant' && $order->shop()->where('merchant_id', $user->id)->exists();
    }
}
