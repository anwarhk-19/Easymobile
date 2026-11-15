<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'merchant' && $this->user()->shop !== null;
    }

    public function rules(): array
    {
        $shopId = $this->user()->shop->id;
        $productId = $this->route('product')?->id;

        return [
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('is_active', true)],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'description' => ['nullable', 'string', 'max:5000'],
            'sku' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9._\-\/]+$/', Rule::unique('products')->where('shop_id', $shopId)->ignore($productId)],
            'price' => ['required', 'numeric', 'min:1', 'max:500000'],
            'discount_price' => ['nullable', 'numeric', 'min:1', 'lt:price'],
            'stock_quantity' => ['required', 'integer', 'min:0', 'max:100000'],
            'availability' => ['required', Rule::in(Product::AVAILABILITY)],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'compatible_model_ids' => ['nullable', 'array', 'max:100'],
            'compatible_model_ids.*' => ['integer', 'exists:phone_models,id'],
            'images' => ['nullable', 'array', 'max:8'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
