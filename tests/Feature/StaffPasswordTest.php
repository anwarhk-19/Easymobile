<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PlatformSeeder;
use Tests\TestCase;

class StaffPasswordTest extends TestCase
{
    public function test_default_staff_accounts_are_created_and_flagged(): void
    {
        $this->seed(PlatformSeeder::class);
        $this->postJson('/api/v1/auth/staff/login', ['email' => 'admin@bhatkalmobilehub.in', 'password' => 'ChangeMe@2026'])->assertOk()->assertJsonPath('user.uses_default_password', true);
        $this->postJson('/api/v1/auth/staff/login', ['email' => 'support@bhatkalmobilehub.in', 'password' => 'Support@2026'])->assertOk()->assertJsonPath('user.role', 'support');
        $this->get('/status')->assertSee('2 account(s) still on the default');
    }

    public function test_staff_can_change_password_and_old_one_stops_working(): void
    {
        $this->seed(PlatformSeeder::class);
        $token = $this->postJson('/api/v1/auth/staff/login', ['email' => 'admin@bhatkalmobilehub.in', 'password' => 'ChangeMe@2026'])->json('token');

        $this->withToken($token)->putJson('/api/v1/auth/password', ['current_password' => 'wrong', 'password' => 'NewSecret2026', 'password_confirmation' => 'NewSecret2026'])
            ->assertStatus(422)->assertJsonValidationErrors('current_password');
        $this->withToken($token)->putJson('/api/v1/auth/password', ['current_password' => 'ChangeMe@2026', 'password' => 'weak', 'password_confirmation' => 'weak'])
            ->assertStatus(422)->assertJsonValidationErrors('password');
        $this->withToken($token)->putJson('/api/v1/auth/password', ['current_password' => 'ChangeMe@2026', 'password' => 'NewSecret2026', 'password_confirmation' => 'NewSecret2026'])->assertOk();

        $this->postJson('/api/v1/auth/staff/login', ['email' => 'admin@bhatkalmobilehub.in', 'password' => 'ChangeMe@2026'])->assertStatus(422);
        $this->postJson('/api/v1/auth/staff/login', ['email' => 'admin@bhatkalmobilehub.in', 'password' => 'NewSecret2026'])->assertOk()->assertJsonPath('user.uses_default_password', false);
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'staff.password_changed']);
    }

    public function test_customers_cannot_use_the_password_endpoint(): void
    {
        $this->actingAsUser(User::factory()->create())->putJson('/api/v1/auth/password', ['current_password' => 'x', 'password' => 'NewSecret2026', 'password_confirmation' => 'NewSecret2026'])->assertForbidden();
    }
}
