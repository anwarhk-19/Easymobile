<?php

namespace Tests\Feature;

use App\Models\RepairRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RepairWorkflowTest extends TestCase
{
    private array $quote = ['diagnosis' => 'Display panel cracked, digitizer damaged.', 'parts_cost' => 5200, 'labour_cost' => 500,
        'parts_type' => 'original', 'estimated_hours' => 3, 'warranty_days' => 90, 'warranty_exclusions' => 'Physical damage'];

    public function test_complete_repair_workflow_with_revised_quotation(): void
    {
        Storage::fake('local');
        $tech = $this->technician();
        $tu = $tech->user;
        $customer = $this->customer();

        // 1–4. Request with photo.
        $id = $this->actingAsUser($customer)->post('/api/v1/repairs', $this->repairPayload($tech) + ['images' => [UploadedFile::fake()->image('crack.jpg')]])
            ->assertCreated()->json('repair.id');
        $this->assertCount(1, RepairRequest::find($id)->images);

        // Before acceptance the technician cannot see the customer's phone number.
        $this->actingAsUser($tu)->getJson("/api/v1/technician/repairs/{$id}")->assertOk()->assertJsonPath('repair.customer.phone', null);

        // 6–8. Accept, diagnose, quote.
        $this->actingAsUser($tu)->postJson("/api/v1/technician/repairs/{$id}/accept")->assertOk()->assertJsonPath('repair.status', 'accepted');
        $this->actingAsUser($tu)->getJson("/api/v1/technician/repairs/{$id}")->assertJsonPath('repair.customer.phone', $customer->phone);
        $this->actingAsUser($tu)->postJson("/api/v1/technician/repairs/{$id}/diagnosis", ['findings' => 'Screen cracked; frame OK.'])->assertJsonPath('repair.status', 'diagnosing');

        // Work cannot start without an approved quote.
        $this->actingAsUser($tu)->postJson("/api/v1/technician/repairs/{$id}/start")->assertStatus(422);

        $this->actingAsUser($tu)->postJson("/api/v1/technician/repairs/{$id}/quotation", $this->quote)->assertCreated()->assertJsonPath('quotation.total', '5700.00');

        // 9–10. Customer approves → technician starts.
        $this->actingAsUser($customer)->postJson("/api/v1/repairs/{$id}/quotation", ['decision' => 'approve'])->assertOk()->assertJsonPath('repair.status', 'approved');
        $this->actingAsUser($tu)->postJson("/api/v1/technician/repairs/{$id}/start")->assertOk()->assertJsonPath('repair.status', 'in_progress');

        // Quote changes mid-repair → back to "quoted"; revision requires a reason.
        $this->actingAsUser($tu)->postJson("/api/v1/technician/repairs/{$id}/quotation", ['parts_cost' => 6000] + $this->quote)->assertStatus(422);
        $this->actingAsUser($tu)->postJson("/api/v1/technician/repairs/{$id}/quotation", ['parts_cost' => 6000, 'change_reason' => 'Frame also bent'] + $this->quote)->assertCreated();
        $this->assertSame('quoted', RepairRequest::find($id)->status);
        $this->actingAsUser($tu)->postJson("/api/v1/technician/repairs/{$id}/testing")->assertStatus(422); // paused
        $this->actingAsUser($customer)->postJson("/api/v1/repairs/{$id}/quotation", ['decision' => 'approve'])->assertJsonPath('repair.status', 'in_progress');
        $this->assertSame(['superseded', 'approved'], RepairRequest::find($id)->quotations->pluck('status')->all());

        // 11–12. Parts, progress, testing.
        $this->actingAsUser($tu)->postJson("/api/v1/technician/repairs/{$id}/part", ['name' => 'AMOLED display', 'parts_type' => 'original', 'quantity' => 1, 'unit_cost' => 5200])->assertCreated();
        $this->actingAsUser($tu)->postJson("/api/v1/technician/repairs/{$id}/progress", ['note' => 'Display fitted'])->assertOk();
        $this->actingAsUser($tu)->postJson("/api/v1/technician/repairs/{$id}/testing")->assertJsonPath('repair.status', 'testing');
        $this->actingAsUser($tu)->postJson("/api/v1/technician/repairs/{$id}/test-result", ['checklist' => ['display' => true, 'touch' => true], 'passed' => true])
            ->assertJsonPath('repair.status', 'ready_for_handover');

        // 13–14. Completion requires the customer's handover check.
        $this->actingAsUser($tu)->postJson("/api/v1/technician/repairs/{$id}/complete")->assertStatus(422);
        $this->actingAsUser($customer)->postJson("/api/v1/repairs/{$id}/handover")->assertOk();
        $done = $this->actingAsUser($tu)->postJson("/api/v1/technician/repairs/{$id}/complete", ['payment_method' => 'upi'])->assertOk();

        // 15. Final bill and warranty.
        $done->assertJsonPath('repair.status', 'completed')->assertJsonPath('repair.final_amount', '6500.00');
        $this->assertNotNull($done->json('repair.warranty.warranty_code'));
        $this->assertSame(now()->addDays(90)->toDateString(), substr($done->json('repair.warranty.ends_at'), 0, 10));

        // 16. Feedback — once only.
        $this->actingAsUser($customer)->postJson("/api/v1/repairs/{$id}/review", ['rating' => 5, 'comment' => 'Great'])->assertCreated();
        $this->actingAsUser($customer)->postJson("/api/v1/repairs/{$id}/review", ['rating' => 4])->assertStatus(422);

        $this->assertGreaterThanOrEqual(12, RepairRequest::find($id)->histories()->count());
    }

    public function test_customer_can_reject_quotation_and_technician_requote(): void
    {
        $tech = $this->technician();
        $customer = $this->customer();
        $id = $this->actingAsUser($customer)->postJson('/api/v1/repairs', $this->repairPayload($tech))->json('repair.id');
        $this->actingAsUser($tech->user)->postJson("/api/v1/technician/repairs/{$id}/accept");
        $this->actingAsUser($tech->user)->postJson("/api/v1/technician/repairs/{$id}/quotation", $this->quote);
        $this->actingAsUser($customer)->postJson("/api/v1/repairs/{$id}/quotation", ['decision' => 'reject', 'note' => 'Too expensive'])->assertJsonPath('repair.status', 'quote_rejected');
        $this->actingAsUser($tech->user)->postJson("/api/v1/technician/repairs/{$id}/quotation", ['parts_type' => 'oem_compatible', 'parts_cost' => 2500] + $this->quote)->assertCreated();
        $this->assertSame('quoted', RepairRequest::find($id)->status);
    }

    public function test_other_technicians_and_customers_cannot_access_a_repair(): void
    {
        $tech = $this->technician();
        $id = $this->actingAsUser($this->customer())->postJson('/api/v1/repairs', $this->repairPayload($tech))->json('repair.id');
        $this->actingAsUser($this->technician()->user)->getJson("/api/v1/technician/repairs/{$id}")->assertStatus(403);
        $this->actingAsUser($this->customer())->getJson("/api/v1/repairs/{$id}")->assertStatus(403);
    }

    public function test_imei_is_encrypted_and_restricted(): void
    {
        $tech = $this->technician();
        $customer = $this->customer();
        $id = $this->actingAsUser($customer)->postJson('/api/v1/repairs', $this->repairPayload($tech, ['imei' => '356938035643809']))->json('repair.id');

        $raw = \DB::table('repair_requests')->where('id', $id)->value('imei_encrypted');
        $this->assertNotSame('356938035643809', $raw);
        $this->actingAsUser($tech->user)->getJson("/api/v1/technician/repairs/{$id}")->assertJsonPath('imei', null);
        $this->actingAsUser($tech->user)->postJson("/api/v1/technician/repairs/{$id}/accept");
        $this->actingAsUser($tech->user)->getJson("/api/v1/technician/repairs/{$id}")->assertJsonPath('imei', '356938035643809');
    }

    public function test_upload_validation_rejects_non_images(): void
    {
        $tech = $this->technician();
        $this->actingAsUser($this->customer())->post('/api/v1/repairs', $this->repairPayload($tech) + ['images' => [UploadedFile::fake()->create('virus.php', 10, 'application/x-php')]])
            ->assertStatus(422)->assertJsonValidationErrors('images.0');
    }

    public function test_unsupported_service_mode_is_rejected(): void
    {
        $tech = $this->technician(['onsite_available' => false]);
        $this->actingAsUser($this->customer())->postJson('/api/v1/repairs', $this->repairPayload($tech, ['service_mode' => 'onsite', 'address' => 'Jali']))->assertStatus(422);
    }
}
