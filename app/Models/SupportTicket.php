<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    public const CATEGORIES = ['wrong_product', 'stock_unavailable', 'delayed_delivery', 'order_cancellation', 'repair_quality',
        'warranty_issue', 'payment_dispute', 'fraud', 'safety_concern', 'privacy_violation', 'seller_misconduct', 'other'];

    /** Categories that are escalated to the administrator automatically. */
    public const ESCALATE_CATEGORIES = ['fraud', 'safety_concern', 'privacy_violation', 'seller_misconduct'];

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    public const STATUSES = ['open', 'acknowledged', 'in_progress', 'escalated', 'resolved', 'closed'];

    protected $guarded = ['id'];

    protected $appends = ['is_overdue'];

    protected function casts(): array
    {
        return [
            'is_escalated' => 'boolean',
            'acknowledge_due_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->select(['id', 'name', 'phone', 'role']);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function repairRequest(): BelongsTo
    {
        return $this->belongsTo(RepairRequest::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->select(['id', 'name', 'role']);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class)->orderBy('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SupportAttachment::class);
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->acknowledged_at === null && $this->acknowledge_due_at !== null && $this->acknowledge_due_at->isPast();
    }
}
