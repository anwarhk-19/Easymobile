<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRepairRequest;
use App\Models\RepairRequest;
use App\Models\TechnicianProfile;
use App\Services\FileStore;
use App\Services\RepairService;
use App\Services\ReviewService;
use App\Services\WhatsAppLinkBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RepairController extends Controller
{
    public function __construct(private RepairService $repairs) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(RepairRequest::where('customer_id', $request->user()->id)
            ->with(['technician:id,name,slug,shop_name', 'brand:id,name', 'phoneModel:id,name', 'repairCategory:id,name'])->latest()->paginate(20));
    }

    public function store(StoreRepairRequest $request, FileStore $files): JsonResponse
    {
        $data = $request->validated();
        $repair = $this->repairs->create($request->user(), TechnicianProfile::findOrFail($data['technician_id']), $data);
        foreach ($request->file('images', []) as $img) {
            $repair->images()->create(['uploaded_by' => $request->user()->id, 'path' => $files->storePrivate($img, 'repairs/'.$repair->id)]);
        }

        return response()->json(['repair' => $repair, 'message' => 'Repair request sent to the technician.'], 201);
    }

    public function show(Request $request, RepairRequest $repair, WhatsAppLinkBuilder $wa): JsonResponse
    {
        Gate::authorize('actAsCustomer', $repair);
        $repair->load(['technician:id,name,slug,shop_name,phone,whatsapp,address,warranty_terms', 'brand:id,name', 'phoneModel:id,name', 'repairCategory:id,name',
            'images', 'quotations', 'parts', 'histories.user', 'warranty', 'diagnoses', 'preferredArea:id,name']);
        $reviewed = $repair->reviews()->exists();

        return response()->json([
            'repair' => $repair,
            'imei' => $repair->imei_encrypted,
            'can_review' => $repair->status === RepairRequest::COMPLETED && ! $reviewed,
            'whatsapp_link' => $wa->forRepair($repair),
        ]);
    }

    public function respondQuotation(Request $request, RepairRequest $repair): JsonResponse
    {
        Gate::authorize('actAsCustomer', $repair);
        $data = $request->validate(['decision' => ['required', 'in:approve,reject'], 'note' => ['nullable', 'string', 'max:500']]);

        return response()->json(['repair' => $this->repairs->respondToQuotation($repair, $request->user(), $data['decision'] === 'approve', $data['note'] ?? null)]);
    }

    public function confirmHandover(Request $request, RepairRequest $repair): JsonResponse
    {
        Gate::authorize('actAsCustomer', $repair);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        return response()->json(['repair' => $this->repairs->confirmHandover($repair, $request->user(), $data['note'] ?? null)]);
    }

    public function cancel(Request $request, RepairRequest $repair): JsonResponse
    {
        Gate::authorize('actAsCustomer', $repair);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return response()->json(['repair' => $this->repairs->cancel($repair, $request->user(), $data['reason'])]);
    }

    public function review(Request $request, RepairRequest $repair, ReviewService $reviews): JsonResponse
    {
        Gate::authorize('actAsCustomer', $repair);
        $data = $request->validate(['rating' => ['required', 'integer', 'between:1,5'], 'comment' => ['nullable', 'string', 'max:1000']]);

        return response()->json(['review' => $reviews->forRepair($request->user(), $repair->load('technician'), $data), 'message' => 'Thank you for your feedback!'], 201);
    }
}
