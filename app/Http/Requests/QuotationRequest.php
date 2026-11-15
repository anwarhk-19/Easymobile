<?php

namespace App\Http\Requests;

use App\Models\RepairRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'technician';
    }

    public function rules(): array
    {
        return [
            'diagnosis' => ['required', 'string', 'min:10', 'max:2000'],
            'parts_cost' => ['required', 'numeric', 'min:0', 'max:200000'],
            'labour_cost' => ['required', 'numeric', 'min:0', 'max:100000'],
            'parts_type' => ['required', Rule::in(RepairRequest::PARTS_TYPES)],
            'estimated_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'warranty_days' => ['required', 'integer', 'min:0', 'max:365'],
            'warranty_exclusions' => ['nullable', 'string', 'max:1000'],
            'change_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
