<?php

namespace App\Services;

use App\Exceptions\WorkflowException;
use App\Models\Order;
use App\Models\Settlement;
use App\Models\Shop;
use App\Models\User;
use App\Support\Reference;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Settlement tracking. This only records what is owed and whether it was paid manually —
 * funds are never transferred automatically (no settlement integration in the pilot).
 */
class SettlementService
{
    public function unsettledOrders(Shop $shop, Carbon $from, Carbon $to)
    {
        return Order::where('shop_id', $shop->id)->where('status', Order::COMPLETED)->whereNull('settlement_id')
            ->whereBetween('completed_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
    }

    public function generate(Shop $shop, Carbon $from, Carbon $to, User $admin): Settlement
    {
        return DB::transaction(function () use ($shop, $from, $to, $admin) {
            $orders = $this->unsettledOrders($shop, $from, $to)->lockForUpdate()->get();
            if ($orders->isEmpty()) {
                throw new WorkflowException('No completed, unsettled orders for this shop in the selected period.');
            }
            $settlement = Settlement::create([
                'reference' => Reference::make('STL', Settlement::class),
                'shop_id' => $shop->id,
                'period_start' => $from->toDateString(),
                'period_end' => $to->toDateString(),
                'orders_count' => $orders->count(),
                'gross_amount' => $orders->sum(fn ($o) => (float) $o->total_amount),
                'platform_fee' => $orders->sum(fn ($o) => (float) $o->commission_amount),
                'adjustments' => $orders->sum(fn ($o) => (float) $o->adjustment_amount),
                'refunds' => $orders->sum(fn ($o) => (float) $o->refund_amount),
                'net_payable' => $orders->sum(fn ($o) => (float) $o->merchant_payable),
                'status' => 'pending',
                'created_by' => $admin->id,
            ]);
            Order::whereIn('id', $orders->pluck('id'))->update(['settlement_id' => $settlement->id]);
            AuditLogger::log('settlement.generated', $settlement, "Settlement {$settlement->reference} generated for {$shop->name}", ['orders' => $orders->count()]);

            return $settlement;
        });
    }

    public function updateStatus(Settlement $settlement, string $status, ?string $paymentReference, ?string $notes): Settlement
    {
        if ($settlement->status === 'paid') {
            throw new WorkflowException('This settlement is already marked as paid.');
        }
        $settlement->update([
            'status' => $status,
            'payment_reference' => $paymentReference ?? $settlement->payment_reference,
            'notes' => $notes ?? $settlement->notes,
            'settled_at' => $status === 'paid' ? now() : null,
        ]);
        AuditLogger::log('settlement.'.$status, $settlement, "Settlement {$settlement->reference} marked {$status}", ['payment_reference' => $paymentReference]);

        return $settlement;
    }
}
