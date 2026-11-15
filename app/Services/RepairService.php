<?php

namespace App\Services;

use App\Exceptions\WorkflowException;
use App\Models\RepairQuotation;
use App\Models\RepairRequest;
use App\Models\RepairStatusHistory;
use App\Models\TechnicianProfile;
use App\Models\User;
use App\Support\Reference;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Repair workflow. Work never starts without an explicitly approved quotation, and any change to the
 * quotation puts the job back to "quoted" until the customer approves again.
 */
class RepairService
{
    public function __construct(private Notifier $notifier) {}

    public function create(User $customer, TechnicianProfile $tech, array $data): RepairRequest
    {
        if (! $tech->isPublished()) {
            throw new WorkflowException('This technician is not currently accepting requests.');
        }
        if (($data['service_mode'] ?? 'walk_in') === 'pickup_drop' && ! $tech->pickup_available) {
            throw new WorkflowException('This technician does not offer pickup/drop-off.');
        }
        if (($data['service_mode'] ?? 'walk_in') === 'onsite' && ! $tech->onsite_available) {
            throw new WorkflowException('This technician does not offer on-site service.');
        }

        return DB::transaction(function () use ($customer, $tech, $data) {
            $repair = RepairRequest::create([
                'reference' => Reference::make('REP', RepairRequest::class),
                'customer_id' => $customer->id,
                'technician_profile_id' => $tech->id,
                'brand_id' => $data['brand_id'],
                'phone_model_id' => $data['phone_model_id'] ?? null,
                'device_model_text' => $data['device_model_text'] ?? null,
                'repair_category_id' => $data['repair_category_id'],
                'issue_description' => $data['issue_description'],
                'service_mode' => $data['service_mode'] ?? 'walk_in',
                'preferred_area_id' => $data['preferred_area_id'] ?? null,
                'address' => $data['address'] ?? null,
                'preferred_time' => $data['preferred_time'] ?? null,
                'data_access_consent' => (bool) ($data['data_access_consent'] ?? false),
                'imei_encrypted' => $data['imei'] ?? null,
                'status' => RepairRequest::SUBMITTED,
            ]);
            $this->history($repair, null, RepairRequest::SUBMITTED, $customer, 'Repair request submitted.');
            $repair->load('brand', 'phoneModel');
            $this->notifier->notify($tech->user, 'New repair request', 'new_repair_request',
                ['repair_reference' => $repair->reference, 'device' => $repair->deviceLabel()], '/technician/repairs/'.$repair->id);

            return $repair;
        });
    }

    public function accept(RepairRequest $r, User $tech): RepairRequest
    {
        $this->assertStatus($r, [RepairRequest::SUBMITTED], 'accept');

        return $this->transition($r, RepairRequest::ACCEPTED, $tech, 'Technician accepted the request.', ['accepted_at' => now()]);
    }

    public function reject(RepairRequest $r, User $tech, string $reason): RepairRequest
    {
        $this->assertStatus($r, [RepairRequest::SUBMITTED], 'reject');

        return $this->transition($r, RepairRequest::REJECTED, $tech, 'Technician declined: '.$reason, ['rejection_reason' => $reason]);
    }

    public function recordDiagnosis(RepairRequest $r, User $tech, string $findings): RepairRequest
    {
        $this->assertStatus($r, [RepairRequest::ACCEPTED, RepairRequest::DIAGNOSING, RepairRequest::QUOTE_REJECTED, RepairRequest::IN_PROGRESS], 'record a diagnosis for');

        return DB::transaction(function () use ($r, $tech, $findings) {
            $r->diagnoses()->create(['findings' => $findings, 'recorded_by' => $tech->id]);
            if (in_array($r->status, [RepairRequest::ACCEPTED, RepairRequest::QUOTE_REJECTED], true)) {
                return $this->transition($r, RepairRequest::DIAGNOSING, $tech, 'Diagnosis recorded: '.Str::limit($findings, 200), ['diagnosed_at' => now()]);
            }
            $this->history($r, $r->status, $r->status, $tech, 'Diagnosis note: '.Str::limit($findings, 200));

            return $r;
        });
    }

