<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\WorkflowException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Refund;
use App\Services\AuditLogger;
use App\Services\CommissionService;
use App\Services\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RefundController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Refund::with(['order:id,reference,shop_id,total_amount,refund_amount', 'order.shop:id,name', 'requester:id,name'])->latest();
        if ($request->filled('status')) {
            $q->where('status', $request->input('status'));
        }

        return response()->json($q->paginate(25)->withQueryString());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $order = Order::findOrFail($data['order_id']);
        $refundable = (float) $order->total_amount - (float) $order->refund_amount - (float) $order->refunds()->whereIn('status', ['requested', 'approved'])->sum('amount');
        if ((float) $data['amount'] > $refundable + 0.001) {
            throw new WorkflowException('Refund exceeds the refundable amount of ₹'.number_format($refundable, 2).'.');
        }
        $refund = $order->refunds()->create($data + ['requested_by' => $request->user()->id, 'status' => 'requested']);
        AuditLogger::log('refund.created', $refund, "Refund of ₹{$data['amount']} raised for {$order->reference}", $data);

        return response()->json(['refund' => $refund], 201);
    }

    public function process(Request $request, Refund $refund, CommissionService $commissions, Notifier $notifier): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected,processed'],
            'method' => ['required_if:status,processed', 'nullable', 'in:cash,upi,bank_transfer,store_credit'],
            'reference' => ['nullable', 'string', 'max:60'],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);
        if (in_array($refund->status, ['rejected', 'processed'], true)) {
            throw new WorkflowException('This refund has already been '.$refund->status.'.');
        }
        DB::transaction(function () use ($refund, $data, $request, $commissions) {
            $refund->update($data + ['processed_by' => $request->user()->id, 'processed_at' => now()]);
            if ($data['status'] === 'processed') {
                $order = Order::whereKey($refund->order_id)->lockForUpdate()->first();
                $order->refund_amount = round((float) $order->refund_amount + (float) $refund->amount, 2);
                $order->payment_status = $order->refund_amount + 0.001 >= (float) $order->total_amount ? 'refunded' : 'partially_refunded';
                // Refunds after completion reduce what the merchant is owed.
                if ($order->status === Order::COMPLETED) {
                    $commissions->recalculatePayable($order);
                }
                $order->save();
            }
            AuditLogger::log('refund.'.$data['status'], $refund, "Refund #{$refund->id} {$data['status']}", $data);
        });
        $notifier->notify($refund->order->customer, 'Refund update', 'order_status', ['order_reference' => $refund->order->reference, 'status_label' => 'refund '.$data['status']], '/orders/'.$refund->order_id);

        return response()->json(['refund' => $refund->fresh('order')]);
    }
}
