<?php

namespace App\Services;

use App\Models\NotificationTemplate;

class TemplateRenderer
{
    /** Built-in fallbacks used when an admin has not configured (or has disabled) a template. */
    public const DEFAULTS = [
        'whatsapp' => [
            'product_enquiry' => 'Hello, I found this product on Bhatkal Mobile Hub. Product: {product_name}. Model: {compatible_model}. Please confirm availability and final price.',
            'shop_enquiry' => 'Hello {shop_name}, I found your shop on Bhatkal Mobile Hub. I would like to know more about your products.',
            'order_confirmation' => 'Your order request {order_reference} has been confirmed by {shop_name}. Product: {product_name}. Total amount: ₹{amount}.',
            'order_followup' => 'Hello {shop_name}, this is about my order request {order_reference} on Bhatkal Mobile Hub.',
            'repair_enquiry' => 'Hello, I found your repair service on Bhatkal Mobile Hub. My device is {device}. Issue: {problem}. Please share the next steps.',
            'repair_followup' => 'Hello {technician_name}, this is about my repair request {repair_reference} ({device}) on Bhatkal Mobile Hub.',
        ],
        'in_app' => [
            'order_status' => 'Order {order_reference} is now {status_label}.',
            'repair_status' => 'Repair {repair_reference} is now {status_label}.',
            'repair_quotation' => 'A quotation of ₹{amount} is ready for repair {repair_reference}. Please approve or reject it.',
            'ticket_update' => 'Support ticket {ticket_reference} was updated: {status_label}.',
            'shop_status' => 'Your shop {shop_name} is now {status_label}.',
            'technician_status' => 'Your technician profile is now {status_label}.',
            'new_enquiry' => 'New enquiry {enquiry_reference} received for {shop_name}.',
            'new_repair_request' => 'New repair request {repair_reference} for {device}.',
        ],
        'sms' => [
            'order_status' => 'Bhatkal Mobile Hub: Order {order_reference} is now {status_label}.',
            'repair_status' => 'Bhatkal Mobile Hub: Repair {repair_reference} is now {status_label}.',
        ],
    ];

    public function render(string $key, string $channel, array $vars): string
    {
        $body = NotificationTemplate::where('key', $key)->where('channel', $channel)->where('is_active', true)->value('body')
            ?? self::DEFAULTS[$channel][$key]
            ?? $key;

        return self::interpolate($body, $vars);
    }

    public static function interpolate(string $body, array $vars): string
    {
        return preg_replace_callback('/\{(\w+)\}/', fn ($m) => array_key_exists($m[1], $vars) ? (string) $vars[$m[1]] : $m[0], $body);
    }
}
