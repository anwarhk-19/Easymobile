<?php

namespace App\Services;

use App\Models\Enquiry;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStockLog;
use App\Models\Refund;
use App\Models\RepairQuotation;
use App\Models\RepairRequest;
use App\Models\SearchLog;
use App\Models\Shop;
use App\Models\SupportTicket;
use App\Models\TechnicianProfile;
use App\Support\DateBucket;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /** @return array{0:Carbon,1:Carbon} */
    public static function range(?string $from, ?string $to): array
    {
        $end = $to ? Carbon::parse($to)->endOfDay() : now()->endOfDay();
        $start = $from ? Carbon::parse($from)->startOfDay() : $end->copy()->subDays(29)->startOfDay();

        return [$start, $end];
    }

    public function admin(Carbon $from, Carbon $to, string $period = 'day'): array
    {
        $bucket = DateBucket::expression('created_at', $period);
        $orders = Order::whereBetween('created_at', [$from, $to]);
        $completed = Order::where('status', Order::COMPLETED)->whereBetween('completed_at', [$from, $to]);
        $totalOrders = (clone $orders)->count();
        $cancelled = (clone $orders)->whereIn('status', [Order::CANCELLED, Order::REJECTED])->count();

        $ordersSeries = Order::selectRaw("{$bucket} as bucket, COUNT(*) as orders, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed, SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END) as revenue, SUM(CASE WHEN status = 'completed' THEN commission_amount ELSE 0 END) as commission")
            ->whereBetween('created_at', [$from, $to])->groupBy('bucket')->orderBy('bucket')->get();

        $customersWithOrders = Order::where('status', Order::COMPLETED)->select('customer_id', DB::raw('COUNT(*) as c'))->groupBy('customer_id')->get();

        $repairs = RepairRequest::whereBetween('created_at', [$from, $to]);
        $completedRepairs = RepairRequest::where('status', RepairRequest::COMPLETED)->whereBetween('completed_at', [$from, $to])->get(['created_at', 'completed_at', 'approved_at']);

        return [
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'period' => $period],
            'orders' => [
                'series' => $ordersSeries,
                'total' => $totalOrders,
                'completed' => (clone $completed)->count(),
                'cancelled' => $cancelled,
                'cancellation_rate' => $totalOrders ? round($cancelled / $totalOrders * 100, 1) : 0,
            ],
            'revenue' => [
                'gross' => round((float) (clone $completed)->sum('total_amount'), 2),
                'commission' => round((float) (clone $completed)->sum('commission_amount'), 2),
                'refunds' => round((float) Refund::where('status', 'processed')->whereBetween('processed_at', [$from, $to])->sum('amount'), 2),
            ],
            'active_merchants' => Shop::published()->whereHas('orders', fn ($q) => $q->whereBetween('created_at', [$from, $to]))->count(),
            'merchant_response_minutes' => $this->avgMinutes(Enquiry::whereNotNull('responded_at')->whereBetween('created_at', [$from, $to])->get(['created_at', 'responded_at']), 'created_at', 'responded_at'),
            'merchant_quote_minutes' => $this->avgMinutes(Order::whereNotNull('quoted_at')->whereBetween('created_at', [$from, $to])->get(['created_at', 'quoted_at']), 'created_at', 'quoted_at'),
            'technician_response_minutes' => $this->avgMinutes(RepairRequest::whereNotNull('accepted_at')->whereBetween('created_at', [$from, $to])->get(['created_at', 'accepted_at']), 'created_at', 'accepted_at'),
            'product_demand' => $this->productDemand($from, $to),
            'no_result_searches' => SearchLog::where('results_count', 0)->whereBetween('created_at', [$from, $to])
                ->select('query', DB::raw('COUNT(*) as searches'), DB::raw('MAX(created_at) as last_searched'))->groupBy('query')->orderByDesc('searches')->limit(25)->get(),
            'complaints' => [
                'total' => SupportTicket::whereBetween('created_at', [$from, $to])->count(),
                'by_category' => SupportTicket::whereBetween('created_at', [$from, $to])->select('category', DB::raw('COUNT(*) as total'))->groupBy('category')->orderByDesc('total')->get(),
                'refunds_requested' => Refund::whereBetween('created_at', [$from, $to])->count(),
            ],
            'repairs' => [
                'total' => (clone $repairs)->count(),
                'completed' => $completedRepairs->count(),
                'avg_turnaround_hours' => round($this->avgMinutes($completedRepairs, 'created_at', 'completed_at') / 60, 1),
                'warranty_claims' => SupportTicket::where('category', 'warranty_issue')->whereBetween('created_at', [$from, $to])->count(),
            ],
            'customer_retention' => [
                'customers_with_completed_orders' => $customersWithOrders->count(),
                'repeat_customers' => $customersWithOrders->where('c', '>', 1)->count(),
                'repeat_rate' => $customersWithOrders->count() ? round($customersWithOrders->where('c', '>', 1)->count() / $customersWithOrders->count() * 100, 1) : 0,
            ],
        ];
    }

    public function merchant(Shop $shop, Carbon $from, Carbon $to): array
    {
        $orders = Order::where('shop_id', $shop->id)->whereBetween('created_at', [$from, $to]);

        return [
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'enquiries' => Enquiry::where('shop_id', $shop->id)->whereBetween('created_at', [$from, $to])->count(),
            'enquiry_response_minutes' => $this->avgMinutes(Enquiry::where('shop_id', $shop->id)->whereNotNull('responded_at')->whereBetween('created_at', [$from, $to])->get(['created_at', 'responded_at']), 'created_at', 'responded_at'),
            'order_requests' => (clone $orders)->count(),
            'confirmed_orders' => (clone $orders)->whereNotNull('confirmed_at')->count(),
            'completed_orders' => (clone $orders)->where('status', Order::COMPLETED)->count(),
            'cancelled_orders' => (clone $orders)->whereIn('status', [Order::CANCELLED, Order::REJECTED])->count(),
            'revenue' => round((float) Order::where('shop_id', $shop->id)->where('status', Order::COMPLETED)->whereBetween('completed_at', [$from, $to])->sum('total_amount'), 2),
            'commission' => round((float) Order::where('shop_id', $shop->id)->where('status', Order::COMPLETED)->whereBetween('completed_at', [$from, $to])->sum('commission_amount'), 2),
            'net_payable' => round((float) Order::where('shop_id', $shop->id)->where('status', Order::COMPLETED)->whereBetween('completed_at', [$from, $to])->sum('merchant_payable'), 2),
            'product_performance' => Product::where('shop_id', $shop->id)->withTrashed()
                ->select('id', 'name', 'sku', 'views_count', 'stock_quantity', 'availability')
                ->withCount(['reviews as reviews_count'])
                ->addSelect(['enquiries' => Enquiry::selectRaw('COUNT(*)')->whereColumn('product_id', 'products.id')->whereBetween('created_at', [$from, $to])])
                ->addSelect(['units_sold' => DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->selectRaw('COALESCE(SUM(order_items.quantity),0)')->whereColumn('order_items.product_id', 'products.id')
                    ->where('orders.status', Order::COMPLETED)->whereBetween('orders.completed_at', [$from, $to])])
                ->orderByDesc('units_sold')->orderByDesc('enquiries')->limit(50)->get(),
            'stock_movement' => ProductStockLog::whereHas('product', fn ($p) => $p->withTrashed()->where('shop_id', $shop->id))
                ->whereBetween('created_at', [$from, $to])->with('product:id,name,sku')->latest()->limit(100)->get(),
        ];
    }

    public function technician(TechnicianProfile $tech, Carbon $from, Carbon $to): array
    {
        $base = RepairRequest::where('technician_profile_id', $tech->id)->whereBetween('created_at', [$from, $to]);
        $ids = (clone $base)->pluck('id');
        $completed = RepairRequest::where('technician_profile_id', $tech->id)->where('status', RepairRequest::COMPLETED)->whereBetween('completed_at', [$from, $to])->get(['id', 'created_at', 'completed_at', 'final_amount']);

        return [
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'requests_received' => $ids->count(),
            'requests_rejected' => (clone $base)->where('status', RepairRequest::REJECTED)->count(),
            'response_minutes' => $this->avgMinutes((clone $base)->whereNotNull('accepted_at')->get(['created_at', 'accepted_at']), 'created_at', 'accepted_at'),
            'quotes_submitted' => RepairQuotation::whereIn('repair_request_id', $ids)->count(),
            'quotes_accepted' => RepairQuotation::whereIn('repair_request_id', $ids)->whereIn('status', ['approved'])->count(),
            'quotes_rejected' => RepairQuotation::whereIn('repair_request_id', $ids)->where('status', 'rejected')->count(),
            'completed_repairs' => $completed->count(),
            'revenue' => round((float) $completed->sum('final_amount'), 2),
            'avg_turnaround_hours' => round($this->avgMinutes($completed, 'created_at', 'completed_at') / 60, 1),
            'warranty_claims' => SupportTicket::where('category', 'warranty_issue')
                ->whereHas('repairRequest', fn ($r) => $r->where('technician_profile_id', $tech->id))->whereBetween('created_at', [$from, $to])->count(),
        ];
    }

    private function productDemand(Carbon $from, Carbon $to)
    {
        return Product::withTrashed()->select('id', 'name', 'shop_id')->with('shop:id,name')
            ->addSelect(['enquiries' => Enquiry::selectRaw('COUNT(*)')->whereColumn('product_id', 'products.id')->whereBetween('created_at', [$from, $to])])
            ->addSelect(['ordered' => DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->selectRaw('COALESCE(SUM(order_items.quantity),0)')->whereColumn('order_items.product_id', 'products.id')->whereBetween('orders.created_at', [$from, $to])])
            ->orderByDesc('ordered')->orderByDesc('enquiries')->limit(15)->get()
            ->filter(fn ($p) => $p->enquiries > 0 || $p->ordered > 0)->values();
    }

    private function avgMinutes($rows, string $start, string $end): float
    {
        $diffs = collect($rows)->filter(fn ($r) => $r->{$start} && $r->{$end})->map(fn ($r) => Carbon::parse($r->{$start})->diffInMinutes(Carbon::parse($r->{$end})));

        return $diffs->isEmpty() ? 0.0 : round($diffs->avg(), 1);
    }
}
