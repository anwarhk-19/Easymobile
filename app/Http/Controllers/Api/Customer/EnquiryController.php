<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Models\Product;
use App\Models\Shop;
use App\Services\Notifier;
use App\Services\WhatsAppLinkBuilder;
use App\Support\Reference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EnquiryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(Enquiry::where('customer_id', $request->user()->id)
            ->with(['shop:id,name,slug,whatsapp,phone', 'product:id,name,slug', 'phoneModel:id,name', 'order:id,enquiry_id,reference,status'])->latest()->paginate(20));
    }

    public function store(Request $request, Notifier $notifier, WhatsAppLinkBuilder $wa): JsonResponse
    {
        $data = $request->validate([
            'shop_id' => ['required_without:product_id', 'nullable', 'integer'],
            'product_id' => ['nullable', 'integer'],
            'phone_model_id' => ['nullable', 'integer', 'exists:phone_models,id'],
            'message' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $product = isset($data['product_id']) ? Product::visible()->find($data['product_id']) : null;
        if (isset($data['product_id']) && ! $product) {
            throw ValidationException::withMessages(['product_id' => 'This product is no longer available.']);
        }
        $shop = Shop::published()->find($product?->shop_id ?? $data['shop_id'])
            ?? throw ValidationException::withMessages(['shop_id' => 'This shop is not available.']);

        $enquiry = Enquiry::create([
            'reference' => Reference::make('ENQ', Enquiry::class),
            'customer_id' => $request->user()->id,
            'shop_id' => $shop->id,
            'product_id' => $product?->id,
            'phone_model_id' => $data['phone_model_id'] ?? null,
            'message' => $data['message'],
            'status' => 'new',
        ]);
        $notifier->notify($shop->merchant, 'New enquiry', 'new_enquiry', ['enquiry_reference' => $enquiry->reference, 'shop_name' => $shop->name], '/merchant/enquiries');

        return response()->json([
            'enquiry' => $enquiry,
            'whatsapp_link' => $product ? $wa->forProduct($product, $enquiry->phoneModel) : $wa->forShop($shop),
        ], 201);
    }
}
