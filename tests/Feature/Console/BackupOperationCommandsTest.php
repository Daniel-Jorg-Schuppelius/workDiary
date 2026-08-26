<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupOperationCommandsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Console;

use App\Enums\Operations\OperationsTaskType;
use App\Models\Backup\BackupGeneration;
use App\Models\OperationsTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Queue, Storage};
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Vollscan 2026-08-23, D7 (MVP-725): die drei Cloud-Backup-Läufe des
 * Schedulers. Sie fassen echte Zieldateien an — getestet werden deshalb
 * ausschließlich die Pfade VOR dem Upload/Download: fehlender Hauptschlüssel,
 * leerer Bestand, fehlende Pflichtangaben. Der Upload-Pfad selbst hängt an
 * BackupRunServiceTest/BackupVerifyRestoreTest mit Attrappen-Ziel.
 */
class BackupOperationCommandsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        // Betriebsaufgaben hängen an einer Organisation (System-Org).
        $this->setUpOrganization();
        Storage::fake('local');
        Queue::fake();
    }

    // ── workdiary:backup:run ─────────────────────────────────────────────

    public function test_backup_run_fails_and_raises_a_task_without_master_key(): void {
        config(['backup_targets.master_key' => null]);

        $this->artisan('workdiary:backup:run')
            ->expectsOutputToContain('Backup-Lauf abgebrochen')
            ->assertExitCode(1);

        // Der Fehlschlag darf nicht nur im Log stehen: Betriebsaufgabe (MVP-056).
        $this->assertDatabaseHas('operations_tasks', [
            'type' => OperationsTaskType::BackupFailed->value,
            'dedupe_key' => 'backup_target_failed',
        ]);
    }

    public function test_backup_run_succeeds_without_configured_targets(): void {
        config(['backup_targets.master_key' => base64_encode(str_repeat("\x42", 32))]);

        $this->artisan('workdiary:backup:run')->assertExitCode(0);

        $this->assertSame(0, OperationsTask::query()->where('dedupe_key', 'backup_target_failed')->count());
    }

    // ── workdiary:backup:verify ──────────────────────────────────────────

    public function test_backup_verify_resolves_the_alert_when_nothing_is_pending(): void {
        $this->artisan('workdiary:backup:verify')->assertExitCode(0);

        $this->assertSame(
            0,
            OperationsTask::query()
                ->where('dedupe_key', 'backup_target_verify_failed')
                ->whereNull('resolved_at')
                ->count(),
        );
    }

    // ── workdiary:backup:restore-test ────────────────────────────────────

    public function test_restore_test_requires_an_isolated_target_directory(): void {
        $this->artisan('workdiary:backup:restore-test')
            ->expectsOutputToContain('--target-dir ist Pflicht')
            ->assertExitCode(2);
    }

    public function test_restore_test_reports_a_missing_generation(): void {
        $this->artisan('workdiary:backup:restore-test', [
            '--target-dir' => storage_path('framework/testing/restore-' . bin2hex(random_bytes(4))),
        ])
            ->expectsOutputToContain('Keine passende Generation gefunden.')
            ->assertExitCode(2);
    }

    public function test_restore_test_reports_an_unknown_snapshot_uuid(): void {
        BackupGeneration::factory()->committed()->create();

        $this->artisan('workdiary:backup:restore-test', [
            '--generation' => 'gibt-es-nicht',
            '--target-dir' => storage_path('framework/testing/restore-' . bin2hex(random_bytes(4))),
        ])
            ->expectsOutputToContain('Keine passende Generation gefunden.')
            ->assertExitCode(2);
    }
}
