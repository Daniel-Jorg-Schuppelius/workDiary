<?php
/*
 * Created on   : Sat Nov 22 2025
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupCheckRestoreCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Backup;

use App\Console\Commands\BackupCheckRestoreCommand;
use App\Models\{AuditLog, BackupHeartbeat};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupCheckRestoreCommandTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        config()->set('backup.thresholds_hours', ['warn' => 26, 'critical' => 72]);
        config()->set('backup.min_size_bytes', 0);
        config()->set('backup.size_drop_ratio', 0.5);
    }

    public function test_missing_heartbeat_yields_critical_exit_code(): void {
        $exit = $this->artisan('workdiary:backup:check-restore')->run();

        $this->assertSame(BackupCheckRestoreCommand::EXIT_CRITICAL, $exit);

        $audit = AuditLog::query()->where('event', 'backup.checkRestore')->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertSame('critical', $audit->changes['status'] ?? null);
        $this->assertArrayHasKey('last_backup_at', $audit->changes);
        $this->assertNull($audit->changes['last_backup_at']);
    }

    public function test_recent_heartbeat_yields_ok(): void {
        BackupHeartbeat::create([
            'occurred_at' => CarbonImmutable::now()->subHours(2),
            'size_bytes' => 5_000_000,
            'manifest_hash' => str_repeat('a', 64),
            'source' => 'phpunit',
        ]);

        $exit = $this->artisan('workdiary:backup:check-restore')->run();

        $this->assertSame(BackupCheckRestoreCommand::EXIT_OK, $exit);

        $audit = AuditLog::query()->where('event', 'backup.checkRestore')->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertSame('ok', $audit->changes['status'] ?? null);
        $this->assertSame(5_000_000, $audit->changes['size_bytes'] ?? null);
    }

    public function test_stale_heartbeat_yields_warn(): void {
        BackupHeartbeat::create([
            'occurred_at' => CarbonImmutable::now()->subHours(30),
            'size_bytes' => 5_000_000,
            'source' => 'phpunit',
        ]);

        $exit = $this->artisan('workdiary:backup:check-restore')->run();

        $this->assertSame(BackupCheckRestoreCommand::EXIT_WARN, $exit);
        $audit = AuditLog::query()->where('event', 'backup.checkRestore')->latest('id')->first();
        $this->assertSame('warn', $audit->changes['status'] ?? null);
    }

    public function test_very_old_heartbeat_yields_critical(): void {
        BackupHeartbeat::create([
            'occurred_at' => CarbonImmutable::now()->subHours(100),
            'size_bytes' => 5_000_000,
            'source' => 'phpunit',
        ]);

        $exit = $this->artisan('workdiary:backup:check-restore')->run();

        $this->assertSame(BackupCheckRestoreCommand::EXIT_CRITICAL, $exit);
        $audit = AuditLog::query()->where('event', 'backup.checkRestore')->latest('id')->first();
        $this->assertSame('critical', $audit->changes['status'] ?? null);
    }

    public function test_below_min_size_yields_warn(): void {
        config()->set('backup.min_size_bytes', 1_000_000);

        BackupHeartbeat::create([
            'occurred_at' => CarbonImmutable::now()->subHour(),
            'size_bytes' => 100_000,
            'source' => 'phpunit',
        ]);

        $exit = $this->artisan('workdiary:backup:check-restore')->run();

        $this->assertSame(BackupCheckRestoreCommand::EXIT_WARN, $exit);
        $audit = AuditLog::query()->where('event', 'backup.checkRestore')->latest('id')->first();
        $this->assertSame('warn', $audit->changes['status'] ?? null);
    }

    public function test_size_drop_against_median_yields_warn(): void {
        $base = CarbonImmutable::now()->subDays(7);
        foreach (range(1, 6) as $i) {
            BackupHeartbeat::create([
                'occurred_at' => $base->addDays($i),
                'size_bytes' => 10_000_000,
                'source' => 'phpunit',
            ]);
        }
        BackupHeartbeat::create([
            'occurred_at' => CarbonImmutable::now()->subMinutes(30),
            'size_bytes' => 2_000_000,
            'source' => 'phpunit',
        ]);

        $exit = $this->artisan('workdiary:backup:check-restore')->run();

        $this->assertSame(BackupCheckRestoreCommand::EXIT_WARN, $exit);
        $audit = AuditLog::query()->where('event', 'backup.checkRestore')->latest('id')->first();
        $this->assertSame('warn', $audit->changes['status'] ?? null);
        $this->assertSame(10_000_000, $audit->changes['median_size_bytes'] ?? null);
    }

    public function test_json_output_is_machine_readable(): void {
        BackupHeartbeat::create([
            'occurred_at' => CarbonImmutable::now()->subHour(),
            'size_bytes' => 5_000_000,
            'source' => 'phpunit',
        ]);

        $this->artisan('workdiary:backup:check-restore', ['--json' => true])
            ->expectsOutputToContain('"status":"ok"')
            ->assertExitCode(BackupCheckRestoreCommand::EXIT_OK);
    }
}
