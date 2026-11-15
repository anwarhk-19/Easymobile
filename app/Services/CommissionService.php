<?php

namespace App\Services;

use App\Models\Commission;
use App\Models\Order;

class CommissionService
{
    /**
     * Resolves the applicable rule. Precedence: shop-specific > category-specific > global.
     * No active rule means the listing is free (0 commission).
     */
    public function resolveRule(int $shopId, ?int $categoryId): ?Commission
    {
        $active = Commission::where('is_active', true)
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', now()->toDateString()));

        return (clone $active)->where('scope', 'shop')->where('shop_id', $shopId)->latest('id')->first()
            ?? ($categoryId ? (clone $active)->where('scope', 'category')->where('category_id', $categoryId)->latest('id')->first() : null)
            ?? (clone $active)->where('scope', 'global')->latest('id')->first();
    }

    /** @return array{type:string, rate:float, amount:float} */
    public function calculate(Order $order): array
    {
        $order->loadMissing('items.product');
        $categoryId = $order->items->first()?->product?->category_id;
        $rule = $this->resolveRule($order->shop_id, $categoryId);
        $base = (float) $order->product_amount;

        if (! $rule || $rule->type === 'free') {
            return ['type' => 'free', 'rate' => 0.0, 'amount' => 0.0];
        }
        $amount = $rule->type === 'percentage' ? round($base * (float) $rule->value / 100, 2) : min((float) $rule->value, $base);

        return ['type' => $rule->type, 'rate' => (float) $rule->value, 'amount' => $amount];
    }

    public function applyTo(Order $order): void
    {
        $c = $this->calculate($order);
        $order->commission_type = $c['type'];
        $order->commission_rate = $c['rate'];
        $order->commission_amount = $c['amount'];
        $this->recalculatePayable($order);
    }

    public function recalculatePayable(Order $order): void
    {
        $order->merchant_payable = round((float) $order->total_amount - (float) $order->commission_amount
            + (float) $order->adjustment_amount - (float) $order->refund_amount, 2);
    }
}
