<?php

namespace App\Http\Controllers\Api\Merchant;

use App\Models\Enquiry;
use App\Services\Notifier;
use App\Services\WhatsAppLinkBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnquiryController extends MerchantController
{
    public function index(Request $request): JsonResponse
    {
        $shop = $this->shop($request);
        $q = Enquiry::where('shop_id', $shop->id)->with(['customer:id,name,phone', 'product:id,name,slug,price,discount_price,stock_quantity,availability', 'phoneModel:id,name', 'order:id,enquiry_id,reference,status'])->latest();
        if ($request->filled('status')) {
            $q->where('status', $request->input('status'));
        }

        return response()->json($q->paginate(20)->through(function ($e) {
            $e->setAttribute('whatsapp_link', app(WhatsAppLinkBuilder::class)->link($e->customer?->phone, "Hello {$e->customer?->name}, this is {$e->shop?->name} replying to your enquiry {$e->reference} on Bhatkal Mobile Hub."));

            return $e;
        }));
    }

    public function reply(Request $request, Enquiry $enquiry, Notifier $notifier): JsonResponse
    {
        abort_unless($enquiry->shop_id === $this->shop($request)->id, 403);
        $data = $request->validate(['reply' => ['required', 'string', 'max:1000'], 'close' => ['nullable', 'boolean']]);
        $enquiry->update([
            'merchant_reply' => $data['reply'],
            'status' => ! empty($data['close']) ? 'closed' : ($enquiry->status === 'converted' ? 'converted' : 'responded'),
            'responded_at' => $enquiry->responded_at ?? now(),
        ]);
        $notifier->notify($enquiry->customer, 'Shop replied to your enquiry', 'order_status', ['order_reference' => $enquiry->reference, 'status_label' => 'answered'], '/enquiries');

        return response()->json(['enquiry' => $enquiry]);
    }
}
