<?php

namespace App\Services;

use App\Exceptions\WorkflowException;
use App\Models\Order;
use App\Models\Product;
use App\Models\RepairRequest;
use App\Models\Review;
use App\Models\Setting;
use App\Models\Shop;
use App\Models\TechnicianProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** Reviews are only accepted for completed transactions, once per reviewable per transaction. */
class ReviewService
{
    public function forOrder(User $customer, Order $order, array $data): array
    {
        if ($order->customer_id !== $customer->id || $order->status !== Order::COMPLETED) {
            throw new WorkflowException('You can only review completed orders.');
        }

        return DB::transaction(function () use ($customer, $order, $data) {
            $created = [];
            $created[] = $this->store($customer, $order->shop, $data['shop_rating'], $data['shop_comment'] ?? null, orderId: $order->id);
            foreach ($data['products'] ?? [] as $p) {
                $productIds = $order->items->pluck('product_id')->filter()->all();
                if (! in_array((int) $p['product_id'], $productIds, true)) {
                    throw new WorkflowException('You can only review products included in this order.');
                }
                $created[] = $this->store($customer, Product::withTrashed()->findOrFail($p['product_id']), $p['rating'], $p['comment'] ?? null, orderId: $order->id);
            }

            return $created;
        });
    }

    public function forRepair(User $customer, RepairRequest $repair, array $data): Review
    {
        if ($repair->customer_id !== $customer->id || $repair->status !== RepairRequest::COMPLETED) {
            throw new WorkflowException('You can only review completed repairs.');
        }

        return $this->store($customer, $repair->technician, $data['rating'], $data['comment'] ?? null, repairId: $repair->id);
    }

    public function moderate(Review $review, User $admin, string $status, ?string $note = null): Review
    {
        $review->update(['status' => $status, 'moderated_by' => $admin->id, 'moderated_at' => now(), 'moderation_note' => $note]);
        $this->refreshAggregate($review->reviewable);
        AuditLogger::log('review.'.$status, $review, "Review #{$review->id} {$status}", ['note' => $note]);

        return $review;
    }

    public function refreshAggregate(?Model $reviewable): void
    {
        if (! $reviewable instanceof Shop && ! $reviewable instanceof TechnicianProfile && ! $reviewable instanceof Product) {
            return;
        }
        $q = Review::where('reviewable_type', $reviewable->getMorphClass())->where('reviewable_id', $reviewable->getKey())->where('status', 'approved');
        $reviewable->forceFill(['rating_avg' => round((float) $q->avg('rating'), 2), 'rating_count' => $q->count()])->saveQuietly();
    }

    private function store(User $customer, Model $reviewable, int $rating, ?string $comment, ?int $orderId = null, ?int $repairId = null): Review
    {
        $exists = Review::where('customer_id', $customer->id)
            ->where('reviewable_type', $reviewable->getMorphClass())->where('reviewable_id', $reviewable->getKey())
            ->where($orderId ? 'order_id' : 'repair_request_id', $orderId ?? $repairId)->exists();
        if ($exists) {
            throw new WorkflowException('You have already reviewed this transaction.');
        }
        $review = Review::create([
            'customer_id' => $customer->id,
            'reviewable_type' => $reviewable->getMorphClass(),
            'reviewable_id' => $reviewable->getKey(),
            'order_id' => $orderId,
            'repair_request_id' => $repairId,
            'rating' => $rating,
            'comment' => $comment,
            'status' => Setting::get('reviews_auto_approve') ? 'approved' : 'pending',
        ]);
        $this->refreshAggregate($reviewable);

        return $review;
    }
}
