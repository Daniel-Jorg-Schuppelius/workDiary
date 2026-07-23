<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupRecordRestoreTestCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Backup;

use App\Enums\Backup\RestoreTestResult;
use App\Models\{BackupHeartbeat, RestoreTest};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BackupRecordRestoreTestCommandTest extends TestCase {
    use RefreshDatabase;

    public function test_records_passed_entry_with_metrics(): void {
        $this->artisan('workdiary:backup:record-restore-test', [
            '--source' => 'script-backup:workdiary',
            '--scope' => 'DB+Storage+.env, Stand 20260723_230000',
            '--restored-size-bytes' => 123456,
            '--duration-minutes' => 7,
            '--notes' => '42 User, 310 Migrationen',
        ])->assertExitCode(0);

        $test = RestoreTest::query()->sole();
        $this->assertSame('script-backup:workdiary', $test->source);
        $this->assertSame(RestoreTestResult::Passed, $test->result);
        $this->assertSame(123456, $test->restored_size_bytes);
        $this->assertSame(7, $test->duration_minutes);
        $this->assertNull($test->performed_by_user_id);
        $this->assertTrue($test->tested_on->isToday());
    }

    public function test_rejects_invalid_result_and_future_date(): void {
        $this->artisan('workdiary:backup:record-restore-test', ['--result' => 'super'])->assertExitCode(2);
        $this->artisan('workdiary:backup:record-restore-test', ['--tested-on' => Carbon::tomorrow()->toDateString()])
            ->assertExitCode(2);

        $this->assertSame(0, RestoreTest::query()->count());
    }

    public function test_recorded_failed_run_is_stored_as_failed(): void {
        $this->artisan('workdiary:backup:record-restore-test', [
            '--result' => 'failed',
            '--notes' => 'restore-test.sh: fehlgeschlagen bei DB-Dump einspielen',
        ])->assertExitCode(0);

        $this->assertSame(RestoreTestResult::Failed, RestoreTest::query()->sole()->result);
    }

    public function test_status_command_turns_green_after_recorded_test(): void {
        config(['backup.heartbeat_token' => 'token', 'backup_targets.master_key' => null]);
        BackupHeartbeat::create(['occurred_at' => Carbon::now()->subHours(2), 'source' => 'testhost']);

        $this->artisan('workdiary:backup:record-restore-test')->assertExitCode(0);

        $this->artisan('workdiary:backup:status')
            ->expectsOutputToContain('Letzter erfolgreicher Restore-Test')
            ->assertExitCode(0);
    }
}
