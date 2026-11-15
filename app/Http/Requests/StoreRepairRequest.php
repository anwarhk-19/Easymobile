<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRepairRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'customer';
    }

    public function rules(): array
    {
        return [
            'technician_id' => ['required', 'integer', 'exists:technician_profiles,id'],
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'phone_model_id' => ['nullable', 'integer', Rule::exists('phone_models', 'id')->where('brand_id', $this->input('brand_id'))],
            'device_model_text' => ['required_without:phone_model_id', 'nullable', 'string', 'max:120'],
            'repair_category_id' => ['required', 'integer', 'exists:repair_categories,id'],
            'issue_description' => ['required', 'string', 'min:10', 'max:2000'],
            'service_mode' => ['required', Rule::in(['walk_in', 'pickup_drop', 'onsite'])],
            'preferred_area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'address' => ['required_unless:service_mode,walk_in', 'nullable', 'string', 'max:500'],
            'preferred_time' => ['nullable', 'string', 'max:100'],
            'data_access_consent' => ['nullable', 'boolean'],
            // Optional — only collect when genuinely needed (e.g. warranty / network-lock issues). Stored encrypted.
            'imei' => ['nullable', 'digits_between:14,16'],
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
