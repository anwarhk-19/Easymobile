<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class LogViewerTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->file = storage_path('logs/viewer-test.log');
        file_put_contents($this->file, implode("\n", [
            '[2026-11-16 09:00:00] production.INFO: [SMS] to 9845012345: 482913 is your Bhatkal Mobile Hub verification code.',
            '[2026-11-16 09:05:00] production.ERROR: SQLSTATE[HY000] [2002] Connection refused {"exception":"[object] (PDOException(code: 2002))',
            '[stacktrace]',
            '#0 /vendor/laravel/framework/src/Illuminate/Database/Connectors/Connector.php(65): PDO->__construct()',
            '"}',
            '[2026-11-16 09:10:00] production.WARNING: Stock reminder skipped',
            '',
        ]));
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    public function test_admin_sees_parsed_entries_newest_first_with_otps_masked(): void
    {
        $this->actingAsUser($this->admin())->getJson('/api/v1/admin/system-logs')->assertOk()->assertJsonFragment(['name' => 'viewer-test.log']);

        $res = $this->getJson('/api/v1/admin/system-logs/viewer-test.log')->assertOk();
        $this->assertSame(3, $res->json('total'));
        $this->assertSame('warning', $res->json('data.0.level'));
        $this->assertSame('error', $res->json('data.1.level'));
        $this->assertStringContainsString('Connector.php', $res->json('data.1.context'));
        $this->assertSame(1, $res->json('counts.error'));
        $this->assertStringContainsString('•••••• is your', $res->json('data.2.message'));
        $this->assertStringNotContainsString('482913', json_encode($res->json()));
    }

    public function test_filters_by_level_and_search(): void
    {
        $this->actingAsUser($this->admin());
        $this->assertSame(1, $this->getJson('/api/v1/admin/system-logs/viewer-test.log?level=error')->json('total'));
        $this->assertSame(1, $this->getJson('/api/v1/admin/system-logs/viewer-test.log?q=PDO-%3E__construct')->json('total'));
    }

    public function test_download_is_masked_and_clear_is_audited(): void
    {
        $this->actingAsUser($this->admin());
        $body = $this->get('/api/v1/admin/system-logs/viewer-test.log/download')->assertOk()->streamedContent();
        $this->assertStringNotContainsString('482913', $body);
        $this->assertStringContainsString('Stock reminder skipped', $body);

        $this->deleteJson('/api/v1/admin/system-logs/viewer-test.log')->assertOk();
        $this->assertSame('', file_get_contents($this->file));
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'logs.cleared']);
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'logs.downloaded']);
    }

    public function test_only_admins_and_only_log_files(): void
    {
        $this->actingAsUser(User::factory()->staff('support')->create())->getJson('/api/v1/admin/system-logs')->assertForbidden();
        $this->actingAsUser($this->customer())->getJson('/api/v1/admin/system-logs')->assertForbidden();

        $this->actingAsUser($this->admin());
        foreach (['..%2F.env', '.env', 'missing.log', '..%2F..%2Fdatabase%2Fdatabase.sqlite'] as $bad) {
            $this->getJson('/api/v1/admin/system-logs/'.$bad)->assertNotFound();
        }
    }
}
