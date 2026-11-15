<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommissionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Commission::with(['shop:id,name', 'category:id,name'])->orderBy('scope')->latest()->get());
    }

    public function store(Request $request): JsonResponse
    {
        $c = Commission::create($this->validated($request));
        AuditLogger::log('commission.created', $c, "Created {$c->scope} commission rule ({$c->type} {$c->value})");

        return response()->json(['commission' => $c], 201);
    }

    public function update(Request $request, Commission $commission): JsonResponse
    {
        $before = $commission->only(['type', 'value', 'is_active']);
        $commission->update($this->validated($request));
        AuditLogger::log('commission.updated', $commission, "Updated commission rule #{$commission->id}", ['before' => $before, 'after' => $commission->only(['type', 'value', 'is_active'])]);

        return response()->json(['commission' => $commission]);
    }

    public function destroy(Commission $commission): JsonResponse
    {
        $commission->delete();
        AuditLogger::log('commission.deleted', null, "Deleted commission rule #{$commission->id}");

        return response()->json(['message' => 'Deleted.']);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'scope' => ['required', Rule::in(Commission::SCOPES)],
            'shop_id' => ['required_if:scope,shop', 'nullable', 'integer', 'exists:shops,id'],
            'category_id' => ['required_if:scope,category', 'nullable', 'integer', 'exists:categories,id'],
            'type' => ['required', Rule::in(Commission::TYPES)],
            'value' => ['required_unless:type,free', 'nullable', 'numeric', 'min:0', $request->input('type') === 'percentage' ? 'max:50' : 'max:100000'],
            'is_active' => ['nullable', 'boolean'],
            'effective_from' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
        $data['value'] = $data['type'] === 'free' ? 0 : $data['value'];
        $data['shop_id'] = $data['scope'] === 'shop' ? $data['shop_id'] : null;
        $data['category_id'] = $data['scope'] === 'category' ? $data['category_id'] : null;
        $data['is_active'] = $data['is_active'] ?? true;

        return $data;
    }
}
