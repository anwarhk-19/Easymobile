<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'customer';
    }

    public function rules(): array
    {
        return [
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'enquiry_id' => ['nullable', 'integer', 'exists:enquiries,id'],
            'items' => ['required', 'array', 'min:1', 'max:10'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'items.*.variant' => ['nullable', 'string', 'max:100'],
            'items.*.colour' => ['nullable', 'string', 'max:60'],
            'items.*.model_note' => ['nullable', 'string', 'max:150'],
            'fulfilment' => ['required', Rule::in(['pickup', 'delivery'])],
            'delivery_address' => ['required_if:fulfilment,delivery', 'nullable', 'string', 'max:500'],
            'delivery_area_id' => ['required_if:fulfilment,delivery', 'nullable', 'integer', 'exists:areas,id'],
            'payment_method' => ['nullable', Rule::in(['cod', 'pickup_payment', 'upi'])],
            'customer_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