    public function submitQuotation(RepairRequest $r, User $tech, array $data): RepairQuotation
    {
        $isRevision = in_array($r->status, [RepairRequest::APPROVED, RepairRequest::IN_PROGRESS, RepairRequest::TESTING], true);
        $this->assertStatus($r, [RepairRequest::ACCEPTED, RepairRequest::DIAGNOSING, RepairRequest::QUOTED, RepairRequest::QUOTE_REJECTED,
            RepairRequest::APPROVED, RepairRequest::IN_PROGRESS, RepairRequest::TESTING], 'quote');
        if ($isRevision && empty($data['change_reason'])) {
            throw new WorkflowException('Please explain why the quotation has changed.');
        }

        return DB::transaction(function () use ($r, $tech, $data, $isRevision) {
            $r->quotations()->where('status', 'pending')->update(['status' => 'superseded']);
            $version = (int) $r->quotations()->max('version') + 1;
            $total = round((float) $data['parts_cost'] + (float) $data['labour_cost'], 2);
            $quote = $r->quotations()->create([
                'version' => $version,
                'diagnosis' => $data['diagnosis'],
                'parts_cost' => $data['parts_cost'],
                'labour_cost' => $data['labour_cost'],
                'parts_type' => $data['parts_type'],
                'total' => $total,
                'estimated_hours' => $data['estimated_hours'],
                'warranty_days' => $data['warranty_days'] ?? 0,
                'warranty_exclusions' => $data['warranty_exclusions'] ?? null,
                'change_reason' => $data['change_reason'] ?? null,
                'status' => 'pending',
            ]);
            $note = $isRevision
                ? "Revised quotation v{$version} (₹".number_format($total, 2).'). Work paused until the customer approves. Reason: '.$data['change_reason']
                : "Quotation v{$version} submitted: ₹".number_format($total, 2);
            $this->transition($r, RepairRequest::QUOTED, $tech, $note, ['quoted_at' => now()], notify: false);
            $this->notifier->notify($r->customer, $isRevision ? 'Revised repair quotation' : 'Repair quotation ready', 'repair_quotation',
                ['repair_reference' => $r->reference, 'amount' => number_format($total, 2)], '/repairs/'.$r->id, sms: true);

            return $quote;
        });
    }

    public function respondToQuotation(RepairRequest $r, User $customer, bool $approve, ?string $note = null): RepairRequest
    {
        $this->assertStatus($r, [RepairRequest::QUOTED], 'respond to the quotation of');
        $quote = $r->pendingQuotation()->first() ?? throw new WorkflowException('There is no pending quotation.');

        return DB::transaction(function () use ($r, $customer, $approve, $note, $quote) {
            if ($approve) {
                $r->quotations()->where('status', 'approved')->update(['status' => 'superseded']);
            }
            $quote->update(['status' => $approve ? 'approved' : 'rejected', 'responded_at' => now(), 'customer_note' => $note]);
            // If work had already started, approval of a revision resumes it.
            $resume = $r->started_at !== null;
            $to = $approve ? ($resume ? RepairRequest::IN_PROGRESS : RepairRequest::APPROVED) : RepairRequest::QUOTE_REJECTED;
            $msg = $approve ? "Customer approved quotation v{$quote->version} (₹".number_format((float) $quote->total, 2).').' : "Customer rejected quotation v{$quote->version}.";

            return $this->transition($r, $to, $customer, $msg.($note ? ' Note: '.$note : ''), $approve ? ['approved_at' => now()] : [], notifyTechnician: true);
        });
    }

    public function start(RepairRequest $r, User $tech): RepairRequest
    {
        $this->assertStatus($r, [RepairRequest::APPROVED], 'start');
        if (! $r->approvedQuotation()->exists()) {
            throw new WorkflowException('Repair cannot start without an approved quotation.');
        }

        return $this->transition($r, RepairRequest::IN_PROGRESS, $tech, 'Repair work started.', ['started_at' => now()]);
    }

    public function addProgress(RepairRequest $r, User $tech, string $note): RepairRequest
    {
        $this->assertStatus($r, [RepairRequest::IN_PROGRESS, RepairRequest::TESTING], 'add progress to');
        $this->history($r, $r->status, $r->status, $tech, $note);
        $this->notifier->notify($r->customer, 'Repair progress', 'repair_status', ['repair_reference' => $r->reference, 'status_label' => $note], '/repairs/'.$r->id);

        return $r;
    }

    public function addPart(RepairRequest $r, User $tech, array $data)
    {
        $this->assertStatus($r, [RepairRequest::APPROVED, RepairRequest::IN_PROGRESS, RepairRequest::TESTING], 'record parts for');
        $part = $r->parts()->create($data);
        $this->history($r, $r->status, $r->status, $tech, "Part used: {$part->name} ×{$part->quantity} (".str_replace('_', ' ', $part->parts_type).')');

        return $part;
    }

    /** @param array<string,bool> $checklist e.g. ['display' => true, 'touch' => true, 'charging' => false] */
    public function startTesting(RepairRequest $r, User $tech): RepairRequest
    {
        $this->assertStatus($r, [RepairRequest::IN_PROGRESS], 'start testing');

        return $this->transition($r, RepairRequest::TESTING, $tech, 'Functional testing in progress.', ['testing_at' => now()]);
    }

