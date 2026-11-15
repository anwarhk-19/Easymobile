<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\AuditLogger;
use App\Services\CommissionService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function index(Request $request): JsonResponse
    {
        $q = Order::with(['shop:id,name', 'customer:id,name,phone'])->latest();
        foreach (['status', 'shop_id', 'payment_status'] as $f) {
            if ($request->filled($f)) {
                $q->where($f, $request->input($f));
            }
        }
        if ($request->filled('q')) {
            $q->where('reference', 'like', '%'.$request->input('q').'%');
        }
        if ($request->filled('from')) {
            $q->whereDate('created_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $q->whereDate('created_at', '<=', $request->input('to'));
        }

        return response()->json($q->paginate(25)->withQueryString());
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json(['order' => $order->load(['shop:id,name,phone', 'customer:id,name,phone', 'items', 'histories.user', 'payments', 'refunds', 'settlement:id,reference,status', 'deliveryArea:id,name'])]);
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->orders->cancel($order->load('items.product'), $request->user(), $data['reason']);
        AuditLogger::log('order.cancelled', $order, "Admin cancelled order {$order->reference}", $data);

        return response()->json(['order' => $order]);
    }

    public function complete(Request $request, Order $order): JsonResponse
    {
        $this->orders->confirmReceipt($order, $request->user());
        AuditLogger::log('order.completed', $order, "Admin completed order {$order->reference}");

        return response()->json(['order' => $order]);
    }

    /** Manual adjustment to the merchant payable (e.g. a goodwill credit or a penalty). */
    public function adjust(Request $request, Order $order, CommissionService $commissions): JsonResponse
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'between:-100000,100000'], 'note' => ['required', 'string', 'max:500']]);
        abort_if($order->settlement_id && $order->settlement?->status === 'paid', 422, 'Order is already in a paid settlement.');
        $order->adjustment_amount = round((float) $order->adjustment_amount + (float) $data['amount'], 2);
        $commissions->recalculatePayable($order);
        $order->save();
        $this->orders->history($order, $order->status, $order->status, $request->user(), 'Adjustment ₹'.$data['amount'].': '.$data['note']);
        AuditLogger::log('order.adjusted', $order, "Adjusted order {$order->reference} by ₹{$data['amount']}", $data);

        return response()->json(['order' => $order]);
    }
}
