<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Models\Order;
use App\Models\Product;
use App\Models\RepairRequest;
use App\Models\Review;
use App\Models\Settlement;
use App\Models\Shop;
use App\Models\SupportTicket;
use App\Models\TechnicianProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $completed = Order::where('status', Order::COMPLETED);
        $pendingSettlement = (float) Settlement::whereIn('status', ['pending', 'processing'])->sum('net_payable')
            + (float) Order::where('status', Order::COMPLETED)->whereNull('settlement_id')->sum('merchant_payable');

        return response()->json([
            'stats' => [
                'customers' => User::where('role', 'customer')->count(),
                'merchants' => User::where('role', 'merchant')->count(),
                'active_shops' => Shop::where('status', Shop::STATUS_APPROVED)->count(),
                'pending_shop_approvals' => Shop::where('status', Shop::STATUS_PENDING)->count(),
                'technicians' => TechnicianProfile::count(),
                'pending_technician_approvals' => TechnicianProfile::where('status', TechnicianProfile::STATUS_PENDING)->count(),
                'products' => Product::count(),
                'active_listings' => Product::visible()->count(),
                'enquiries' => Enquiry::count(),
                'orders' => Order::count(),
                'completed_orders' => (clone $completed)->count(),
                'cancelled_orders' => Order::whereIn('status', [Order::CANCELLED, Order::REJECTED])->count(),
                'repair_requests' => RepairRequest::count(),
                'completed_repairs' => RepairRequest::where('status', RepairRequest::COMPLETED)->count(),
                'marketplace_revenue' => round((float) (clone $completed)->sum('total_amount'), 2),
                'platform_commission' => round((float) (clone $completed)->sum('commission_amount'), 2),
                'pending_merchant_settlements' => round($pendingSettlement, 2),
                'open_complaints' => SupportTicket::whereNotIn('status', ['resolved', 'closed'])->count(),
                'escalated_complaints' => SupportTicket::where('is_escalated', true)->whereNotIn('status', ['resolved', 'closed'])->count(),
                'pending_reviews' => Review::where('status', 'pending')->count(),
            ],
            'queues' => [
                'shops' => Shop::where('status', Shop::STATUS_PENDING)->with('area:id,name')->oldest()->limit(5)->get(['id', 'name', 'area_id', 'created_at']),
                'technicians' => TechnicianProfile::where('status', TechnicianProfile::STATUS_PENDING)->oldest()->limit(5)->get(['id', 'name', 'shop_name', 'created_at']),
                'tickets' => SupportTicket::whereNotIn('status', ['resolved', 'closed'])->orderByDesc('is_escalated')->oldest()->limit(5)->get(['id', 'reference', 'subject', 'priority', 'status', 'is_escalated', 'acknowledge_due_at', 'acknowledged_at', 'created_at']),
            ],
            'recent_orders' => Order::with('shop:id,name', 'customer:id,name')->latest()->limit(8)->get(['id', 'reference', 'status', 'total_amount', 'shop_id', 'customer_id', 'created_at']),
        ]);
    }
}
