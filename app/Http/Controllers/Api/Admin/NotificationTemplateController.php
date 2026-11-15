<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationTemplate;
use App\Services\AuditLogger;
use App\Services\TemplateRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationTemplateController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['templates' => NotificationTemplate::orderBy('channel')->orderBy('key')->get(), 'defaults' => TemplateRenderer::DEFAULTS]);
    }

    public function store(Request $request): JsonResponse
    {
        $t = NotificationTemplate::create($this->validated($request));
        AuditLogger::log('template.created', $t, "Created template {$t->channel}/{$t->key}");

        return response()->json(['template' => $t], 201);
    }

    public function update(Request $request, NotificationTemplate $template): JsonResponse
    {
        $template->update($this->validated($request, $template));
        AuditLogger::log('template.updated', $template, "Updated template {$template->channel}/{$template->key}");

        return response()->json(['template' => $template]);
    }

    public function destroy(NotificationTemplate $template): JsonResponse
    {
        $template->delete();
        AuditLogger::log('template.deleted', null, "Deleted template {$template->channel}/{$template->key}");

        return response()->json(['message' => 'Deleted. The built-in default will be used.']);
    }

    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $sample = ['product_name' => 'boAt Airdopes 141', 'compatible_model' => 'Any Bluetooth phone', 'order_reference' => 'ORD-2611-ABCDEF', 'shop_name' => 'Al-Noor Mobiles',
            'amount' => '1,299.00', 'device' => 'Samsung Galaxy A54', 'problem' => 'Screen cracked', 'repair_reference' => 'REP-2611-QWERTY', 'status_label' => 'Confirmed',
            'technician_name' => 'Imran', 'ticket_reference' => 'TKT-2611-ZXCVBN', 'enquiry_reference' => 'ENQ-2611-ASDFGH'];

        return response()->json(['preview' => TemplateRenderer::interpolate($data['body'], $sample)]);
    }

    private function validated(Request $request, ?NotificationTemplate $t = null): array
    {
        return $request->validate([
            'key' => ['required', 'string', 'max:60', 'regex:/^[a-z_]+$/', Rule::unique('notification_templates')->where('channel', $request->input('channel'))->ignore($t?->id)],
            'channel' => ['required', Rule::in(NotificationTemplate::CHANNELS)],
            'name' => ['required', 'string', 'max:120'],
            'subject' => ['nullable', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
