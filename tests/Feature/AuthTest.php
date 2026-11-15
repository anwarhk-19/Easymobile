<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_customer_registers_and_logs_in_with_otp(): void
    {
        $code = $this->postJson('/api/v1/auth/otp/send', ['phone' => '+91 98450 12345'])
            ->assertOk()->assertJson(['is_registered' => false])->json('debug_code');

        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '9845012345', 'code' => $code])
            ->assertStatus(422)->assertJsonValidationErrors('name');

        // The OTP is not consumed when only the name is missing, so the same code still works.
        $res = $this->postJson('/api/v1/auth/otp/verify', ['phone' => '9845012345', 'code' => $code, 'name' => 'Ayesha'])
            ->assertOk()->assertJsonPath('user.role', 'customer');

        $this->withToken($res->json('token'))->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('user.phone', '9845012345');
        $this->assertDatabaseHas('customer_profiles', ['user_id' => $res->json('user.id')]);
    }

    public function test_invalid_phone_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/otp/send', ['phone' => '12345'])->assertStatus(422)->assertJsonValidationErrors('phone');
    }

    public function test_wrong_code_attempts_are_limited(): void
    {
        $this->postJson('/api/v1/auth/otp/send', ['phone' => '9845012346'])->assertOk();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/otp/verify', ['phone' => '9845012346', 'code' => '000000', 'name' => 'X'])->assertStatus(422);
            $this->travel(11)->seconds(); // stay under the per-minute verify throttle
        }
        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '9845012346', 'code' => '000000', 'name' => 'X'])
            ->assertStatus(422)->assertJsonFragment(['Too many incorrect attempts. Please request a new OTP.']);
    }

    public function test_otp_expires(): void
    {
        $code = $this->postJson('/api/v1/auth/otp/send', ['phone' => '9845012347'])->json('debug_code');
        $this->travel(6)->minutes();
        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '9845012347', 'code' => $code, 'name' => 'X'])
            ->assertStatus(422)->assertJsonValidationErrors('code');
    }

    public function test_resend_cooldown_and_send_rate_limit(): void
    {
        $this->postJson('/api/v1/auth/otp/send', ['phone' => '9845012348'])->assertOk();
        $this->postJson('/api/v1/auth/otp/send', ['phone' => '9845012348'])->assertStatus(429);
    }

    public function test_suspended_user_cannot_log_in_or_use_tokens(): void
    {
        $user = User::factory()->create(['phone' => '9845012349', 'status' => 'suspended']);
        $code = $this->postJson('/api/v1/auth/otp/send', ['phone' => '9845012349'])->json('debug_code');
        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '9845012349', 'code' => $code])->assertStatus(403);

        $this->actingAsUser($user)->getJson('/api/v1/auth/me')->assertStatus(403)->assertJsonPath('code', 'account_suspended');
    }

    public function test_staff_login_with_password_only(): void
    {
        $admin = User::factory()->staff('admin')->create(['email' => 'a@x.in']);
        $this->postJson('/api/v1/auth/staff/login', ['email' => 'a@x.in', 'password' => 'wrong'])->assertStatus(422);
        $this->postJson('/api/v1/auth/staff/login', ['email' => 'a@x.in', 'password' => 'Secret@12345'])->assertOk()->assertJsonPath('user.role', 'admin');

        $customer = User::factory()->create(['email' => 'c@x.in', 'password' => 'Secret@12345']);
        $this->postJson('/api/v1/auth/staff/login', ['email' => 'c@x.in', 'password' => 'Secret@12345'])->assertStatus(422);
    }

    public function test_role_based_access_is_enforced(): void
    {
        $customer = $this->customer();
        $this->actingAsUser($customer)->getJson('/api/v1/merchant/dashboard')->assertStatus(403);
        $this->actingAsUser($customer)->getJson('/api/v1/admin/dashboard')->assertStatus(403);
        $this->actingAsUser($customer)->getJson('/api/v1/technician/repairs')->assertStatus(403);

        $support = User::factory()->staff('support')->create();
        $this->actingAsUser($support)->getJson('/api/v1/admin/dashboard')->assertOk();
        $this->actingAsUser($support)->getJson('/api/v1/admin/settings')->assertStatus(403);
        $this->getJson('/api/v1/orders')->assertStatus(403); // still acting as support
    }
}
