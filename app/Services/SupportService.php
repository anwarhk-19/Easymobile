<?php

namespace App\Services;

use App\Models\Role;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\BusinessDays;
use App\Support\Reference;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class SupportService
{
    public function __construct(private Notifier $notifier, private FileStore $files) {}

    /** @param array<int, UploadedFile> $attachments */
    public function open(User $user, array $data, array $attachments = []): SupportTicket
    {
        return DB::transaction(function () use ($user, $data, $attachments) {
            $escalate = in_array($data['category'], SupportTicket::ESCALATE_CATEGORIES, true);
            $ticket = SupportTicket::create([
                'reference' => Reference::make('TKT', SupportTicket::class),
                'user_id' => $user->id,
                'order_id' => $data['order_id'] ?? null,
                'repair_request_id' => $data['repair_request_id'] ?? null,
                'category' => $data['category'],
                'subject' => $data['subject'],
                'description' => $data['description'],
                'priority' => $escalate ? 'urgent' : ($data['priority'] ?? 'normal'),
                'status' => $escalate ? 'escalated' : 'open',
                'is_escalated' => $escalate,
                'escalation_reason' => $escalate ? 'Auto-escalated: '.str_replace('_', ' ', $data['category']) : null,
                // Pilot SLA: acknowledge within one business day.
                'acknowledge_due_at' => BusinessDays::add(now(), 1),
            ]);
            foreach ($attachments as $file) {
                $this->attach($ticket, $user, $file);
            }
            if ($escalate) {
                $this->notifyAdmins($ticket, 'Escalated complaint');
            }

            return $ticket;
        });
    }

    public function attach(SupportTicket $ticket, User $user, UploadedFile $file): void
    {
        $ticket->attachments()->create([
            'uploaded_by' => $user->id,
            'path' => $this->files->storePrivate($file, 'tickets/'.$ticket->id),
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 200),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
    }

    public function reply(SupportTicket $ticket, User $user, string $message, bool $internal = false): void
    {
        $ticket->messages()->create(['user_id' => $user->id, 'message' => $message, 'is_internal' => $internal && $user->isStaff()]);
        if ($user->isStaff() && ! $ticket->acknowledged_at) {
            $ticket->update(['acknowledged_at' => now(), 'status' => $ticket->status === 'open' ? 'acknowledged' : $ticket->status]);
        }
        if ($user->isStaff() && ! $internal) {
            $this->notifier->notify($ticket->user()->first(), 'Support reply', 'ticket_update', ['ticket_reference' => $ticket->reference, 'status_label' => 'new reply'], '/support/'.$ticket->id);
        }
    }

    public function update(SupportTicket $ticket, User $staff, array $data): SupportTicket
    {
        $before = $ticket->only(['status', 'priority', 'assigned_to', 'is_escalated']);
        $ticket->fill(array_intersect_key($data, array_flip(['status', 'priority', 'assigned_to', 'resolution_notes'])));
        if (! empty($data['escalate'])) {
            $ticket->is_escalated = true;
            $ticket->status = 'escalated';
            $ticket->escalation_reason = $data['escalation_reason'] ?? 'Escalated by support';
            $this->notifyAdmins($ticket, 'Complaint escalated');
        }
        if (! $ticket->acknowledged_at && $ticket->status !== 'open') {
            $ticket->acknowledged_at = now();
        }
        if (in_array($ticket->status, ['resolved', 'closed'], true) && ! $ticket->resolved_at) {
            $ticket->resolved_at = now();
        }
        $ticket->save();
        AuditLogger::log('ticket.updated', $ticket, "Ticket {$ticket->reference} updated", ['before' => $before, 'after' => $ticket->only(array_keys($before))]);
        $this->notifier->notify($ticket->user()->first(), 'Ticket update', 'ticket_update', ['ticket_reference' => $ticket->reference, 'status_label' => Notifier::label($ticket->status)], '/support/'.$ticket->id);

        return $ticket;
    }

    /** Scheduled: flag tickets not acknowledged within the SLA and alert administrators. */
    public function escalateOverdue(): int
    {
        $count = 0;
        SupportTicket::whereNull('acknowledged_at')->where('is_escalated', false)->where('acknowledge_due_at', '<', now())
            ->each(function (SupportTicket $t) use (&$count) {
                $t->update(['is_escalated' => true, 'priority' => 'high', 'escalation_reason' => 'Not acknowledged within one business day']);
                $this->notifyAdmins($t, 'Ticket SLA breached');
                $count++;
            });

        return $count;
    }

    private function notifyAdmins(SupportTicket $ticket, string $title): void
    {
        User::where('role', Role::ADMIN)->where('status', 'active')->each(fn (User $a) => $this->notifier->notify($a, $title, 'ticket_update',
            ['ticket_reference' => $ticket->reference, 'status_label' => $ticket->escalation_reason ?? 'escalated'], '/admin/tickets/'.$ticket->id));
    }
}
