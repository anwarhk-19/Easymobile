<?php

namespace Tests\Feature;

use App\Http\Controllers\InstallController;
use Tests\TestCase;

class InstallerTest extends TestCase
{
    protected function tearDown(): void
    {
        @unlink(storage_path(InstallController::LOCK));
        parent::tearDown();
    }

    public function test_setup_form_is_shown_on_a_fresh_site(): void
    {
        $this->get('/install')->assertOk()->assertSee('Set up Bhatkal Mobile Hub')->assertSee('Database name');
        $this->get('/status')->assertSee('Run the one-time setup');
    }

    public function test_setup_is_locked_once_an_admin_exists(): void
    {
        $this->admin();
        $this->get('/install')->assertNotFound();
        $this->post('/install', ['db_host' => 'evil.example'])->assertNotFound();
        $this->get('/status')->assertDontSee('Run the one-time setup');
    }

    public function test_setup_is_locked_by_the_lock_file(): void
    {
        file_put_contents(storage_path(InstallController::LOCK), 'x');
        $this->get('/install')->assertNotFound();
    }

    public function test_validation_and_bad_database_details_are_explained_without_writing_env(): void
    {
        $before = @file_get_contents(base_path('.env'));
        $this->post('/install', ['app_url' => 'https://x.test', 'admin_password' => 'short'])->assertStatus(422)->assertSee('at least 10 characters');
        $this->post('/install', ['app_url' => 'https://x.test', 'db_host' => 'no-such-host.invalid', 'db_port' => 3306, 'db_database' => 'u1_db', 'db_username' => 'u1_user',
            'db_password' => 'pw', 'admin_email' => 'a@b.in', 'admin_password' => 'LongPassword1', 'admin_password_confirmation' => 'LongPassword1'])
            ->assertStatus(422)->assertSee('host name is wrong');
        $this->assertSame($before, @file_get_contents(base_path('.env')));
    }
}
