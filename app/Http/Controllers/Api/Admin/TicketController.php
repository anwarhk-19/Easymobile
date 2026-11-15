<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\SupportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = SupportTicket::with(['user', 'assignee'])->orderByDesc('is_escalated')->latest();
        foreach (['status', 'category', 'priority'] as $f) {
            if ($request->filled($f)) {
                $q->where($f, $request->input($f));
            }
        }
        if ($request->input('assigned') === 'me') {
            $q->where('assigned_to', $request->user()->id);
        }
        if ($request->boolean('escalated')) {
            $q->where('is_escalated', true);
        }
        if ($request->boolean('overdue')) {
            $q->whereNull('acknowledged_at')->where('acknowledge_due_at', '<', now());
        }
        if ($request->filled('q')) {
            $like = '%'.$request->input('q').'%';
            $q->where(fn ($s) => $s->where('reference', 'like', $like)->orWhere('subject', 'like', $like));
        }

        return response()->json($q->paginate(25)->withQueryString());
    }

    public function update(Request $request, SupportTicket $ticket, SupportService $support): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(SupportTicket::STATUSES)],
            'priority' => ['nullable', Rule::in(SupportTicket::PRIORITIES)],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->whereIn('role', ['admin', 'support'])],
            'resolution_notes' => ['required_if:status,resolved', 'nullable', 'string', 'max:5000'],
            'escalate' => ['nullable', 'boolean'],
            'escalation_reason' => ['required_if:escalate,true', 'nullable', 'string', 'max:255'],
        ]);

        return response()->json(['ticket' => $support->update($ticket, $request->user(), array_filter($data, fn ($v) => $v !== null))]);
    }

    public function staff(): JsonResponse
    {
        return response()->json(User::whereIn('role', ['admin', 'support'])->where('status', 'active')->get(['id', 'name', 'role']));
    }
}
