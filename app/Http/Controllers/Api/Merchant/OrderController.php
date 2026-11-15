<?php

namespace App\Http\Controllers\Api\Merchant;

use App\Models\Order;
use App\Services\OrderService;
use App\Services\WhatsAppLinkBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class OrderController extends MerchantController
{
    public function __construct(private OrderService $orders) {}

    public function index(Request $request): JsonResponse
    {
        $shop = $this->shop($request);
        $q = Order::where('shop_id', $shop->id)->with(['customer:id,name', 'items:id,order_id,product_name,quantity'])->latest();
        if ($request->filled('status')) {
            $groups = [
                'action' => [Order::REQUESTED, Order::ACCEPTED],
                'pending' => [Order::REQUESTED, Order::QUOTED, Order::ACCEPTED],
                'active' => [Order::CONFIRMED, Order::READY, Order::HANDED_OVER],
                'closed' => [Order::CANCELLED, Order::REJECTED],
            ];
            $q->whereIn('status', $groups[$request->input('status')] ?? [$request->input('status')]);
        }
        if ($request->filled('q')) {
            $q->where('reference', 'like', '%'.$request->input('q').'%');
        }

        return response()->json($q->paginate(20)->withQueryString());
    }

    public function show(Request $request, Order $order, WhatsAppLinkBuilder $wa): JsonResponse
    {
        Gate::authorize('actAsMerchant', $order);
        $order->load(['customer:id,name,phone', 'items.product:id,name,sku,stock_quantity,availability,slug', 'histories.user', 'payments', 'refunds', 'deliveryArea:id,name', 'enquiry']);

        return response()->json([
            'order' => $order,
            'whatsapp_customer_link' => $wa->link($order->customer->phone, "Hello {$order->customer->name}, this is {$order->shop->name} regarding your order request {$order->reference} on Bhatkal Mobile Hub."),
            'whatsapp_confirmation_link' => in_array($order->status, [Order::CONFIRMED, Order::READY], true) ? $wa->orderConfirmationToCustomer($order) : null,
        ]);
    }

    public function quote(Request $request, Order $order): JsonResponse
    {
        Gate::authorize('actAsMerchant', $order);
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.variant' => ['nullable', 'string', 'max:100'],
            'items.*.colour' => ['nullable', 'string', 'max:60'],
            'items.*.model_note' => ['nullable', 'string', 'max:150'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1', 'max:20'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:500000'],
            'fulfilment' => ['nullable', Rule::in(['pickup', 'delivery'])],
            'delivery_fee' => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'merchant_note' => ['nullable', 'string', 'max:1000'],
        ]);

        return response()->json(['order' => $this->orders->quote($order, $request->user(), $data)->load('items')]);
    }

    public function reject(Request $request, Order $order): JsonResponse
    {
        Gate::authorize('actAsMerchant', $order);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return response()->json(['order' => $this->orders->reject($order, $request->user(), $data['reason'])]);
    }

    public function confirm(Request $request, Order $order): JsonResponse
    {
        Gate::authorize('actAsMerchant', $order);

        return response()->json(['order' => $this->orders->confirm($order->load('items.product'), $request->user())]);
    }

    public function ready(Request $request, Order $order): JsonResponse
    {
        Gate::authorize('actAsMerchant', $order);

        return response()->json(['order' => $this->orders->markReady($order, $request->user())]);
    }

    public function handover(Request $request, Order $order): JsonResponse
    {
        Gate::authorize('actAsMerchant', $order);

        return response()->json(['order' => $this->orders->markHandedOver($order, $request->user())]);
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        Gate::authorize('actAsMerchant', $order);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return response()->json(['order' => $this->orders->cancel($order->load('items.product'), $request->user(), $data['reason'])]);
    }

    public function payment(Request $request, Order $order): JsonResponse
    {
        Gate::authorize('actAsMerchant', $order);
        $data = $request->validate([
            'method' => ['required', Rule::in(Order::PAYMENT_METHODS)],
            'amount' => ['required', 'numeric', 'min:1', 'max:'.max(1, (float) $order->total_amount)],
            'upi_reference' => ['required_if:method,upi', 'nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9]+$/'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json(['payment' => $this->orders->recordPayment($order, $request->user(), $data), 'order' => $order->fresh()], 201);
    }
}
