<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Review::with(['customer', 'reviewable', 'order:id,reference', 'repairRequest:id,reference'])->latest();
        if ($request->filled('status')) {
            $q->where('status', $request->input('status'));
        }
        if ($request->filled('type')) {
            $q->where('reviewable_type', $request->input('type'));
        }

        return response()->json($q->paginate(25)->withQueryString()->through(function (Review $r) {
            $r->setAttribute('reviewable_name', $r->reviewable?->name);
            $r->unsetRelation('reviewable');

            return $r;
        }));
    }

    public function moderate(Request $request, Review $review, ReviewService $reviews): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'in:approved,rejected'], 'note' => ['nullable', 'string', 'max:255']]);

        return response()->json(['review' => $reviews->moderate($review, $request->user(), $data['status'], $data['note'] ?? null)]);
    }
}
