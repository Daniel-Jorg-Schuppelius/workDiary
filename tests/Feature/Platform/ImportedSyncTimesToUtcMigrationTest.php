<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImportedSyncTimesToUtcMigrationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Platform;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-824: importierte Zeiten aus CSV (Toggl/Kimai/Clockify) und der
 * Kimai-API wandern nach UTC. Exportierte, gesperrte und von Hand
 * umgestellte Zeiten bleiben; down() stellt genau die markierten her.
 */
class ImportedSyncTimesToUtcMigrationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private int $userId;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['timezone' => 'Europe/Berlin']);
        $this->userId = (int) $this->orgUser()->id;
    }

    private function migration(): Migration {
        return require database_path('migrations/2027_02_22_101200_convert_imported_sync_times_to_utc.php');
    }

    /** @param array<string, mixed> $entry */
    private function imported(string $plugin, string $key, array $entry = [], string $importedAt = '2026-06-10 12:00:00'): int {
        $id = (int) DB::table('time_entries')->insertGetId($entry + [
            'organization_id' => $this->organization->id,
            'user_id' => $this->userId,
            'date' => '2026-06-02',
            'started_at' => '2026-06-02 09:00:00',
            'ended_at' => '2026-06-02 10:30:00',
            'minutes' => 90,
        ]);
        DB::table('external_references')->insert([
            'organization_id' => $this->organization->id,
            'plugin_id' => $plugin,
            'external_type' => 'entry',
            'referenceable_type' => 'App\\Models\\TimeEntry',
            'referenceable_id' => $id,
            'external_id' => $key,
            'payload' => json_encode(['source' => str_starts_with($key, 'csv:') ? 'csv' : 'api']),
            'synced_at' => $importedAt,
            'created_at' => $importedAt,
        ]);

        return $id;
    }

    private function retime(int $entryId, string $at): void {
        DB::table('audit_logs')->insert([
            'organization_id' => $this->organization->id,
            'user_id' => $this->userId,
            'event' => 'updated',
            'auditable_type' => 'App\\Models\\TimeEntry',
            'auditable_id' => $entryId,
            'changes' => json_encode(['before' => ['started_at' => 'x'], 'after' => ['started_at' => 'y']]),
            'created_at' => $at,
        ]);
    }

    private function start(int $entryId): string {
        return (string) DB::table('time_entries')->where('id', $entryId)->value('started_at');
    }

    public function test_imported_wall_times_move_to_utc_unless_fixed(): void {
        $csv = $this->imported('toggl', 'csv:a');
        $kimaiApi = $this->imported('kimai', 'api:7');
        $togglApi = $this->imported('toggl', 'api:8');
        $exported = $this->imported('kimai', 'csv:b', ['exported' => true]);
        $colleague = (int) $this->orgUser()->id;
        $locked = $this->imported('clockify', 'csv:c', ['user_id' => $colleague]);
        DB::table('month_closures')->insert([
            'organization_id' => $this->organization->id, 'user_id' => $colleague,
            'period_year' => 2026, 'period_month' => 6, 'status' => 'approved',
        ]);
        $retimed = $this->imported('clockify', 'csv:d');
        $this->retime($retimed, '2026-06-20 08:00:00');
        $resynced = $this->imported('kimai', 'csv:e');
        DB::table('external_references')->where('external_id', 'csv:e')->update(['synced_at' => '2026-06-25 08:00:02']);
        $this->retime($resynced, '2026-06-25 08:00:00');

        $migration = $this->migration();
        $migration->up();

        $this->assertSame('2026-06-02 07:00:00', $this->start($csv));
        $this->assertSame('2026-06-02 07:00:00', $this->start($kimaiApi));
        $this->assertSame('2026-06-02 09:00:00', $this->start($togglApi), 'Toggl-API lieferte schon UTC.');
        $this->assertSame('2026-06-02 09:00:00', $this->start($exported));
        $this->assertSame('2026-06-02 09:00:00', $this->start($locked));
        $this->assertSame('2026-06-02 09:00:00', $this->start($retimed), 'Von Hand umgestellt = schon UTC.');
        $this->assertSame('2026-06-02 07:00:00', $this->start($resynced), 'Abgleich per Upload ist Wanduhr.');
        $this->assertSame('2026-06-02', (string) DB::table('time_entries')->where('id', $csv)->value('date'));

        $migration->down();

        $this->assertSame('2026-06-02 09:00:00', $this->start($csv));
        $this->assertSame('2026-06-02 09:00:00', $this->start($resynced));
        $this->assertSame('2026-06-02 09:00:00', $this->start($retimed));
    }

    public function test_second_run_is_a_no_op_and_down_removes_the_mark(): void {
        $csv = $this->imported('toggl', 'csv:a');

        $migration = $this->migration();
        $migration->up();
        $migration->up();

        $this->assertSame('2026-06-02 07:00:00', $this->start($csv));

        $migration->down();

        $this->assertSame('2026-06-02 09:00:00', $this->start($csv));
        $payload = json_decode((string) DB::table('external_references')->where('external_id', 'csv:a')->value('payload'), true);
        $this->assertSame(['source' => 'csv'], $payload);
    }

    public function test_open_inbox_snapshots_of_csv_imports_move(): void {
        $insert = fn(string $key, string $status): int => (int) DB::table('integration_inbox_items')->insertGetId([
            'organization_id' => $this->organization->id,
            'plugin_id' => 'kimai',
            'source' => 'csv',
            'target_type' => 'App\\Models\\TimeEntry',
            'external_type' => 'entry',
            'dedupe_key' => 'entry:' . $key,
            'case_type' => 'unmatched',
            'status' => $status,
            'remote_snapshot' => json_encode(['entry_key' => $key, 'started_at' => '2026-06-02T09:00:00+00:00', 'ended_at' => '2026-06-02T10:30:00+00:00']),
            'occurred_at' => '2026-06-02 09:00:00',
        ]);
        $open = $insert('csv:a', 'open');
        $api = $insert('api:1', 'open');
        $resolved = $insert('csv:b', 'resolved_created');

        $migration = $this->migration();
        $migration->up();

        $snapshot = fn(int $id): array => (array) json_decode((string) DB::table('integration_inbox_items')->where('id', $id)->value('remote_snapshot'), true);
        $this->assertSame('2026-06-02T07:00:00+00:00', $snapshot($open)['started_at']);
        $this->assertSame('2026-06-02 07:00:00', (string) DB::table('integration_inbox_items')->where('id', $open)->value('occurred_at'));
        $this->assertSame('2026-06-02T09:00:00+00:00', $snapshot($api)['started_at']);
        $this->assertSame('2026-06-02T09:00:00+00:00', $snapshot($resolved)['started_at']);

        $migration->down();

        $this->assertSame('2026-06-02T09:00:00+00:00', $snapshot($open)['started_at']);
        $this->assertArrayNotHasKey('utc_migrated_mvp824', $snapshot($open));
    }
}
