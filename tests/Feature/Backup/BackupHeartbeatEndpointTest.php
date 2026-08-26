<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupHeartbeatEndpointTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Backup;

use App\Models\{AuditLog, BackupHeartbeat};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupHeartbeatEndpointTest extends TestCase {
    use RefreshDatabase;

    private const TOKEN = 'TEST-HEARTBEAT-TOKEN-1234567890ABCDEF';

    protected function setUp(): void {
        parent::setUp();
        config(['backup.heartbeat_token' => self::TOKEN]);
    }

    public function test_returns_401_without_token(): void {
        $response = $this->postJson('/admin/backup/heartbeat', [
            'manifest_sha256' => str_repeat('a', 64),
        ]);

        $response->assertStatus(401);
        $this->assertSame(0, BackupHeartbeat::query()->count());
    }

    public function test_returns_401_with_wrong_token(): void {
        $response = $this->withHeaders(['Authorization' => 'Bearer wrong-token'])
            ->postJson('/admin/backup/heartbeat', [
                'manifest_sha256' => str_repeat('a', 64),
            ]);

        $response->assertStatus(401);
        $this->assertSame(0, BackupHeartbeat::query()->count());
    }

    public function test_returns_503_when_token_not_configured(): void {
        config(['backup.heartbeat_token' => null]);

        $response = $this->withHeaders(['Authorization' => 'Bearer anything'])
            ->postJson('/admin/backup/heartbeat', []);

        $response->assertStatus(503);
    }

    public function test_creates_heartbeat_and_audit_with_valid_token(): void {
        $hash = str_repeat('b', 64);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::TOKEN])
            ->postJson('/admin/backup/heartbeat', [
                'manifest_sha256' => $hash,
                'size_bytes' => 4096,
                'source' => 'backup-host',
            ]);

        $response->assertStatus(201);

        $this->assertSame(1, BackupHeartbeat::query()->count());
        $row = BackupHeartbeat::query()->first();
        $this->assertNotNull($row);
        $this->assertSame($hash, $row->manifest_hash);
        $this->assertSame(4096, $row->size_bytes?->getBytes());
        $this->assertSame('backup-host', $row->source);

        $audit = AuditLog::query()->where('event', 'backup.heartbeatReceived')->first();
        $this->assertNotNull($audit);
        $this->assertSame(BackupHeartbeat::class, $audit->auditable_type);
        $this->assertSame($row->id, $audit->auditable_id);
    }

    /**
     * Das Pre-Backup von deploy.sh läuft, während die Anwendung wegen der
     * Migration abgeriegelt ist. Bliebe der Endpunkt dann gesperrt, meldete
     * ausgerechnet die Sicherung vor riskanten Änderungen ihren Erfolg nie —
     * und `BackupStatusService` hielte das Backup danach für überfällig.
     */
    public function test_reachable_during_maintenance_mode(): void {
        $this->app->make(\Illuminate\Contracts\Foundation\MaintenanceMode::class)->activate([]);

        try {
            $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::TOKEN])
                ->postJson('/admin/backup/heartbeat', [
                    'manifest_sha256' => str_repeat('c', 64),
                    'size_bytes' => 128,
                    'source' => 'deploy-prebackup',
                ]);

            $response->assertStatus(201);
        } finally {
            $this->app->make(\Illuminate\Contracts\Foundation\MaintenanceMode::class)->deactivate();
        }

        $this->assertSame(1, BackupHeartbeat::query()->count());
    }

    public function test_rejects_invalid_manifest_hash(): void {
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . self::TOKEN])
            ->postJson('/admin/backup/heartbeat', [
                'manifest_sha256' => 'not-a-sha256',
            ]);

        $response->assertStatus(422);
        $this->assertSame(0, BackupHeartbeat::query()->count());
    }
}
