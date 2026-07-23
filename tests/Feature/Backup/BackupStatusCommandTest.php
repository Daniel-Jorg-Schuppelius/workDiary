<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupStatusCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Backup;

use App\Models\Backup\BackupTargetConnection;
use App\Models\BackupHeartbeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BackupStatusCommandTest extends TestCase {
    use RefreshDatabase;

    public function test_missing_token_and_heartbeat_fail_with_hints(): void {
        config(['backup.heartbeat_token' => null]);

        $this->artisan('workdiary:backup:status')
            ->expectsOutputToContain('workdiary:backup:rotate-token')
            ->expectsOutputToContain('Noch kein Backup registriert')
            ->assertExitCode(1);
    }

    public function test_healthy_script_backup_without_cloud_targets_passes(): void {
        config(['backup.heartbeat_token' => 'token', 'backup_targets.master_key' => null]);
        BackupHeartbeat::create(['occurred_at' => Carbon::now()->subHours(2), 'source' => 'testhost']);

        $this->artisan('workdiary:backup:status')
            ->expectsOutputToContain('Letzter Heartbeat')
            ->expectsOutputToContain('erst nötig, wenn Ziele verbunden werden')
            ->assertExitCode(0);
    }

    public function test_stale_heartbeat_is_flagged_overdue(): void {
        config(['backup.heartbeat_token' => 'token', 'backup.heartbeat_freshness_hours' => 26]);
        BackupHeartbeat::create(['occurred_at' => Carbon::now()->subDays(3), 'source' => 'testhost']);

        $this->artisan('workdiary:backup:status')
            ->expectsOutputToContain('ÜBERFÄLLIG')
            ->assertExitCode(1);
    }

    public function test_connected_target_without_master_key_fails_with_generate_hint(): void {
        config(['backup.heartbeat_token' => 'token', 'backup_targets.master_key' => null]);
        BackupHeartbeat::create(['occurred_at' => Carbon::now()->subHours(2), 'source' => 'testhost']);
        BackupTargetConnection::factory()->active()->create();

        $this->artisan('workdiary:backup:status')
            ->expectsOutputToContain('workdiary:backup:generate-master-key')
            ->assertExitCode(1);
    }

    public function test_master_key_without_recovery_key_hints_at_recovery_generation(): void {
        config([
            'backup.heartbeat_token' => 'token',
            'backup_targets.master_key' => base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)),
            'backup_targets.recovery_public_key' => null,
        ]);
        BackupHeartbeat::create(['occurred_at' => Carbon::now()->subHours(2), 'source' => 'testhost']);

        $this->artisan('workdiary:backup:status')
            ->expectsOutputToContain('workdiary:backup:generate-recovery-key')
            ->assertExitCode(0);
    }
}
