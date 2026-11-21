<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class TestOtpTest extends TestCase
{
    public function test_default_master_otp_is_998001_and_can_be_switched_off(): void
    {
        User::factory()->create(['phone' => '9900000001']);
        $this->assertSame('998001', config('otp.test_code'));
        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '9900000001', 'code' => '998001'])->assertOk()->assertJsonStructure(['token']);

        config(['otp.test_code' => null]); // what OTP_TEST_CODE=off produces
        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '9900000001', 'code' => '998001'])->assertStatus(422);
    }

    public function test_test_code_signs_in_listed_numbers_without_sms(): void
    {
        config(['otp.test_code' => '246810', 'otp.test_phones' => ['9900000001', '9845000101'], 'otp.expose_code' => false]);
        User::factory()->create(['phone' => '9900000001']);

        $this->postJson('/api/v1/auth/otp/send', ['phone' => '9900000001'])->assertOk()->assertJsonPath('test_mode', true)->assertJsonMissingPath('debug_code');
        // No per-number limit for test numbers.
        $this->postJson('/api/v1/auth/otp/send', ['phone' => '9900000001'])->assertOk();

        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '9900000001', 'code' => '111111'])->assertStatus(422);
        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '9900000001', 'code' => '246810'])->assertOk()->assertJsonStructure(['token']);

        // A listed number that isn't registered yet can create an account (e.g. a test merchant).
        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '9845000101', 'code' => '246810', 'name' => 'Test Shop', 'role' => 'merchant'])
            ->assertOk()->assertJsonPath('user.role', 'merchant');
    }

    public function test_test_code_rejected_for_other_numbers(): void
    {
        config(['otp.test_code' => '246810', 'otp.test_phones' => ['9900000001']]);
        User::factory()->create(['phone' => '9812345678']);
        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '9812345678', 'code' => '246810'])->assertStatus(422);
        $this->postJson('/api/v1/auth/otp/send', ['phone' => '9812345678'])->assertOk()->assertJsonMissingPath('test_mode');
    }

    public function test_status_page_warns_when_open_to_everyone(): void
    {
        config(['otp.test_code' => '246810', 'otp.test_phones' => ['*']]);
        $this->get('/status')->assertSee('OTP_TEST_PHONES=*');
    }
}
