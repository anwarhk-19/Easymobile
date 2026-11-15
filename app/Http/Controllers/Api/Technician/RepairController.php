<?php

namespace App\Http\Controllers\Api\Technician;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuotationRequest;
use App\Models\RepairRequest;
use App\Models\TechnicianProfile;
use App\Services\RepairService;
use App\Services\ReportService;
use App\Services\WhatsAppLinkBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RepairController extends Controller
{
    public function __construct(private RepairService $repairs) {}

    private function profile(Request $request): TechnicianProfile
    {
        return $request->user()->technicianProfile ?? abort(response()->json(['message' => 'Create your technician profile first.', 'code' => 'profile_required'], 404));
    }

    public function dashboard(Request $request): JsonResponse
    {
        $p = $this->profile($request);
        $q = fn () => RepairRequest::where('technician_profile_id', $p->id);

        return response()->json([
            'profile' => $p->only(['id', 'name', 'slug', 'status', 'rejection_reason', 'rating_avg', 'rating_count']),
            'stats' => [
                'new_requests' => $q()->where('status', RepairRequest::SUBMITTED)->count(),
                'awaiting_quote' => $q()->whereIn('status', [RepairRequest::ACCEPTED, RepairRequest::DIAGNOSING, RepairRequest::QUOTE_REJECTED])->count(),
                'awaiting_approval' => $q()->where('status', RepairRequest::QUOTED)->count(),
                'in_progress' => $q()->whereIn('status', [RepairRequest::APPROVED, RepairRequest::IN_PROGRESS, RepairRequest::TESTING])->count(),
                'ready_for_handover' => $q()->where('status', RepairRequest::READY)->count(),
                'completed' => $q()->where('status', RepairRequest::COMPLETED)->count(),
                'completed_this_month' => $q()->where('status', RepairRequest::COMPLETED)->where('completed_at', '>=', now()->startOfMonth())->count(),
                'revenue_this_month' => round((float) $q()->where('status', RepairRequest::COMPLETED)->where('completed_at', '>=', now()->startOfMonth())->sum('final_amount'), 2),
                'rating_avg' => (float) $p->rating_avg,
                'rating_count' => $p->rating_count,
            ],
            'recent' => $q()->whereIn('status', RepairRequest::OPEN_STATUSES)->with(['brand:id,name', 'phoneModel:id,name', 'repairCategory:id,name', 'customer:id,name'])->latest()->limit(8)->get(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $p = $this->profile($request);
        $q = RepairRequest::where('technician_profile_id', $p->id)->with(['brand:id,name', 'phoneModel:id,name', 'repairCategory:id,name', 'customer:id,name'])->latest();
        if ($request->filled('status')) {
            $groups = [
                'new' => [RepairRequest::SUBMITTED],
                'active' => [RepairRequest::ACCEPTED, RepairRequest::DIAGNOSING, RepairRequest::QUOTED, RepairRequest::QUOTE_REJECTED, RepairRequest::APPROVED, RepairRequest::IN_PROGRESS, RepairRequest::TESTING, RepairRequest::READY],
                'closed' => [RepairRequest::COMPLETED, RepairRequest::CANCELLED, RepairRequest::REJECTED],
            ];
            $q->whereIn('status', $groups[$request->input('status')] ?? [$request->input('status')]);
        }

        return response()->json($q->paginate(20)->withQueryString());
    }

    public function show(Request $request, RepairRequest $repair, WhatsAppLinkBuilder $wa): JsonResponse
    {
        Gate::authorize('actAsTechnician', $repair);
        $repair->load(['customer:id,name,phone', 'brand:id,name', 'phoneModel:id,name', 'repairCategory:id,name', 'images', 'quotations', 'parts', 'histories.user', 'warranty', 'diagnoses', 'preferredArea:id,name']);
        $canSeeSensitive = Gate::allows('viewSensitive', $repair);
        $data = $repair->toArray();
        // Customer contact details are only shared once the technician accepts the job.
        if (! $repair->accepted_at) {
            $data['customer']['phone'] = null;
            $data['address'] = $repair->address ? '(shared after you accept the request)' : null;
        }

        return response()->json([
            'repair' => $data,
            'imei' => $canSeeSensitive ? $repair->imei_encrypted : null,
            'whatsapp_customer_link' => $repair->accepted_at ? $wa->link($repair->customer->phone, "Hello {$repair->customer->name}, this is {$repair->technician->name} about your repair request {$repair->reference} on Bhatkal Mobile Hub.") : null,
        ]);
    }

    public function accept(Request $request, RepairRequest $repair): JsonResponse
    {
        Gate::authorize('actAsTechnician', $repair);

        return response()->json(['repair' => $this->repairs->accept($repair, $request->user())]);
    }

    public function reject(Request $request, RepairRequest $repair): JsonResponse
    {
        Gate::authorize('actAsTechnician', $repair);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return response()->json(['repair' => $this->repairs->reject($repair, $request->user(), $data['reason'])]);
    }

    public function diagnosis(Request $request, RepairRequest $repair): JsonResponse
    {
        Gate::authorize('actAsTechnician', $repair);
        $data = $request->validate(['findings' => ['required', 'string', 'min:5', 'max:2000']]);

        return response()->json(['repair' => $this->repairs->recordDiagnosis($repair, $request->user(), $data['findings'])]);
    }

    public function quotation(QuotationRequest $request, RepairRequest $repair): JsonResponse
    {
        Gate::authorize('actAsTechnician', $repair);

        return response()->json(['quotation' => $this->repairs->submitQuotation($repair, $request->user(), $request->validated())], 201);
    }

    public function start(Request $request, RepairRequest $repair): JsonResponse
    {
        Gate::authorize('actAsTechnician', $repair);

        return response()->json(['repair' => $this->repairs->start($repair, $request->user())]);
    }

    public function progress(Request $request, RepairRequest $repair): JsonResponse
    {
        Gate::authorize('actAsTechnician', $repair);
        $data = $request->validate(['note' => ['required', 'string', 'max:1000']]);

        return response()->json(['repair' => $this->repairs->addProgress($repair, $request->user(), $data['note'])]);
    }

    public function part(Request $request, RepairRequest $repair): JsonResponse
    {
        Gate::authorize('actAsTechnician', $repair);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'parts_type' => ['required', Rule::in(RepairRequest::PARTS_TYPES)],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'unit_cost' => ['required', 'numeric', 'min:0', 'max:200000'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json(['part' => $this->repairs->addPart($repair, $request->user(), $data)], 201);
    }

    public function startTesting(Request $request, RepairRequest $repair): JsonResponse
    {
        Gate::authorize('actAsTechnician', $repair);

        return response()->json(['repair' => $this->repairs->startTesting($repair, $request->user())]);
    }

    public function testResult(Request $request, RepairRequest $repair): JsonResponse
    {
        Gate::authorize('actAsTechnician', $repair);
        $data = $request->validate([
            'checklist' => ['required', 'array', 'min:1'],
            'checklist.*' => ['boolean'],
            'passed' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        return response()->json(['repair' => $this->repairs->completeTesting($repair, $request->user(), $data['checklist'], $data['passed'], $data['notes'] ?? null)]);
    }

    public function complete(Request $request, RepairRequest $repair): JsonResponse
    {
        Gate::authorize('actAsTechnician', $repair);
        $data = $request->validate([
            'customer_verified_in_person' => ['nullable', 'boolean'],
            'payment_method' => ['nullable', Rule::in(['cash', 'upi', 'card'])],
        ]);

        return response()->json(['repair' => $this->repairs->complete($repair->load('technician'), $request->user(), $data)->load('warranty')]);
    }

    public function cancel(Request $request, RepairRequest $repair): JsonResponse
    {
        Gate::authorize('actAsTechnician', $repair);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return response()->json(['repair' => $this->repairs->cancel($repair, $request->user(), $data['reason'])]);
    }

    public function reports(Request $request, ReportService $reports): JsonResponse
    {
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        [$from, $to] = ReportService::range($request->input('from'), $request->input('to'));

        return response()->json($reports->technician($this->profile($request), $from, $to));
    }
}
