<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupRunServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Backup;

use App\Enums\Backup\{BackupGenerationStatus, BackupRetentionClass};
use App\Models\Backup\{BackupGeneration, BackupTargetConnection};
use App\Services\Backup\BackupRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\FakeBackupTarget;
use Tests\TestCase;

/**
 * Orchestrierung Phase 32 (MVP-364): voller Lauf mit pseudonymisierten
 * Remote-Namen + signiertem Commit, Quota-Preflight, Wiederaufnahme über
 * die parts-Tabelle, Retention-Matrix (verifiziert/Legal-Hold/letzte
 * restorable), Zeitklassen-Zuordnung.
 */
class BackupRunServiceTest extends TestCase {
    use RefreshDatabase;

    private FakeBackupTarget $adapter;

    private BackupTargetConnection $connection;

    private string $base;

    protected function setUp(): void {
        parent::setUp();
        $this->adapter = new FakeBackupTarget();
        $this->connection = BackupTargetConnection::factory()->active()->create();

        $this->base = sys_get_temp_dir() . '/wd-backup-run-' . uniqid();
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

    private function service(): BackupRunService {
        return app(BackupRunService::class);
    }

    public function test_full_run_commits_generation_with_pseudonymized_names(): void {
        $result = $this->service()->run(adapter: $this->adapter);

        $this->assertSame([], $result['failed']);
        $generation = BackupGeneration::query()->sole();
        $this->assertSame(BackupGenerationStatus::Committed, $generation->status);
        $this->assertSame(1, $generation->part_count);
        $this->assertNotNull($generation->key_envelope);
        $this->assertNotNull($generation->manifest_sha256);

        // Remote-Namen tragen das Pseudonym, nie Kunden-/DB-Namen.
        $paths = array_keys($this->adapter->files);
        sort($paths);
        $this->assertCount(2, $paths); // part-1 + commit.manifest
        foreach ($paths as $path) {
            $this->assertMatchesRegularExpression('#^wd-[a-z0-9]+/' . preg_quote($generation->snapshot_uuid, '#') . '/#', $path);
        }
        $this->assertStringEndsWith('/commit.manifest', $paths[0]);
        $this->assertStringEndsWith('/part-1', $paths[1]);

        // Arbeitsverzeichnis abgeräumt, Health zurückgesetzt.
        $this->assertDirectoryDoesNotExist($this->base . '/work/' . $generation->snapshot_uuid);
        $this->assertNull($this->connection->fresh()?->last_error);
    }

    public function test_quota_shortage_fails_run_and_records_error(): void {
        $this->adapter->quotaTotal = 1000;
        $this->adapter->quotaUsed = 990;

        $result = $this->service()->run(adapter: $this->adapter);

        $this->assertArrayHasKey($this->connection->name, $result['failed']);
        $generation = BackupGeneration::query()->sole();
        $this->assertSame(BackupGenerationStatus::Uploading, $generation->status);
        $this->assertStringContainsString('Quota', (string) $generation->last_error);
        $this->assertNotNull($this->connection->fresh()?->last_error);
        $this->assertSame([], array_keys($this->adapter->files));
    }

    public function test_interrupted_upload_resumes_without_rebuilding(): void {
        $this->adapter->failUploads = 1;
        $first = $this->service()->run(adapter: $this->adapter);
        $this->assertNotSame([], $first['failed']);
        $generation = BackupGeneration::query()->sole();
        $this->assertSame(BackupGenerationStatus::Uploading, $generation->status);

        $second = $this->service()->run(adapter: $this->adapter);

        $this->assertSame([], $second['failed']);
        $this->assertSame(1, BackupGeneration::query()->count()); // KEINE neue Generation
        $this->assertSame(BackupGenerationStatus::Committed, $generation->fresh()?->status);
        $this->assertCount(2, $this->adapter->files);
    }

    public function test_retention_deletes_only_verified_beyond_keep(): void {
        config(['backup_targets.retention.daily' => 2]);
        $mk = function (int $daysAgo, BackupGenerationStatus $status, bool $hold = false): BackupGeneration {
            return BackupGeneration::factory()->committed()->create([
                'connection_id' => $this->connection->id,
                'status' => $status,
                'retention_class' => BackupRetentionClass::Daily,
                'legal_hold' => $hold,
                'started_at' => now()->subDays($daysAgo),
                'remote_prefix' => 'wd-test/gen-' . $daysAgo,
            ]);
        };
        $a = $mk(1, BackupGenerationStatus::Verified);
        $b = $mk(2, BackupGenerationStatus::Committed);
        $c = $mk(3, BackupGenerationStatus::Verified);
        $d = $mk(4, BackupGenerationStatus::Verified, hold: true);
        $e = $mk(5, BackupGenerationStatus::Verified);
        foreach ([$c, $d, $e] as $gen) {
            $this->adapter->files[trim((string) $gen->remote_prefix, '/') . '/part-1'] = 'x';
        }

        $this->service()->applyRetention($this->adapter, $this->connection);

        $this->assertNotNull($a->fresh()); // im Behalte-Fenster
        $this->assertNotNull($b->fresh()); // im Behalte-Fenster
        $this->assertNull($c->fresh());    // verifiziert + über Limit → weg
        $this->assertNotNull($d->fresh()); // Legal Hold → bleibt
        $this->assertNull($e->fresh());    // verifiziert + über Limit → weg
        // Remote-Ordner der gelöschten Generationen sind geräumt.
        $this->assertArrayNotHasKey('wd-test/gen-3/part-1', $this->adapter->files);
        $this->assertArrayHasKey('wd-test/gen-4/part-1', $this->adapter->files);
    }

    public function test_retention_never_deletes_last_restorable_generation(): void {
        config(['backup_targets.retention.daily' => 1]);
        $newest = BackupGeneration::factory()->committed()->create([
            'connection_id' => $this->connection->id,
            'retention_class' => BackupRetentionClass::Daily,
            'started_at' => now()->subDays(1),
        ]);
        $onlyVerified = BackupGeneration::factory()->verified()->create([
            'connection_id' => $this->connection->id,
            'retention_class' => BackupRetentionClass::Daily,
            'started_at' => now()->subDays(9),
        ]);

        $this->service()->applyRetention($this->adapter, $this->connection);

        $this->assertNotNull($newest->fresh());
        // Jüngste verifizierte = letzte als restorable bestätigte → bleibt trotz Limit.
        $this->assertNotNull($onlyVerified->fresh());
    }

    public function test_retention_class_depends_on_calendar(): void {
        $service = $this->service();

        $this->assertSame(BackupRetentionClass::Monthly, $service->retentionClassFor(Carbon::parse('2026-07-01')));
        $this->assertSame(BackupRetentionClass::Weekly, $service->retentionClassFor(Carbon::parse('2026-07-13'))); // Montag
        $this->assertSame(BackupRetentionClass::Daily, $service->retentionClassFor(Carbon::parse('2026-07-14')));
    }
}
