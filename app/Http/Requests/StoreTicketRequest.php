<?php

namespace App\Http\Requests;

use App\Models\SupportTicket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(SupportTicket::CATEGORIES)],
            'subject' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'order_id' => ['nullable', 'integer', Rule::exists('orders', 'id')->where('customer_id', $this->user()->id)],
            'repair_request_id' => ['nullable', 'integer', Rule::exists('repair_requests', 'id')->where('customer_id', $this->user()->id)],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high'])],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }
}
