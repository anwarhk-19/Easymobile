<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\SearchLog;
use App\Models\Setting;
use App\Services\AuditLogger;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SystemController extends Controller
{
    public function reports(Request $request, ReportService $reports): JsonResponse
    {
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'], 'period' => ['nullable', 'in:day,week,month']]);
        [$from, $to] = ReportService::range($request->input('from'), $request->input('to'));

        return response()->json($reports->admin($from, $to, $request->input('period', 'day')));
    }

    public function settings(): JsonResponse
    {
        return response()->json(['settings' => Setting::allWithDefaults()]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'platform_name' => ['sometimes', 'string', 'max:80'],
            'support_phone' => ['sometimes', 'regex:/^(91)?[6-9]\d{9}$/'],
            'support_email' => ['sometimes', 'email'],
            'reviews_auto_approve' => ['sometimes', 'boolean'],
            'stock_stale_days' => ['sometimes', 'integer', 'between:1,60'],
            'ticket_ack_business_hours' => ['sometimes', 'integer', 'between:1,72'],
            'default_city' => ['sometimes', 'string', 'max:80'],
            'max_open_order_requests_per_customer' => ['sometimes', 'integer', 'between:1,100'],
        ]);
        $before = Setting::allWithDefaults();
        foreach ($data as $k => $v) {
            Setting::put($k, $v);
        }
        AuditLogger::log('settings.updated', null, 'Updated system settings', ['before' => array_intersect_key($before, $data), 'after' => $data]);

        return response()->json(['settings' => Setting::allWithDefaults()]);
    }

    public function activityLogs(Request $request): JsonResponse
    {
        $q = AdminActivityLog::with('user')->latest('id');
        if ($request->filled('action')) {
            $q->where('action', 'like', $request->input('action').'%');
        }
        if ($request->filled('user_id')) {
            $q->where('user_id', $request->integer('user_id'));
        }

        return response()->json($q->paginate(50)->withQueryString());
    }

    public function searchLogs(Request $request): JsonResponse
    {
        $q = SearchLog::select('query', DB::raw('COUNT(*) as searches'), DB::raw('MIN(results_count) as min_results'), DB::raw('MAX(created_at) as last_searched'))
            ->groupBy('query')->orderByDesc('searches');
        if ($request->boolean('no_results')) {
            $q->where('results_count', 0);
        }

        return response()->json($q->paginate(50)->withQueryString());
    }
}
