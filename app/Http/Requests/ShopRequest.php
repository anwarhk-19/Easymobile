<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'merchant';
    }

    public function rules(): array
    {
        $creating = $this->isMethod('post') && ! $this->user()->shop;

        return [
            'name' => ['required', 'string', 'max:120'],
            'owner_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'regex:/^(\+?91)?[6-9]\d{9}$/'],
            'whatsapp' => ['nullable', 'regex:/^(\+?91)?[6-9]\d{9}$/'],
            'address' => ['required', 'string', 'max:500'],
            'area_id' => ['required', 'integer', 'exists:areas,id'],
            'maps_url' => ['nullable', 'url', 'max:500', 'regex:/^https:\/\/(www\.)?(google\.[a-z.]+\/maps|maps\.google\.[a-z.]+|maps\.app\.goo\.gl|goo\.gl\/maps)/i'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'opens_at' => ['required', 'date_format:H:i'],
            'closes_at' => ['required', 'date_format:H:i'],
            'open_days' => ['nullable', 'array'],
            'open_days.*' => ['in:mon,tue,wed,thu,fri,sat,sun'],
            'description' => ['nullable', 'string', 'max:2000'],
            'pickup_available' => ['nullable', 'boolean'],
            'delivery_available' => ['nullable', 'boolean'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'delivery_notes' => ['nullable', 'string', 'max:255'],
            'gstin' => ['nullable', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'photos' => ['nullable', 'array', 'max:6'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'documents' => [$creating ? 'required' : 'nullable', 'array', 'max:5'],
            'documents.*.type' => ['required', 'in:trade_licence,gst_certificate,shop_establishment,id_proof,address_proof,other'],
            'documents.*.file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'accept_agreement' => [$creating ? 'accepted' : 'nullable'],
        ];
    }

    public function messages(): array
    {
        return ['maps_url.regex' => 'Please paste a Google Maps link for your shop.', 'documents.required' => 'Upload at least one business verification document.'];
    }
}
