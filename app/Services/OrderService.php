<?php

namespace App\Services;

use App\Exceptions\WorkflowException;
use App\Models\Enquiry;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Shop;
use App\Models\User;
use App\Support\Reference;
use Illuminate\Support\Facades\DB;

/**
 * Order workflow. An order request is never treated as a confirmed order:
 * requested → quoted (merchant) → accepted (customer) → confirmed (merchant) → ready → handed_over → completed (customer).
 */
class OrderService
{
    public function __construct(
        private CommissionService $commissions,
        private StockService $stock,
        private Notifier $notifier,
    ) {}

    /** @param array<int, array{product_id:int, quantity:int, variant?:string, colour?:string, model_note?:string}> $items */
    public function createRequest(User $customer, Shop $shop, array $items, array $data, ?Enquiry $enquiry = null): Order
    {
        if (! $shop->isPublished()) {
            throw new WorkflowException('This shop is not currently accepting orders.');
        }
        $fulfilment = $data['fulfilment'] ?? 'pickup';
        if ($fulfilment === 'delivery' && ! $shop->delivery_available) {
            throw new WorkflowException('This shop does not offer delivery. Please choose pickup.');
        }
        if ($fulfilment === 'pickup' && ! $shop->pickup_available) {
            throw new WorkflowException('This shop does not offer pickup. Please choose delivery.');
        }
        $open = Order::where('customer_id', $customer->id)->whereIn('status', [Order::REQUESTED, Order::QUOTED])->count();
        if ($open >= (int) Setting::get('max_open_order_requests_per_customer')) {
            throw new WorkflowException('You have too many pending order requests. Please wait for shops to respond.');
        }

        return DB::transaction(function () use ($customer, $shop, $items, $data, $enquiry, $fulfilment) {
            $order = Order::create([
                'reference' => Reference::make('ORD', Order::class),
                'customer_id' => $customer->id,
                'shop_id' => $shop->id,
                'enquiry_id' => $enquiry?->id,
                'status' => Order::REQUESTED,
                'fulfilment' => $fulfilment,
                'delivery_address' => $fulfilment === 'delivery' ? ($data['delivery_address'] ?? null) : null,
                'delivery_area_id' => $fulfilment === 'delivery' ? ($data['delivery_area_id'] ?? null) : null,
                'customer_note' => $data['customer_note'] ?? null,
                'payment_method' => $data['payment_method'] ?? ($fulfilment === 'pickup' ? 'pickup_payment' : 'cod'),
            ]);

            foreach ($items as $item) {
                $product = Product::visible()->where('shop_id', $shop->id)->find($item['product_id']);
                if (! $product) {
                    throw new WorkflowException('One of the selected products is not available from this shop.');
                }
                $qty = max(1, (int) $item['quantity']);
                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'variant' => $item['variant'] ?? null,
                    'colour' => $item['colour'] ?? null,
                    'model_note' => $item['model_note'] ?? null,
                    'quantity' => $qty,
                    'unit_price' => $product->effective_price,
                    'line_total' => round((float) $product->effective_price * $qty, 2),
                ]);
            }
            $this->recalculateTotals($order, $fulfilment === 'delivery' ? (float) $shop->delivery_fee : 0.0);
            $order->save();

            $enquiry?->update(['status' => 'converted']);
            $this->history($order, null, Order::REQUESTED, $customer, 'Order request submitted. Awaiting shop confirmation of availability and price.');
            $this->notifier->notify($shop->merchant, 'New order request', 'order_status',
                ['order_reference' => $order->reference, 'status_label' => 'Requested'], '/merchant/orders/'.$order->id);

            return $order;
        });
    }

    /** Merchant confirms exact model/variant/colour/price and delivery fee or pickup. */
    public function quote(Order $order, User $merchant, array $data): Order
    {
        $this->assertStatus($order, [Order::REQUESTED, Order::QUOTED], 'quote');

        return DB::transaction(function () use ($order, $merchant, $data) {
            foreach ($data['items'] as $line) {
                $item = $order->items()->whereKey($line['id'])->firstOrFail();
                $qty = (int) ($line['quantity'] ?? $item->quantity);
                $item->update([
                    'variant' => $line['variant'] ?? $item->variant,
                    'colour' => $line['colour'] ?? $item->colour,
                    'model_note' => $line['model_note'] ?? $item->model_note,
                    'quantity' => $qty,
                    'unit_price' => $line['unit_price'],
                    'line_total' => round((float) $line['unit_price'] * $qty, 2),
                ]);
            }
            if (isset($data['fulfilment'])) {
                $order->fulfilment = $data['fulfilment'];
            }
            $fee = $order->fulfilment === 'delivery' ? (float) ($data['delivery_fee'] ?? 0) : 0.0;
            $order->merchant_note = $data['merchant_note'] ?? $order->merchant_note;
            $order->load('items');
            $this->recalculateTotals($order, $fee);
            $from = $order->status;
            $order->status = Order::QUOTED;
            $order->quoted_at = now();
            $order->save();

            $this->history($order, $from, Order::QUOTED, $merchant, 'Shop confirmed product details and final price: ₹'.number_format((float) $order->total_amount, 2));
            $this->notifyCustomer($order, 'Quotation ready — please review and accept');

            return $order;
        });
    }

    public function accept(Order $order, User $customer): Order
    {
        $this->assertStatus($order, [Order::QUOTED], 'accept');

        return $this->transition($order, Order::ACCEPTED, $customer, 'Customer accepted the final quotation.', ['accepted_at' => now()], notifyMerchant: true);
    }

    /** Merchant confirms the order: stock is reserved and commission is snapshotted. */
    public function confirm(Order $order, User $merchant): Order
    {
        $this->assertStatus($order, [Order::ACCEPTED], 'confirm');

        return DB::transaction(function () use ($order, $merchant) {
            foreach ($order->items as $item) {
                if ($item->product) {
                    $product = Product::whereKey($item->product_id)->lockForUpdate()->first();
                    if ($product->stock_quantity < $item->quantity && $product->availability !== 'on_order') {
                        throw new WorkflowException("Insufficient stock for {$product->name}. Update stock before confirming.");
                    }
                    if ($product->stock_quantity > 0) {
                        $this->stock->set($product, $product->stock_quantity - min($item->quantity, $product->stock_quantity), 'order_confirmed', $order->reference);
                    }
                }
            }
            $this->commissions->applyTo($order);

            return $this->transition($order, Order::CONFIRMED, $merchant, 'Shop confirmed the order.', ['confirmed_at' => now()]);
        });
    }

    public function markReady(Order $order, User $merchant): Order
    {
        $this->assertStatus($order, [Order::CONFIRMED], 'mark ready');
        $note = $order->fulfilment === 'delivery' ? 'Order is ready and out for delivery.' : 'Order is ready for pickup at the shop.';

        return $this->transition($order, Order::READY, $merchant, $note, ['ready_at' => now()]);
    }

    public function markHandedOver(Order $order, User $merchant): Order
    {
        $this->assertStatus($order, [Order::READY], 'mark delivered/collected');
        $note = $order->fulfilment === 'delivery' ? 'Shop marked the order as delivered.' : 'Shop marked the order as collected.';

        return $this->transition($order, Order::HANDED_OVER, $merchant, $note.' Awaiting customer confirmation.', ['handed_over_at' => now()]);
    }

    public function confirmReceipt(Order $order, User $actor): Order
    {
        $this->assertStatus($order, [Order::HANDED_OVER], 'confirm receipt');

        return DB::transaction(function () use ($order, $actor) {
            $this->commissions->recalculatePayable($order);

            return $this->transition($order, Order::COMPLETED, $actor, $actor->isStaff() ? 'Completed by support.' : 'Customer confirmed receipt.', ['completed_at' => now()], notifyMerchant: true);
        });
    }

    public function reject(Order $order, User $merchant, string $reason): Order
    {
        $this->assertStatus($order, [Order::REQUESTED, Order::QUOTED], 'reject');

        return $this->transition($order, Order::REJECTED, $merchant, 'Shop could not fulfil: '.$reason, ['cancelled_at' => now(), 'cancel_reason' => $reason, 'cancelled_by' => $merchant->id]);
    }

    public function cancel(Order $order, User $actor, string $reason): Order
    {
        $allowed = match (true) {
            $actor->isStaff() => Order::OPEN_STATUSES,
            $actor->id === $order->customer_id => [Order::REQUESTED, Order::QUOTED, Order::ACCEPTED],
            default => [Order::REQUESTED, Order::QUOTED, Order::ACCEPTED, Order::CONFIRMED, Order::READY],
        };
        $this->assertStatus($order, $allowed, 'cancel');

        return DB::transaction(function () use ($order, $actor, $reason) {
            if ($order->confirmed_at) {
                foreach ($order->items as $item) {
                    if ($item->product) {
                        $this->stock->adjust($item->product, $item->quantity, 'order_cancelled', $order->reference);
                    }
                }
            }
            $order->commission_amount = 0;
            $order->merchant_payable = 0;

            return $this->transition($order, Order::CANCELLED, $actor, 'Cancelled: '.$reason,
                ['cancelled_at' => now(), 'cancel_reason' => $reason, 'cancelled_by' => $actor->id], notifyMerchant: $actor->id === $order->customer_id);
        });
    }

    public function recordPayment(Order $order, User $actor, array $data): Payment
    {
        if (in_array($order->status, [Order::REQUESTED, Order::QUOTED, Order::CANCELLED, Order::REJECTED], true)) {
            throw new WorkflowException('Payments can only be recorded for confirmed orders.');
        }

        return DB::transaction(function () use ($order, $actor, $data) {
            $payment = $order->payments()->create([
                'method' => $data['method'],
                'amount' => $data['amount'],
                'upi_reference' => $data['upi_reference'] ?? null,
                'status' => 'recorded',
                'recorded_by' => $actor->id,
                'paid_at' => $data['paid_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);
            $paid = (float) $order->payments()->sum('amount');
            $order->payment_method = $data['method'];
            $order->payment_status = $paid + 0.001 >= (float) $order->total_amount ? 'paid' : 'pending';
            $order->save();
            $this->history($order, $order->status, $order->status, $actor, 'Payment recorded: ₹'.number_format((float) $data['amount'], 2).' via '.strtoupper($data['method']).(! empty($data['upi_reference']) ? ' (UPI ref '.$data['upi_reference'].')' : ''));

            return $payment;
        });
    }

    public function history(Order $order, ?string $from, string $to, ?User $user, ?string $note = null): void
    {
        OrderStatusHistory::create(['order_id' => $order->id, 'from_status' => $from, 'to_status' => $to, 'user_id' => $user?->id, 'note' => $note]);
    }

    private function transition(Order $order, string $to, User $actor, string $note, array $attributes = [], bool $notifyMerchant = false): Order
    {
        return DB::transaction(function () use ($order, $to, $actor, $note, $attributes, $notifyMerchant) {
            $from = $order->status;
            $order->fill($attributes);
            $order->status = $to;
            $order->save();
            $this->history($order, $from, $to, $actor, $note);
            $vars = ['order_reference' => $order->reference, 'status_label' => Notifier::label($to)];
            if ($notifyMerchant) {
                $this->notifier->notify($order->shop->merchant, 'Order update', 'order_status', $vars, '/merchant/orders/'.$order->id);
            } else {
                $this->notifyCustomer($order);
            }

            return $order;
        });
    }

    private function notifyCustomer(Order $order, string $title = 'Order update'): void
    {
        $this->notifier->notify($order->customer, $title, 'order_status',
            ['order_reference' => $order->reference, 'status_label' => Notifier::label($order->status)], '/orders/'.$order->id, sms: true);
    }

    private function recalculateTotals(Order $order, float $deliveryFee): void
    {
        $order->product_amount = round((float) $order->items()->sum('line_total'), 2);
        $order->delivery_fee = $deliveryFee;
        $order->total_amount = round((float) $order->product_amount + $deliveryFee, 2);
    }

    private function assertStatus(Order $order, array $allowed, string $action): void
    {
        if (! in_array($order->status, $allowed, true)) {
            throw new WorkflowException("Cannot {$action} an order that is ".Notifier::label($order->status).'.');
        }
    }
}
