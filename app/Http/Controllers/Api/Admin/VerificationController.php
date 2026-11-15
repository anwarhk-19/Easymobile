<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\TechnicianProfile;
use App\Services\AuditLogger;
use App\Services\Notifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Shop and technician verification: approve / reject / suspend / reinstate. */
class VerificationController extends Controller
{
    public function __construct(private Notifier $notifier) {}

    public function shops(Request $request): JsonResponse
    {
        $q = Shop::withTrashed()->with(['area:id,name', 'merchant:id,name,phone'])->withCount('products')->latest();
        $this->filter($q, $request, ['name', 'owner_name', 'phone']);

        return response()->json($q->paginate(25)->withQueryString());
    }

    public function shop(Shop $shop): JsonResponse
    {
        return response()->json(['shop' => $shop->load(['area', 'merchant:id,name,phone,email,status', 'photos', 'documents'])->append('maps_link')->loadCount(['products', 'orders'])]);
    }

    public function technicians(Request $request): JsonResponse
    {
        $q = TechnicianProfile::withTrashed()->with(['area:id,name', 'user:id,name,phone', 'repairCategories:id,name'])->latest();
        $this->filter($q, $request, ['name', 'shop_name', 'phone']);

        return response()->json($q->paginate(25)->withQueryString());
    }

    public function technician(TechnicianProfile $technician): JsonResponse
    {
        return response()->json(['technician' => $technician->load(['area', 'user:id,name,phone,email,status', 'serviceAreas:id,name', 'brands:id,name', 'phoneModels:id,name', 'repairCategories:id,name', 'documents'])->loadCount('repairRequests')]);
    }

    public function updateShop(Request $request, Shop $shop): JsonResponse
    {
        $data = $this->validateAction($request);
        if ($data['action'] === 'feature' || $data['action'] === 'unfeature') {
            $shop->update(['is_featured' => $data['action'] === 'feature']);
            AuditLogger::log('shop.'.$data['action'], $shop, ucfirst($data['action'])."d shop {$shop->name}");

            return response()->json(['shop' => $shop]);
        }
        $this->apply($shop, $data, $request, 'shop', $shop->name);
        $this->notifier->notify($shop->merchant, 'Shop verification', 'shop_status', ['shop_name' => $shop->name, 'status_label' => Notifier::label($shop->status)], '/merchant');

        return response()->json(['shop' => $shop]);
    }

    public function updateTechnician(Request $request, TechnicianProfile $technician): JsonResponse
    {
        $data = $this->validateAction($request);
        abort_if(in_array($data['action'], ['feature', 'unfeature'], true), 422, 'Not supported for technicians.');
        $this->apply($technician, $data, $request, 'technician', $technician->name);
        $this->notifier->notify($technician->user, 'Profile verification', 'technician_status', ['status_label' => Notifier::label($technician->status)], '/technician');

        return response()->json(['technician' => $technician]);
    }

    private function validateAction(Request $request): array
    {
        return $request->validate([
            'action' => ['required', 'in:approve,reject,suspend,reinstate,feature,unfeature'],
            'reason' => ['required_if:action,reject,suspend', 'nullable', 'string', 'max:1000'],
        ]);
    }

    private function apply(Model $model, array $data, Request $request, string $type, string $name): void
    {
        $status = ['approve' => 'approved', 'reject' => 'rejected', 'suspend' => 'suspended', 'reinstate' => 'approved'][$data['action']];
        $before = $model->status;
        if ($data['action'] === 'reinstate' && $before !== 'suspended') {
            abort(422, 'Only suspended profiles can be reinstated.');
        }
        $model->forceFill([
            'status' => $status,
            'rejection_reason' => in_array($data['action'], ['reject', 'suspend'], true) ? $data['reason'] : null,
            'approved_at' => $status === 'approved' ? ($model->approved_at ?? now()) : $model->approved_at,
            'approved_by' => $status === 'approved' ? $request->user()->id : $model->approved_by,
        ])->save();
        AuditLogger::log("{$type}.{$data['action']}", $model, ucfirst($data['action'])." {$type} {$name}", ['from' => $before, 'to' => $status, 'reason' => $data['reason'] ?? null]);
    }

    private function filter($q, Request $request, array $searchCols): void
    {
        if ($request->filled('status')) {
            $q->where('status', $request->input('status'));
        }
        if ($request->filled('q')) {
            $like = '%'.$request->input('q').'%';
            $q->where(function ($s) use ($searchCols, $like) {
                foreach ($searchCols as $c) {
                    $s->orWhere($c, 'like', $like);
                }
            });
        }
    }
}
