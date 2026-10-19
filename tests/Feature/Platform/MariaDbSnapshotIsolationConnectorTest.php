<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MariaDbSnapshotIsolationConnectorTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Platform;

use App\Support\Database\{MariaDbConnector, MariaDbSnapshotIsolation, MySqlConnector};
use Illuminate\Foundation\Testing\RefreshDatabase;
use PDO;
use PDOException;
use Tests\TestCase;

/**
 * Befund 2026-10-19: MariaDB ≥ 11.6.2 schaltet innodb_snapshot_isolation ein,
 * damit scheiterte der Audit-Insert (erst exists(), dann FOR UPDATE auf den
 * Kettenkopf) mit Fehler 1020, sobald ein zweiter Prozess dieselbe Kette
 * fortschrieb. Die Verbindungs-Tests laufen nur gegen einen MariaDB-Server,
 * der die Variable kennt (lokal 10.11.14); unter SQLite (CI) werden sie
 * übersprungen.
 */
final class MariaDbSnapshotIsolationConnectorTest extends TestCase {
    use RefreshDatabase;

    public function test_app_connectors_are_bound_for_both_mysql_drivers(): void {
        $this->assertInstanceOf(MySqlConnector::class, app('db.connector.mysql'));
        $this->assertInstanceOf(MariaDbConnector::class, app('db.connector.mariadb'));
    }

    public function test_new_connection_runs_without_snapshot_isolation(): void {
        $pdo = $this->connect();
        $this->assertSame(0, $this->snapshotIsolation($pdo));

        // Vom Server eingeschaltet (Standard ab 11.6.2) — der Connector-Weg schaltet ab.
        $pdo->exec('SET SESSION innodb_snapshot_isolation = ON');
        $this->assertSame(1, $this->snapshotIsolation($pdo));
        $this->assertTrue(MariaDbSnapshotIsolation::disable($pdo));
        $this->assertSame(0, $this->snapshotIsolation($pdo));
    }

    public function test_locking_read_after_a_plain_read_waits_and_sees_the_latest_row(): void {
        $reader = $this->connect();
        $writer = $this->connect();
        $chain = $this->probeChain($writer);

        try {
            $reader->beginTransaction();
            $reader->query("SELECT 1 FROM audit_chain_heads WHERE chain = '{$chain}'")->fetchColumn();
            // Zweiter Prozess schreibt den Kopf derselben Kette fort und committet.
            $writer->exec("UPDATE audit_chain_heads SET head_hash = 'a1', height = 1 WHERE chain = '{$chain}'");

            $row = $reader->query("SELECT height FROM audit_chain_heads WHERE chain = '{$chain}' FOR UPDATE")->fetch(PDO::FETCH_ASSOC);
            $reader->rollBack();

            $this->assertIsArray($row);
            $this->assertSame(1, (int) $row['height'], 'Die sperrende Leseabfrage muss den committeten Stand liefern.');
        } finally {
            $this->cleanUp($reader, $writer, $chain);
        }
    }

    public function test_the_same_sequence_fails_with_error_1020_when_snapshot_isolation_is_on(): void {
        // Gegenprobe: so verhält sich der Server ohne den Connector.
        $reader = $this->connect();
        $writer = $this->connect();
        $chain = $this->probeChain($writer);
        $reader->exec('SET SESSION innodb_snapshot_isolation = ON');

        try {
            $reader->beginTransaction();
            $reader->query("SELECT 1 FROM audit_chain_heads WHERE chain = '{$chain}'")->fetchColumn();
            $writer->exec("UPDATE audit_chain_heads SET head_hash = 'a1', height = 1 WHERE chain = '{$chain}'");

            try {
                $reader->query("SELECT height FROM audit_chain_heads WHERE chain = '{$chain}' FOR UPDATE")->fetch(PDO::FETCH_ASSOC);
                $this->fail('MariaDB mit innodb_snapshot_isolation=ON müsste die sperrende Leseabfrage abweisen.');
            } catch (PDOException $e) {
                $this->assertSame(1020, $e->errorInfo[1] ?? null, $e->getMessage());
            }
        } finally {
            $this->cleanUp($reader, $writer, $chain);
        }
    }

    /** Neue Verbindung über den gebundenen Connector — der Weg, den jede App-Verbindung nimmt. */
    private function connect(): PDO {
        $name = (string) config('database.default');
        /** @var array<string, mixed> $config */
        $config = config("database.connections.{$name}");
        $driver = (string) ($config['driver'] ?? '');
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Nur gegen MySQL/MariaDB.');
        }

        $pdo = app("db.connector.{$driver}")->connect($config);
        if (! MariaDbSnapshotIsolation::isKnownTo((string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION))) {
            $this->markTestSkipped('Der Server kennt innodb_snapshot_isolation nicht.');
        }

        return $pdo;
    }

    private function snapshotIsolation(PDO $pdo): int {
        return (int) $pdo->query('SELECT @@innodb_snapshot_isolation')->fetchColumn();
    }

    private function probeChain(PDO $writer): string {
        $chain = 'probe:snapshot-isolation-' . getmypid();
        $writer->exec("DELETE FROM audit_chain_heads WHERE chain = '{$chain}'");
        $writer->exec("INSERT INTO audit_chain_heads (chain, head_hash, height) VALUES ('{$chain}', NULL, 0)");

        return $chain;
    }

    private function cleanUp(PDO $reader, PDO $writer, string $chain): void {
        if ($reader->inTransaction()) {
            $reader->rollBack();
        }
        $writer->exec("DELETE FROM audit_chain_heads WHERE chain = '{$chain}'");
    }
}
