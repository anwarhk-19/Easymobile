<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketRequest;
use App\Models\SupportTicket;
use App\Services\SupportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SupportTicketController extends Controller
{
    public function __construct(private SupportService $support) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(SupportTicket::where('user_id', $request->user()->id)->latest()->paginate(20));
    }

    public function store(StoreTicketRequest $request): JsonResponse
    {
        $ticket = $this->support->open($request->user(), $request->validated(), $request->file('attachments', []));

        return response()->json(['ticket' => $ticket, 'message' => 'Complaint registered. Our team will acknowledge it within one business day.'], 201);
    }

    public function show(Request $request, SupportTicket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);
        $ticket->load(['messages' => fn ($m) => $request->user()->isStaff() ? $m : $m->where('is_internal', false), 'messages.user', 'attachments', 'order:id,reference,status', 'repairRequest:id,reference,status', 'assignee']);

        return response()->json(['ticket' => $ticket]);
    }

    public function reply(Request $request, SupportTicket $ticket): JsonResponse
    {
        Gate::authorize('view', $ticket);
        $data = $request->validate(['message' => ['required', 'string', 'max:5000'], 'is_internal' => ['nullable', 'boolean'],
            'attachments' => ['nullable', 'array', 'max:5'], 'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120']]);
        if (in_array($ticket->status, ['closed'], true) && ! $request->user()->isStaff()) {
            return response()->json(['message' => 'This ticket is closed. Please open a new one.'], 422);
        }
        $this->support->reply($ticket, $request->user(), $data['message'], (bool) ($data['is_internal'] ?? false));
        foreach ($request->file('attachments', []) as $file) {
            $this->support->attach($ticket, $request->user(), $file);
        }

        return response()->json(['message' => 'Reply added.'], 201);
    }
}
