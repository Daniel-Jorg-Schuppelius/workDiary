<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupSnapshotBuilderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Backup;

use App\Services\Backup\BackupSnapshotBuilder;
use App\Services\Backup\Exceptions\BackupPreflightException;
use Tests\TestCase;

/**
 * Snapshot-Erstellung Phase 32 (MVP-362) offline: Preflight-Fehlerbilder,
 * SQLite-Dump + tar-Aufbau + Teil-Split — ohne Cloudzugriff.
 */
class BackupSnapshotBuilderTest extends TestCase {
    private const UUID = '01890a5d-ac96-774b-bcce-b302099a8058';

    private string $workDir;

    private string $dbFile;

    protected function setUp(): void {
        parent::setUp();
        $base = sys_get_temp_dir() . '/wd-backup-builder-' . uniqid();
        $this->workDir = $base . '/work';
        $this->dbFile = $base . '/source.sqlite';
        mkdir($base, 0770, true);

        $pdo = new \PDO('sqlite:' . $this->dbFile);
        $pdo->exec('CREATE TABLE demo (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec("INSERT INTO demo (name) VALUES ('workdiary')");
        unset($pdo);

        // Kleine Datei-Quelle statt des echten storage/app (Testlaufzeit).
        mkdir($base . '/files/storage/app', 0770, true);
        file_put_contents($base . '/files/storage/app/beleg.txt', str_repeat('workdiary ', 200_000));

        config([
            'backup_targets.work_dir' => $this->workDir,
            'backup_targets.files_root' => $base . '/files',
            'backup_targets.db_connection' => 'backup_test_sqlite',
            'database.connections.backup_test_sqlite' => ['driver' => 'sqlite', 'database' => $this->dbFile],
        ]);
    }

    protected function tearDown(): void {
        (new BackupSnapshotBuilder())->cleanup(self::UUID);
        $base = dirname($this->dbFile);
        @unlink($this->dbFile);
        @unlink($base . '/files/storage/app/beleg.txt');
        @rmdir($base . '/files/storage/app');
        @rmdir($base . '/files/storage');
        @rmdir($base . '/files');
        @rmdir($this->workDir);
        @rmdir($base);
        parent::tearDown();
    }

    public function test_build_creates_tar_with_db_dump_and_inventory(): void {
        $builder = new BackupSnapshotBuilder();
        $result = $builder->build(self::UUID);

        $this->assertFileExists($result['tar_path']);
        $this->assertGreaterThan(0, $result['plain_size']);

        $listing = shell_exec('tar -tf ' . escapeshellarg($result['tar_path']));
        $this->assertIsString($listing);
        $this->assertStringContainsString('meta/db.sqlite', $listing);
        $this->assertStringContainsString('meta/inventory.json', $listing);
        $this->assertStringContainsString('storage/app', $listing);
    }

    public function test_split_parts_covers_whole_archive_in_order(): void {
        $builder = new BackupSnapshotBuilder();
        $result = $builder->build(self::UUID);

        $parts = $builder->splitParts($result['tar_path'], 1_048_576);
        $this->assertNotEmpty($parts);

        $joined = '';
        foreach ($parts as $part) {
            $joined .= (string) file_get_contents($part);
        }
        $this->assertSame(hash_file('sha256', $result['tar_path']), hash('sha256', $joined));
    }

    public function test_preflight_rejects_memory_sqlite(): void {
        config(['database.connections.backup_test_sqlite.database' => ':memory:']);

        $this->expectException(BackupPreflightException::class);
        (new BackupSnapshotBuilder())->preflight();
    }

    public function test_preflight_reports_missing_binary(): void {
        config([
            'backup_targets.db_connection' => 'backup_test_mysql',
            'database.connections.backup_test_mysql' => ['driver' => 'mysql', 'database' => 'x'],
            'backup_targets.binaries.mysqldump' => '/does/not/exist/mysqldump',
        ]);

        $this->expectException(BackupPreflightException::class);
        $this->expectExceptionMessageMatches('/mysqldump/');
        (new BackupSnapshotBuilder())->preflight();
    }

    public function test_preflight_rejects_unsupported_driver(): void {
        config([
            'backup_targets.db_connection' => 'backup_test_odd',
            'database.connections.backup_test_odd' => ['driver' => 'sqlsrv', 'database' => 'x'],
        ]);

        $this->expectException(BackupPreflightException::class);
        (new BackupSnapshotBuilder())->preflight();
    }
}
