<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PhoneModel;
use App\Models\Product;
use App\Models\RepairRequest;
use App\Models\Shop;
use App\Models\TechnicianProfile;
use App\Support\Phone;

/**
 * Builds WhatsApp click-to-chat links (wa.me) with prefilled messages.
 * NOTE: click-to-chat only opens WhatsApp on the user's device; it does NOT deliver automated messages.
 * Automated delivery requires an approved WhatsApp Business API provider (Phase 3).
 */
class WhatsAppLinkBuilder
{
    public function __construct(private TemplateRenderer $renderer) {}

    public function link(?string $phone, string $message): ?string
    {
        $number = Phone::whatsapp($phone);

        return $number ? 'https://wa.me/'.$number.'?text='.rawurlencode($message) : null;
    }

    public function forProduct(Product $product, ?PhoneModel $model = null): ?string
    {
        $model ??= $product->relationLoaded('compatibleModels') ? $product->compatibleModels->first() : $product->compatibleModels()->first();
        $text = $this->renderer->render('product_enquiry', 'whatsapp', [
            'product_name' => $product->name,
            'compatible_model' => $model ? trim(($model->brand?->name ?? '').' '.$model->name) : 'Not specified',
            'shop_name' => $product->shop?->name,
            'price' => $product->effective_price,
        ]);

        return $this->link($product->shop?->whatsapp ?: $product->shop?->phone, $text);
    }

    public function forShop(Shop $shop): ?string
    {
        return $this->link($shop->whatsapp ?: $shop->phone, $this->renderer->render('shop_enquiry', 'whatsapp', ['shop_name' => $shop->name]));
    }

    public function forTechnician(TechnicianProfile $tech, ?string $device = null, ?string $problem = null): ?string
    {
        return $this->link($tech->whatsapp ?: $tech->phone, $this->renderer->render('repair_enquiry', 'whatsapp', [
            'device' => $device ?: 'my phone',
            'problem' => $problem ?: 'Please advise',
            'technician_name' => $tech->name,
        ]));
    }

    public function forOrder(Order $order): ?string
    {
        $order->loadMissing('shop', 'items');

        return $this->link($order->shop->whatsapp ?: $order->shop->phone, $this->renderer->render('order_followup', 'whatsapp', [
            'shop_name' => $order->shop->name,
            'order_reference' => $order->reference,
        ]));
    }

    /** Link a merchant can use to send the confirmation message to the customer. */
    public function orderConfirmationToCustomer(Order $order): ?string
    {
        $order->loadMissing('shop', 'items', 'customer');

        return $this->link($order->customer->phone, $this->renderer->render('order_confirmation', 'whatsapp', [
            'order_reference' => $order->reference,
            'shop_name' => $order->shop->name,
            'product_name' => $order->items->pluck('product_name')->join(', '),
            'amount' => number_format((float) $order->total_amount, 2),
        ]));
    }

    public function forRepair(RepairRequest $repair): ?string
    {
        $repair->loadMissing('technician', 'brand', 'phoneModel');

        return $this->link($repair->technician->whatsapp ?: $repair->technician->phone, $this->renderer->render('repair_followup', 'whatsapp', [
            'technician_name' => $repair->technician->name,
            'repair_reference' => $repair->reference,
            'device' => $repair->deviceLabel(),
        ]));
    }
}
