<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupAlertsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Operations;

use App\Models\{BackupHeartbeat, OperationsTask, RestoreTest, User};
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BackupAlertsTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
        User::factory()->admin()->create(); // Empfänger + Betreiber-Org
    }

    private function heartbeat(int $hoursAgo, ?int $sizeBytes = 1000000): BackupHeartbeat {
        return BackupHeartbeat::query()->create([
            'occurred_at' => CarbonImmutable::now()->subHours($hoursAgo),
            'size_bytes' => $sizeBytes,
            'source' => 'test',
        ]);
    }

    public function test_fresh_backup_creates_no_task(): void {
        $this->heartbeat(2);
        RestoreTest::query()->create(['source' => 'manual', 'tested_on' => now()->subDays(10), 'result' => 'passed']);

        Artisan::call('operations:scan');

        $this->assertDatabaseCount('operations_tasks', 0);
    }

    public function test_backup_older_than_warn_threshold_creates_warning_task(): void {
        $this->heartbeat(30);

        Artisan::call('operations:scan');

        $this->assertDatabaseHas('operations_tasks', [
            'dedupe_key' => 'backup_overdue',
            'severity' => 'warning',
            'status' => 'open',
        ]);
    }

    public function test_backup_older_than_critical_threshold_escalates_same_task(): void {
        $this->heartbeat(30);
        Artisan::call('operations:scan');

        BackupHeartbeat::query()->delete();
        $this->heartbeat(80);
        Artisan::call('operations:scan');

        $this->assertSame(1, OperationsTask::query()->where('dedupe_key', 'backup_overdue')->count());
        $this->assertDatabaseHas('operations_tasks', [
            'dedupe_key' => 'backup_overdue',
            'severity' => 'critical',
        ]);
    }

    public function test_new_heartbeat_auto_resolves_overdue_task(): void {
        $this->heartbeat(30);
        Artisan::call('operations:scan');

        $this->heartbeat(1);
        Artisan::call('operations:scan');

        $this->assertDatabaseHas('operations_tasks', [
            'dedupe_key' => 'backup_overdue',
            'status' => 'resolved',
        ]);
    }

    public function test_missing_restore_test_creates_task_only_when_backups_exist(): void {
        Artisan::call('operations:scan');
        $this->assertDatabaseMissing('operations_tasks', ['dedupe_key' => 'restore_test_overdue']);

        $this->heartbeat(2);
        Artisan::call('operations:scan');

        $this->assertDatabaseHas('operations_tasks', [
            'dedupe_key' => 'restore_test_overdue',
            'status' => 'open',
        ]);
    }

    public function test_overdue_restore_test_creates_task_and_recent_resolves(): void {
        $this->heartbeat(2);
        RestoreTest::query()->create(['source' => 'manual', 'tested_on' => now()->subDays(200), 'result' => 'passed']);

        Artisan::call('operations:scan');
        $this->assertDatabaseHas('operations_tasks', ['dedupe_key' => 'restore_test_overdue', 'status' => 'open']);

        RestoreTest::query()->create(['source' => 'manual', 'tested_on' => now()->subDay(), 'result' => 'passed']);
        Artisan::call('operations:scan');

        $this->assertDatabaseHas('operations_tasks', ['dedupe_key' => 'restore_test_overdue', 'status' => 'resolved']);
    }

    public function test_size_drop_reports_backup_failed_and_recovery_resolves(): void {
        // Stabiler Median über mehrere Heartbeats, dann Einbruch.
        foreach ([100, 99, 98, 97, 96] as $i => $hoursAgo) {
            $this->heartbeat($hoursAgo, 1000000);
        }
        $this->heartbeat(1, 100000); // 10 % des Medians

        Artisan::call('workdiary:backup:check-restore');

        $this->assertDatabaseHas('operations_tasks', [
            'dedupe_key' => 'backup_failed',
            'severity' => 'critical',
            'status' => 'open',
        ]);

        $this->heartbeat(0, 1000000);
        Artisan::call('workdiary:backup:check-restore');

        $this->assertDatabaseHas('operations_tasks', [
            'dedupe_key' => 'backup_failed',
            'status' => 'resolved',
        ]);
    }
}
