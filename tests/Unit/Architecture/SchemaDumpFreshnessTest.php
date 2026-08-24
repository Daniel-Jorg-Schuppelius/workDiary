<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SchemaDumpFreshnessTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate für die Schema-Dumps (Vollscan 2026-08-23, F10/G22): Die
 * Dumps unter database/schema lagen 30 Migrationen zurück — jeder
 * RefreshDatabase-Lauf migrierte 20 Buchhaltungstabellen nach, und der
 * PersistedTestDatabase-Fingerprint lief ins Leere. Regel (Memory „Dump nach
 * Migrationen auffrischen"): Die letzte Migrationsdatei ist in BEIDEN Dumps
 * eingetragen. Auffrischen: testing-mariadb-lokal.md (workdiary_squash) bzw.
 * temporäre SQLite-Datei + `php artisan schema:dump`.
 */
class SchemaDumpFreshnessTest extends TestCase {
    use ScansSourceTree;

    /** Mehr als so viele fehlende Migrationen gelten als veralteter Dump. */
    private const TOLERANCE = 0;

    public function test_schema_dumps_contain_the_latest_migrations(): void {
        $migrations = array_map(
            fn (string $path): string => basename($path, '.php'),
            $this->filesUnder('database/migrations', '/\.php$/'),
        );
        sort($migrations);
        $this->assertNotEmpty($migrations);

        foreach (['database/schema/mysql-schema.sql', 'database/schema/sqlite-schema.sql'] as $dump) {
            $path = $this->repoRoot() . '/' . $dump;
            $this->assertFileExists($path);
            $content = (string) file_get_contents($path);

            $missing = array_values(array_filter($migrations, fn (string $name): bool => ! str_contains($content, "'" . $name . "'")));

            $this->assertLessThanOrEqual(self::TOLERANCE, count($missing), sprintf(
                "%s ist %d Migration(en) im Rückstand:\n%s\n\nAuffrischen (MariaDB: workdiary_squash + DB_SOCKET, SQLite: temporäre Datei) → php artisan schema:dump; Sandbox-Kopfzeile und testing_schema_state entfernen.",
                $dump,
                count($missing),
                implode("\n", array_slice($missing, 0, 10)),
            ));
        }
    }
}