    public function completeTesting(RepairRequest $r, User $tech, array $checklist, bool $passed, ?string $notes = null): RepairRequest
    {
        $this->assertStatus($r, [RepairRequest::TESTING], 'complete testing for');
        if (! $passed) {
            return $this->transition($r, RepairRequest::IN_PROGRESS, $tech, 'Functional test failed — back to repair. '.($notes ?? ''), ['test_checklist' => $checklist]);
        }

        return $this->transition($r, RepairRequest::READY, $tech, 'Functional testing passed. Device ready for handover. '.($notes ?? ''),
            ['test_checklist' => $checklist, 'ready_at' => now(), 'technician_notes' => $notes]);
    }

    public function confirmHandover(RepairRequest $r, User $customer, ?string $note = null): RepairRequest
    {
        $this->assertStatus($r, [RepairRequest::READY], 'confirm handover for');
        $r->update(['handover_confirmed_at' => now()]);
        $this->history($r, $r->status, $r->status, $customer, 'Customer checked the device at handover.'.($note ? ' '.$note : ''));
        $this->notifier->notify($r->technician->user, 'Handover checked', 'repair_status', ['repair_reference' => $r->reference, 'status_label' => 'checked by customer'], '/technician/repairs/'.$r->id);

        return $r;
    }

    public function complete(RepairRequest $r, User $tech, array $data): RepairRequest
    {
        $this->assertStatus($r, [RepairRequest::READY], 'complete');
        if (! $r->handover_confirmed_at && empty($data['customer_verified_in_person'])) {
            throw new WorkflowException('The customer must check the device at handover before the job is completed.');
        }
        $quote = $r->approvedQuotation()->first() ?? throw new WorkflowException('No approved quotation found.');

        return DB::transaction(function () use ($r, $tech, $data, $quote) {
            $days = (int) $quote->warranty_days;
            if ($days > 0) {
                $r->warranty()->create([
                    'warranty_code' => Reference::make('WAR', \App\Models\RepairWarranty::class, 'warranty_code'),
                    'starts_at' => now()->toDateString(),
                    'ends_at' => now()->addDays($days)->toDateString(),
                    'terms' => $r->technician->warranty_terms,
                    'exclusions' => $quote->warranty_exclusions,
                ]);
            }

            return $this->transition($r, RepairRequest::COMPLETED, $tech, 'Repair completed. Final bill ₹'.number_format((float) $quote->total, 2).($days ? " with {$days}-day warranty." : '.'), [
                'completed_at' => now(),
                'handover_confirmed_at' => $r->handover_confirmed_at ?? now(),
                'final_amount' => $quote->total,
                'payment_method' => $data['payment_method'] ?? null,
                'payment_status' => ! empty($data['payment_method']) ? 'paid' : 'pending',
            ]);
        });
    }

    public function cancel(RepairRequest $r, User $actor, string $reason): RepairRequest
    {
        $allowed = $actor->isStaff()
            ? RepairRequest::OPEN_STATUSES
            : [RepairRequest::SUBMITTED, RepairRequest::ACCEPTED, RepairRequest::DIAGNOSING, RepairRequest::QUOTED, RepairRequest::QUOTE_REJECTED];
        $this->assertStatus($r, $allowed, 'cancel');

        return $this->transition($r, RepairRequest::CANCELLED, $actor, 'Cancelled: '.$reason, ['cancelled_at' => now(), 'cancel_reason' => $reason],
            notifyTechnician: $actor->id === $r->customer_id);
    }

    public function history(RepairRequest $r, ?string $from, string $to, ?User $user, ?string $note = null): void
    {
        RepairStatusHistory::create(['repair_request_id' => $r->id, 'from_status' => $from, 'to_status' => $to, 'user_id' => $user?->id, 'note' => $note]);
    }

    private function transition(RepairRequest $r, string $to, User $actor, string $note, array $attrs = [], bool $notify = true, bool $notifyTechnician = false): RepairRequest
    {
        return DB::transaction(function () use ($r, $to, $actor, $note, $attrs, $notify, $notifyTechnician) {
            $from = $r->status;
            $r->fill($attrs);
            $r->status = $to;
            $r->save();
            $this->history($r, $from, $to, $actor, $note);
            if ($notify) {
                $vars = ['repair_reference' => $r->reference, 'status_label' => Notifier::label($to)];
                $notifyTechnician
                    ? $this->notifier->notify($r->technician->user, 'Repair update', 'repair_status', $vars, '/technician/repairs/'.$r->id)
                    : $this->notifier->notify($r->customer, 'Repair update', 'repair_status', $vars, '/repairs/'.$r->id, sms: true);
            }

            return $r;
        });
    }

    private function assertStatus(RepairRequest $r, array $allowed, string $action): void
    {
        if (! in_array($r->status, $allowed, true)) {
            throw new WorkflowException("Cannot {$action} a repair that is ".Notifier::label($r->status).'.');
        }
    }
}
