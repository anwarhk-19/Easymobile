<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Enquiry;
use App\Models\Order;
use App\Models\Shop;
use App\Services\OrderService;
use App\Services\ReviewService;
use App\Services\WhatsAppLinkBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function index(Request $request): JsonResponse
    {
        $q = Order::where('customer_id', $request->user()->id)->with(['shop:id,name,slug', 'items:id,order_id,product_name,quantity'])->latest();
        if ($request->filled('status')) {
            $request->input('status') === 'open' ? $q->whereIn('status', Order::OPEN_STATUSES) : $q->where('status', $request->input('status'));
        }

        return response()->json($q->paginate(20));
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $data = $request->validated();
        $enquiry = isset($data['enquiry_id']) ? Enquiry::where('customer_id', $request->user()->id)->find($data['enquiry_id']) : null;
        $order = $this->orders->createRequest($request->user(), Shop::findOrFail($data['shop_id']), $data['items'], $data, $enquiry);

        return response()->json(['order' => $order->load('items'), 'message' => 'Order request sent. The shop will confirm availability and the final price.'], 201);
    }

    public function show(Request $request, Order $order, WhatsAppLinkBuilder $wa): JsonResponse
    {
        Gate::authorize('actAsCustomer', $order);
        $order->load(['shop:id,name,slug,phone,whatsapp,address,area_id,maps_url,latitude,longitude', 'shop.area:id,name', 'items.product:id,slug,name', 'histories.user', 'payments', 'refunds', 'deliveryArea:id,name']);
        $reviewed = $order->reviews()->where('customer_id', $request->user()->id)->get(['id', 'reviewable_type', 'reviewable_id', 'rating', 'status']);

        return response()->json(['order' => $order, 'reviews' => $reviewed, 'can_review' => $order->status === Order::COMPLETED && $reviewed->isEmpty(), 'whatsapp_link' => $wa->forOrder($order)]);
    }

    public function accept(Order $order, Request $request): JsonResponse
    {
        Gate::authorize('actAsCustomer', $order);

        return response()->json(['order' => $this->orders->accept($order, $request->user())]);
    }

    public function cancel(Order $order, Request $request): JsonResponse
    {
        Gate::authorize('actAsCustomer', $order);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return response()->json(['order' => $this->orders->cancel($order, $request->user(), $data['reason'])]);
    }

    public function confirmReceipt(Order $order, Request $request): JsonResponse
    {
        Gate::authorize('actAsCustomer', $order);

        return response()->json(['order' => $this->orders->confirmReceipt($order, $request->user())]);
    }

    public function review(Order $order, Request $request, ReviewService $reviews): JsonResponse
    {
        Gate::authorize('actAsCustomer', $order);
        $data = $request->validate([
            'shop_rating' => ['required', 'integer', 'between:1,5'],
            'shop_comment' => ['nullable', 'string', 'max:1000'],
            'products' => ['nullable', 'array'],
            'products.*.product_id' => ['required', 'integer'],
            'products.*.rating' => ['required', 'integer', 'between:1,5'],
            'products.*.comment' => ['nullable', 'string', 'max:1000'],
        ]);

        return response()->json(['reviews' => $reviews->forOrder($request->user(), $order->load('items', 'shop'), $data), 'message' => 'Thank you! Your review will appear after moderation.'], 201);
    }
}
