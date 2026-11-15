<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TechnicianProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'technician';
    }

    public function rules(): array
    {
        $creating = ! $this->user()->technicianProfile;

        return [
            'name' => ['required', 'string', 'max:100'],
            'shop_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['required', 'regex:/^(\+?91)?[6-9]\d{9}$/'],
            'whatsapp' => ['nullable', 'regex:/^(\+?91)?[6-9]\d{9}$/'],
            'address' => ['required', 'string', 'max:500'],
            'area_id' => ['required', 'integer', 'exists:areas,id'],
            'service_area_ids' => ['required', 'array', 'min:1'],
            'service_area_ids.*' => ['integer', 'exists:areas,id'],
            'brand_ids' => ['required', 'array', 'min:1'],
            'brand_ids.*' => ['integer', 'exists:brands,id'],
            'phone_model_ids' => ['nullable', 'array'],
            'phone_model_ids.*' => ['integer', 'exists:phone_models,id'],
            'supported_models_note' => ['nullable', 'string', 'max:1000'],
            'repair_category_ids' => ['required', 'array', 'min:1'],
            'repair_category_ids.*' => ['integer', 'exists:repair_categories,id'],
            'experience_years' => ['required', 'integer', 'min:0', 'max:60'],
            'certifications' => ['nullable', 'string', 'max:1000'],
            'opens_at' => ['required', 'date_format:H:i'],
            'closes_at' => ['required', 'date_format:H:i'],
            'open_days' => ['nullable', 'array'],
            'open_days.*' => ['in:mon,tue,wed,thu,fri,sat,sun'],
            'pickup_available' => ['nullable', 'boolean'],
            'onsite_available' => ['nullable', 'boolean'],
            'default_warranty_days' => ['required', 'integer', 'min:0', 'max:365'],
            'warranty_terms' => ['required', 'string', 'max:2000'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'documents' => ['nullable', 'array', 'max:5'],
            'documents.*.type' => ['required', 'in:id_proof,certificate,shop_licence,address_proof,other'],
            'documents.*.file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'accept_agreement' => [$creating ? 'accepted' : 'nullable'],
        ];
    }
}
