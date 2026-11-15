<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public const REQUESTED = 'requested';         // customer order request — NOT a confirmed order

    public const QUOTED = 'quoted';               // merchant confirmed model/variant/colour/price/delivery

    public const ACCEPTED = 'accepted';           // customer accepted the final quotation

    public const CONFIRMED = 'confirmed';         // merchant confirmed the order

    public const READY = 'ready';                 // ready for pickup / out for delivery

    public const HANDED_OVER = 'handed_over';     // delivered or collected

    public const COMPLETED = 'completed';         // customer confirmed receipt

    public const CANCELLED = 'cancelled';

    public const REJECTED = 'rejected';           // merchant could not fulfil (e.g. unavailable)

    public const OPEN_STATUSES = [self::REQUESTED, self::QUOTED, self::ACCEPTED, self::CONFIRMED, self::READY, self::HANDED_OVER];

    public const PAYMENT_METHODS = ['cod', 'pickup_payment', 'upi'];

    public const PAYMENT_STATUSES = ['pending', 'paid', 'partially_refunded', 'refunded'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        $money = array_fill_keys(['product_amount', 'delivery_fee', 'total_amount', 'commission_rate', 'commission_amount',
            'adjustment_amount', 'refund_amount', 'merchant_payable'], 'decimal:2');
        $dates = array_fill_keys(['quoted_at', 'accepted_at', 'confirmed_at', 'ready_at', 'handed_over_at', 'completed_at', 'cancelled_at'], 'datetime');

        return $money + $dates;
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class)->withTrashed();
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function deliveryArea(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'delivery_area_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }
}
