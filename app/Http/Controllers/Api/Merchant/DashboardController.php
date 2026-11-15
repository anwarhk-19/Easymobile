<?php

namespace App\Http\Controllers\Api\Merchant;

use App\Models\Enquiry;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends MerchantController
{
    public function __invoke(Request $request): JsonResponse
    {
        $shop = $this->shop($request);
        $products = Product::where('shop_id', $shop->id);
        $orders = Order::where('shop_id', $shop->id);
        $staleDays = (int) \App\Models\Setting::get('stock_stale_days');

        return response()->json([
            'shop' => $shop->only(['id', 'name', 'slug', 'status', 'rejection_reason', 'rating_avg', 'rating_count']),
            'stats' => [
                'total_products' => (clone $products)->count(),
                'active_products' => (clone $products)->where('status', 'active')->where('is_suspended', false)->count(),
                'out_of_stock_products' => (clone $products)->where('availability', 'out_of_stock')->count(),
                'stale_stock_products' => (clone $products)->where('status', 'active')->where(fn ($q) => $q->whereNull('stock_confirmed_at')->orWhere('stock_confirmed_at', '<', now()->subDays($staleDays)))->count(),
                'new_enquiries' => Enquiry::where('shop_id', $shop->id)->where('status', 'new')->count(),
                'pending_orders' => (clone $orders)->whereIn('status', [Order::REQUESTED, Order::QUOTED, Order::ACCEPTED])->count(),
                'confirmed_orders' => (clone $orders)->whereIn('status', [Order::CONFIRMED, Order::READY, Order::HANDED_OVER])->count(),
                'completed_orders' => (clone $orders)->where('status', Order::COMPLETED)->count(),
                'cancelled_orders' => (clone $orders)->whereIn('status', [Order::CANCELLED, Order::REJECTED])->count(),
                'monthly_sales' => round((float) (clone $orders)->where('status', Order::COMPLETED)->where('completed_at', '>=', now()->startOfMonth())->sum('total_amount'), 2),
                'rating_avg' => (float) $shop->rating_avg,
                'rating_count' => $shop->rating_count,
            ],
            'action_required' => [
                'orders' => (clone $orders)->whereIn('status', [Order::REQUESTED, Order::ACCEPTED])->with('customer:id,name')->latest()->limit(5)->get(['id', 'reference', 'status', 'customer_id', 'total_amount', 'created_at']),
                'enquiries' => Enquiry::where('shop_id', $shop->id)->where('status', 'new')->with(['customer:id,name', 'product:id,name'])->latest()->limit(5)->get(),
            ],
            'recent_reviews' => Review::where('reviewable_type', 'shop')->where('reviewable_id', $shop->id)->where('status', 'approved')->with('customer')->latest()->limit(5)->get(),
        ]);
    }
}
