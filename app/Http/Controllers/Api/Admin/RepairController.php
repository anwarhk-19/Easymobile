<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\RepairRequest;
use App\Services\AuditLogger;
use App\Services\RepairService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RepairController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = RepairRequest::with(['technician:id,name,shop_name', 'customer:id,name', 'brand:id,name', 'phoneModel:id,name', 'repairCategory:id,name'])->latest();
        if ($request->filled('status')) {
            $q->where('status', $request->input('status'));
        }
        if ($request->filled('technician_id')) {
            $q->where('technician_profile_id', $request->integer('technician_id'));
        }
        if ($request->filled('q')) {
            $q->where('reference', 'like', '%'.$request->input('q').'%');
        }

        return response()->json($q->paginate(25)->withQueryString());
    }

    public function show(Request $request, RepairRequest $repair): JsonResponse
    {
        $repair->load(['technician:id,name,shop_name,phone', 'customer:id,name,phone', 'brand:id,name', 'phoneModel:id,name', 'repairCategory:id,name',
            'images', 'quotations', 'parts', 'histories.user', 'warranty', 'diagnoses']);
        $imei = null;
        if ($request->user()->isAdmin() && $request->boolean('reveal_imei') && $repair->imei_encrypted) {
            $imei = $repair->imei_encrypted;
            AuditLogger::log('repair.imei_viewed', $repair, "Viewed IMEI for repair {$repair->reference}");
        }

        return response()->json(['repair' => $repair, 'has_imei' => (bool) $repair->imei_encrypted, 'imei' => $imei]);
    }

    public function cancel(Request $request, RepairRequest $repair, RepairService $repairs): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $repairs->cancel($repair, $request->user(), $data['reason']);
        AuditLogger::log('repair.cancelled', $repair, "Admin cancelled repair {$repair->reference}", $data);

        return response()->json(['repair' => $repair]);
    }
}
