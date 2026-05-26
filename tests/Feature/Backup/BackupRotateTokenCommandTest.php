<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupRotateTokenCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Backup;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupRotateTokenCommandTest extends TestCase {
    use RefreshDatabase;

    private string $envPath;
    private ?string $originalEnv = null;

    protected function setUp(): void {
        parent::setUp();
        $this->envPath = sys_get_temp_dir() . '/workdiary-rotate-' . uniqid() . '.env';
        file_put_contents($this->envPath, "APP_KEY=base64:dGVzdA==\n");
        $this->app->useEnvironmentPath(dirname($this->envPath));
        $this->app->loadEnvironmentFrom(basename($this->envPath));
    }

    protected function tearDown(): void {
        if (file_exists($this->envPath)) {
            @unlink($this->envPath);
        }
        parent::tearDown();
    }

    public function test_command_writes_new_token_to_env_and_logs_audit(): void {
        $exit = $this->artisan('workdiary:backup:rotate-token')->run();

        $this->assertSame(0, $exit);

        $contents = (string) file_get_contents($this->envPath);
        $this->assertMatchesRegularExpression('/^BACKUP_HEARTBEAT_TOKEN=[A-Za-z0-9]{64}$/m', $contents);
        $this->assertStringContainsString('APP_KEY=base64:dGVzdA==', $contents);

        $audit = AuditLog::query()->where('event', 'backup.tokenRotated')->first();
        $this->assertNotNull($audit);
        $this->assertIsArray($audit->changes);
        $this->assertArrayHasKey('token_hash', $audit->changes);
        $this->assertSame(64, (int) ($audit->changes['length'] ?? 0));
    }

    public function test_command_replaces_existing_token_line(): void {
        file_put_contents(
            $this->envPath,
            "APP_KEY=base64:dGVzdA==\nBACKUP_HEARTBEAT_TOKEN=OLD-TOKEN\nDB_NAME=foo\n"
        );

        $this->artisan('workdiary:backup:rotate-token')->assertExitCode(0);

        $contents = (string) file_get_contents($this->envPath);
        $this->assertStringNotContainsString('OLD-TOKEN', $contents);
        $this->assertStringContainsString('DB_NAME=foo', $contents);
        $this->assertMatchesRegularExpression('/^BACKUP_HEARTBEAT_TOKEN=[A-Za-z0-9]{64}$/m', $contents);
    }

    public function test_command_honors_custom_length_option(): void {
        $this->artisan('workdiary:backup:rotate-token', ['--length' => 96])->assertExitCode(0);

        $contents = (string) file_get_contents($this->envPath);
        $this->assertMatchesRegularExpression('/^BACKUP_HEARTBEAT_TOKEN=[A-Za-z0-9]{96}$/m', $contents);
    }
}
