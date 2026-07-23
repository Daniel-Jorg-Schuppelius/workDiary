<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupGenerateKeysCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Backup;

use App\Models\AuditLog;
use CommonToolkit\Helper\FileSystem\File as ToolkitFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupGenerateKeysCommandTest extends TestCase {
    use RefreshDatabase;

    private string $envPath;

    protected function setUp(): void {
        parent::setUp();
        $this->envPath = sys_get_temp_dir() . '/workdiary-genkeys-' . uniqid() . '.env';
        ToolkitFile::write($this->envPath, "APP_KEY=base64:dGVzdA==\n");
        $this->app->useEnvironmentPath(dirname($this->envPath));
        $this->app->loadEnvironmentFrom(basename($this->envPath));
        config(['backup_targets.master_key' => null, 'backup_targets.recovery_public_key' => null]);
    }

    protected function tearDown(): void {
        if (file_exists($this->envPath)) {
            @unlink($this->envPath);
        }
        parent::tearDown();
    }

    public function test_master_key_command_writes_valid_key_and_logs_audit_without_key_material(): void {
        $this->artisan('workdiary:backup:generate-master-key')->assertExitCode(0);

        $contents = ToolkitFile::read($this->envPath);
        $this->assertMatchesRegularExpression('#^BACKUP_MASTER_KEY=[A-Za-z0-9+/]+={0,2}$#m', $contents);

        preg_match('/^BACKUP_MASTER_KEY=(.+)$/m', $contents, $m);
        $raw = base64_decode($m[1], true);
        $this->assertNotFalse($raw);
        $this->assertSame(SODIUM_CRYPTO_SECRETBOX_KEYBYTES, strlen($raw));

        $audit = AuditLog::query()->where('event', 'backup.masterKeyGenerated')->first();
        $this->assertNotNull($audit);
        $this->assertIsArray($audit->changes);
        $this->assertStringNotContainsString($m[1], json_encode($audit->changes, JSON_THROW_ON_ERROR));
    }

    public function test_master_key_command_refuses_overwrite_without_force(): void {
        ToolkitFile::write($this->envPath, "BACKUP_MASTER_KEY=vorhanden\n");

        $this->artisan('workdiary:backup:generate-master-key')->assertExitCode(1);
        $this->assertStringContainsString('BACKUP_MASTER_KEY=vorhanden', ToolkitFile::read($this->envPath));

        $this->artisan('workdiary:backup:generate-master-key', ['--force' => true])->assertExitCode(0);
        $this->assertStringNotContainsString('BACKUP_MASTER_KEY=vorhanden', ToolkitFile::read($this->envPath));
    }

    public function test_recovery_key_command_writes_public_key_and_shows_secret_once(): void {
        $this->artisan('workdiary:backup:generate-recovery-key')
            ->expectsOutputToContain('Recovery-Secret-Key')
            ->assertExitCode(0);

        $contents = ToolkitFile::read($this->envPath);
        preg_match('/^BACKUP_RECOVERY_PUBLIC_KEY=(.+)$/m', $contents, $m);
        $this->assertNotEmpty($m[1] ?? null);

        $raw = base64_decode($m[1], true);
        $this->assertNotFalse($raw);
        $this->assertSame(SODIUM_CRYPTO_BOX_PUBLICKEYBYTES, strlen($raw));

        $audit = AuditLog::query()->where('event', 'backup.recoveryKeyGenerated')->first();
        $this->assertNotNull($audit);
        $this->assertIsArray($audit->changes);
        $this->assertSame($m[1], $audit->changes['public_key'] ?? null);
    }

    public function test_recovery_key_command_refuses_overwrite_without_force(): void {
        ToolkitFile::write($this->envPath, "BACKUP_RECOVERY_PUBLIC_KEY=vorhanden\n");

        $this->artisan('workdiary:backup:generate-recovery-key')->assertExitCode(1);
        $this->assertStringContainsString('BACKUP_RECOVERY_PUBLIC_KEY=vorhanden', ToolkitFile::read($this->envPath));

        $this->artisan('workdiary:backup:generate-recovery-key', ['--force' => true])->assertExitCode(0);
        $this->assertStringNotContainsString('BACKUP_RECOVERY_PUBLIC_KEY=vorhanden', ToolkitFile::read($this->envPath));
    }
}
