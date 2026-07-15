<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupVerifyRestoreTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Backup;

use App\Enums\Backup\BackupGenerationStatus;
use App\Models\Backup\{BackupGeneration, BackupTargetConnection};
use App\Models\RestoreTest;
use App\Services\Backup\{BackupRestoreTestService, BackupRunService, BackupVerifyService};
use App\Services\Backup\Exceptions\BackupPreflightException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeBackupTarget;
use Tests\TestCase;

/**
 * Verifikation + Restore-Test Phase 32 (MVP-365): Stichproben-Verify setzt
 * `verified`, Remote-Manipulation setzt `verify_failed`; der Restore-Test
 * stellt isoliert wieder her, prüft den DB-Dump und protokolliert RPO/RTO
 * plus Registereintrag — Produktion wird nie überschrieben.
 */
class BackupVerifyRestoreTest extends TestCase {
    use RefreshDatabase;

    private FakeBackupTarget $adapter;

    private BackupTargetConnection $connection;

    private string $base;

    protected function setUp(): void {
        parent::setUp();
        $this->adapter = new FakeBackupTarget();
        $this->connection = BackupTargetConnection::factory()->active()->create();

        $this->base = sys_get_temp_dir() . '/wd-backup-vr-' . uniqid();
        mkdir($this->base . '/files/storage/app', 0770, true);
        file_put_contents($this->base . '/files/storage/app/beleg.txt', str_repeat('workdiary ', 50_000));
        $dbFile = $this->base . '/source.sqlite';
        $pdo = new \PDO('sqlite:' . $dbFile);
        $pdo->exec('CREATE TABLE demo (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec("INSERT INTO demo (name) VALUES ('workdiary')");
        unset($pdo);

        config([
            'backup_targets.master_key' => base64_encode(str_repeat("\x42", 32)),
            'backup_targets.work_dir' => $this->base . '/work',
            'backup_targets.files_root' => $this->base . '/files',
            'backup_targets.db_connection' => 'backup_test_sqlite',
            'database.connections.backup_test_sqlite' => ['driver' => 'sqlite', 'database' => $dbFile],
        ]);
    }

    protected function tearDown(): void {
        exec('rm -rf ' . escapeshellarg($this->base));
        parent::tearDown();
    }

    /** Erzeugt eine committete Generation im Fake-Ziel. */
    private function committedGeneration(): BackupGeneration {
        $result = app(BackupRunService::class)->run(adapter: $this->adapter);
        \PHPUnit\Framework\Assert::assertSame([], $result['failed']);

        return BackupGeneration::query()->sole();
    }

    public function test_verify_marks_generation_verified(): void {
        $generation = $this->committedGeneration();

        $result = app(BackupVerifyService::class)->run($this->adapter);

        $this->assertSame([$generation->snapshot_uuid], $result['verified']);
        $fresh = $generation->fresh();
        $this->assertSame(BackupGenerationStatus::Verified, $fresh?->status);
        $this->assertNotNull($fresh?->last_verified_at);
    }

    public function test_verify_detects_remote_tampering(): void {
        $generation = $this->committedGeneration();
        $this->adapter->tamper((string) $generation->remote_prefix . '/part-1');

        $result = app(BackupVerifyService::class)->run($this->adapter);

        $this->assertArrayHasKey($generation->snapshot_uuid, $result['failed']);
        $this->assertSame(BackupGenerationStatus::VerifyFailed, $generation->fresh()?->status);
        $this->assertNotNull($this->connection->fresh()?->last_error);
    }

    public function test_verify_detects_commit_signature_break(): void {
        $generation = $this->committedGeneration();
        $this->adapter->tamper((string) $generation->remote_prefix . '/commit.manifest');

        $result = app(BackupVerifyService::class)->run($this->adapter);

        $this->assertArrayHasKey($generation->snapshot_uuid, $result['failed']);
        $this->assertSame(BackupGenerationStatus::VerifyFailed, $generation->fresh()?->status);
    }

    public function test_restore_test_restores_isolated_and_logs_protocol(): void {
        $generation = $this->committedGeneration();
        $targetDir = $this->base . '/restore';

        $result = app(BackupRestoreTestService::class)->run($generation, $targetDir, $this->adapter);

        $this->assertFileExists($targetDir . '/extracted/meta/db.sqlite');
        $this->assertFileExists($targetDir . '/extracted/meta/inventory.json');
        $this->assertFileExists($targetDir . '/extracted/storage/app/beleg.txt');
        $this->assertGreaterThan(0, $result['restored_size']);

        $fresh = $generation->fresh();
        $this->assertNotNull($fresh?->restore_tested_at);
        $this->assertNotNull($fresh?->restore_rpo_seconds);
        $this->assertNotNull($fresh?->restore_rto_seconds);

        $register = RestoreTest::query()->sole();
        $this->assertStringContainsString('cloud-backup:', $register->source);
        $this->assertStringContainsString($generation->snapshot_uuid, (string) $register->scope);
    }

    public function test_restore_test_refuses_non_empty_target_dir(): void {
        $generation = $this->committedGeneration();
        $targetDir = $this->base . '/restore-dirty';
        mkdir($targetDir, 0770, true);
        file_put_contents($targetDir . '/produktion.txt', 'x');

        $this->expectException(BackupPreflightException::class);
        app(BackupRestoreTestService::class)->run($generation, $targetDir, $this->adapter);
    }
}
