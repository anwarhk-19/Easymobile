<?php

namespace App\Http\Controllers\Api\Merchant;

use App\Models\Settlement;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends MerchantController
{
    public function index(Request $request, ReportService $reports): JsonResponse
    {
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        [$from, $to] = ReportService::range($request->input('from'), $request->input('to'));

        return response()->json($reports->merchant($this->shop($request), $from, $to));
    }

    public function settlements(Request $request): JsonResponse
    {
        return response()->json(Settlement::where('shop_id', $this->shop($request)->id)->latest()->paginate(20));
    }
}
