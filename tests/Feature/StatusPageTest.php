<?php

namespace Tests\Feature;

use Tests\TestCase;

class StatusPageTest extends TestCase
{
    public function test_status_page_reports_checks_without_secrets(): void
    {
        config(['database.connections.sqlite.password' => 'super-secret-pw']);
        $res = $this->get('/status')->assertOk()->assertSee('Database connection')->assertSee('Database tables created');
        $res->assertDontSee('super-secret-pw')->assertDontSee(base_path());
    }

    public function test_broken_database_still_renders_a_helpful_page(): void
    {
        config(['database.connections.broken' => ['driver' => 'mysql', 'host' => 'no-such-host.invalid', 'database' => 'x', 'username' => 'x', 'password' => 'super-secret-pw']]);
        config(['database.default' => 'broken', 'cache.default' => 'database', 'cache.stores.database.connection' => 'broken']);
        try {
            $res = $this->get('/status');
        } finally {
            config(['database.default' => 'sqlite', 'cache.default' => 'array']); // let the test framework clean up normally
        }
        $res->assertOk()->assertSee('✗', false)->assertSee('DB_HOST=localhost')->assertDontSee('super-secret-pw')->assertDontSee('no-such-host.invalid');
    }

    public function test_status_page_can_be_disabled(): void
    {
        config(['app.status_page' => false]);
        $this->get('/status')->assertNotFound();
    }
}
