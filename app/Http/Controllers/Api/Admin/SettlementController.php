<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Settlement;
use App\Models\Shop;
use App\Services\SettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SettlementController extends Controller
{
    public function __construct(private SettlementService $settlements) {}

    public function index(Request $request): JsonResponse
    {
        $q = Settlement::with('shop:id,name')->latest();
        if ($request->filled('status')) {
            $q->where('status', $request->input('status'));
        }
        if ($request->filled('shop_id')) {
            $q->where('shop_id', $request->integer('shop_id'));
        }

        return response()->json($q->paginate(25)->withQueryString());
    }

    /** Unsettled totals per shop — the basis for generating settlements. */
    public function outstanding(): JsonResponse
    {
        $rows = Order::where('status', Order::COMPLETED)->whereNull('settlement_id')
            ->select('shop_id', DB::raw('COUNT(*) as orders_count'), DB::raw('SUM(total_amount) as gross_amount'), DB::raw('SUM(commission_amount) as platform_fee'),
                DB::raw('SUM(adjustment_amount) as adjustments'), DB::raw('SUM(refund_amount) as refunds'), DB::raw('SUM(merchant_payable) as net_payable'),
                DB::raw('MIN(completed_at) as oldest'), DB::raw('MAX(completed_at) as newest'))
            ->groupBy('shop_id')->with('shop:id,name')->get();

        return response()->json($rows);
    }

    public function show(Settlement $settlement): JsonResponse
    {
        return response()->json(['settlement' => $settlement->load(['shop:id,name,owner_name,phone', 'orders:id,settlement_id,reference,completed_at,total_amount,commission_amount,adjustment_amount,refund_amount,merchant_payable'])]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['shop_id' => ['required', 'integer', 'exists:shops,id'], 'from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from']]);
        $s = $this->settlements->generate(Shop::findOrFail($data['shop_id']), Carbon::parse($data['from']), Carbon::parse($data['to']), $request->user());

        return response()->json(['settlement' => $s], 201);
    }

    public function update(Request $request, Settlement $settlement): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,processing,paid,on_hold'],
            'payment_reference' => ['required_if:status,paid', 'nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        return response()->json(['settlement' => $this->settlements->updateStatus($settlement, $data['status'], $data['payment_reference'] ?? null, $data['notes'] ?? null)]);
    }
}
