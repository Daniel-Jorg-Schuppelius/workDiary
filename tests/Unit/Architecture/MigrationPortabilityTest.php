<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MigrationPortabilityTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Architektur-Gate für MySQL-/MariaDB-Portabilität der Migrationen.
 *
 * SQLite (lokal/CI-Default) verschluckt zwei Klassen von Fehlern, die auf
 * MySQL/InnoDB hart abbrechen lassen:
 *
 *   1. Foreign-Key-Reihenfolge: Eine `constrained('x')`-Referenz auf eine
 *      Tabelle, die in einer SPÄTEREN Migration erst erstellt wird, führt zu
 *      `errno 150 (Foreign key constraint is incorrectly formed)`.
 *   2. Identifier-Länge: Auto-generierte Index-/Unique-/FK-Namen dürfen auf
 *      MySQL maximal 64 Zeichen lang sein, sonst `1059 (Identifier name too
 *      long)`.
 *
 * Dieser Test prüft beides statisch über alle Migrationsdateien, damit solche
 * Fehler vor dem Deploy auffallen statt erst im Web-Installer auf dem Server.
 */
class MigrationPortabilityTest extends TestCase {
    private const IDENTIFIER_LIMIT = 64;

    /** Pfad zum Migrationsverzeichnis. */
    private function migrationDir(): string {
        return dirname(__DIR__, 3) . '/database/migrations';
    }

    /** @return list<string> alle Migrationsdateien, sortiert nach Dateiname (= Ausführungsreihenfolge). */
    private function migrationFiles(): array {
        $files = glob($this->migrationDir() . '/*.php') ?: [];
        sort($files);

        return $files;
    }

    /**
     * Foreign Keys dürfen nur auf Tabellen verweisen, die zu diesem Zeitpunkt
     * bereits erstellt wurden (oder im selben Migrationsfile davor).
     */
    public function test_foreign_keys_reference_already_created_tables(): void {
        $created = [];
        $violations = [];

        foreach ($this->migrationFiles() as $file) {
            $src = file_get_contents($file) ?: '';
            $base = basename($file);

            // Tabellen, die diese Datei erstellt (Intra-File-Reihenfolge ist unkritisch).
            $intra = [];
            if (preg_match_all('/Schema::create\(\s*[\'"]([a-z0-9_]+)[\'"]/', $src, $m)) {
                foreach ($m[1] as $t) {
                    $intra[$t] = true;
                }
            }

            // Explizite Referenzen: constrained('zieltabelle').
            if (preg_match_all('/constrained\(\s*[\'"]([a-z0-9_]+)[\'"]/', $src, $cm)) {
                foreach ($cm[1] as $target) {
                    if (! isset($created[$target]) && ! isset($intra[$target])) {
                        $violations[] = sprintf('%s: Foreign Key -> %s (Tabelle existiert zu diesem Zeitpunkt noch nicht)', $base, $target);
                    }
                }
            }

            foreach ($intra as $t => $_) {
                $created[$t] = $base;
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($violations)),
            "Foreign Keys verweisen auf noch nicht erstellte Tabellen (MySQL errno 150).\n"
                . "Verschiebe die referenzierte CREATE-Migration vor die referenzierende.\n"
        );
    }

    /**
     * Auto-generierte Identifier (Index/Unique/FK/morphs ohne expliziten Namen)
     * dürfen das MySQL-Limit von 64 Zeichen nicht überschreiten.
     */
    public function test_auto_generated_identifiers_fit_mysql_limit(): void {
        $violations = [];

        foreach ($this->migrationFiles() as $file) {
            $src = file_get_contents($file) ?: '';
            $base = basename($file);

            if (! preg_match_all('/Schema::(?:create|table)\(\s*[\'"]([a-z0-9_]+)[\'"][^;]*?\{(.*?)\}\s*\);/s', $src, $blocks, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($blocks as $b) {
                $table = $b[1];
                $body = $b[2];

                // index([...]) / unique([...]) ohne expliziten Namen.
                if (preg_match_all('/->\s*(index|unique)\(\s*\[([^\]]*)\]\s*\)/', $body, $im, PREG_SET_ORDER)) {
                    foreach ($im as $m) {
                        $cols = array_filter(array_map(static fn($c) => trim($c, " '\"\t"), explode(',', $m[2])));
                        $suffix = $m[1] === 'unique' ? 'unique' : 'index';
                        $name = $table . '_' . implode('_', $cols) . '_' . $suffix;
                        if (strlen($name) > self::IDENTIFIER_LIMIT) {
                            $violations[] = sprintf('%s: %s [%s] -> %s (%d Zeichen)', $base, $m[1], implode(',', $cols), $name, strlen($name));
                        }
                    }
                }

                // Foreign Keys: foreignId('col')->constrained(...) bzw.
                // ->foreign('col') ohne expliziten Namen erzeugen den Constraint-
                // Namen {table}_{col}_foreign (basiert auf der LOKALEN Spalte).
                foreach (explode(';', $body) as $stmt) {
                    $hasExplicitName = str_contains($stmt, 'indexName');
                    if (
                        ! $hasExplicitName
                        && preg_match('/->\s*constrained\(/', $stmt)
                        && preg_match('/foreignId\(\s*[\'"]([a-z0-9_]+)[\'"]\s*\)/', $stmt, $fm)
                    ) {
                        $name = $table . '_' . $fm[1] . '_foreign';
                        if (strlen($name) > self::IDENTIFIER_LIMIT) {
                            $violations[] = sprintf('%s: FK %s -> %s (%d Zeichen)', $base, $fm[1], $name, strlen($name));
                        }
                    }
                    // ->foreign('col') ohne zweites Namens-Argument.
                    if (preg_match('/->\s*foreign\(\s*[\'"]([a-z0-9_]+)[\'"]\s*\)/', $stmt, $fm2)) {
                        $name = $table . '_' . $fm2[1] . '_foreign';
                        if (strlen($name) > self::IDENTIFIER_LIMIT) {
                            $violations[] = sprintf('%s: FK %s -> %s (%d Zeichen)', $base, $fm2[1], $name, strlen($name));
                        }
                    }
                }

                // morphs()/nullableMorphs()/uuidMorphs() ohne expliziten Indexnamen.
                if (preg_match_all('/->\s*(morphs|nullableMorphs|uuidMorphs)\(\s*[\'"]([a-z0-9_]+)[\'"]\s*\)/', $body, $mm, PREG_SET_ORDER)) {
                    foreach ($mm as $m) {
                        $nameCol = $m[2];
                        $idx = $table . '_' . $nameCol . '_type_' . $nameCol . '_id_index';
                        if (strlen($idx) > self::IDENTIFIER_LIMIT) {
                            $violations[] = sprintf('%s: %s(%s) -> %s (%d Zeichen)', $base, $m[1], $nameCol, $idx, strlen($idx));
                        }
                    }
                }
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($violations)),
            "Auto-generierte Identifier überschreiten 64 Zeichen (MySQL Fehler 1059).\n"
                . "Vergib einen expliziten kurzen Namen, z. B. index([...], 'kurz_idx') bzw. morphs('x', 'kurz_idx').\n"
        );
    }

    /**
     * Tabellen-Mutationen (Schema::table/DB::table) dürfen nur auf Tabellen
     * zeigen, die zu diesem Zeitpunkt bereits erstellt sind. Eine bewusste
     * Vorwärtsreferenz (Tabelle wird von einer SPÄTEREN create-Migration
     * angelegt) ist nur zulässig, wenn sie im selben File per Schema::hasTable()
     * abgesichert ist. Sonst bricht MySQL auf einer frischen DB mit `1146 (Base
     * table or view not found)` ab — SQLite verschluckt es lautlos.
     */
    public function test_table_mutations_reference_already_created_tables(): void {
        $files = $this->migrationFiles();

        // Pass 1: früheste create-Migration je Tabelle (Index = Ausführungsreihenfolge).
        $createdAt = [];
        foreach ($files as $i => $file) {
            $src = file_get_contents($file) ?: '';
            if (preg_match_all('/Schema::create\(\s*[\'"]([a-z0-9_]+)[\'"]/', $src, $m)) {
                foreach ($m[1] as $t) {
                    if (! isset($createdAt[$t])) {
                        $createdAt[$t] = $i;
                    }
                }
            }
        }

        // Pass 2: jede Mutation prüfen.
        $violations = [];
        foreach ($files as $i => $file) {
            $src = file_get_contents($file) ?: '';
            $base = basename($file);

            if (! preg_match_all('/(?:Schema::table|DB::table)\(\s*[\'"]([a-z0-9_]+)[\'"]/', $src, $rm)) {
                continue;
            }
            foreach (array_unique($rm[1]) as $t) {
                if (! isset($createdAt[$t]) || $createdAt[$t] <= $i) {
                    continue; // Fremd-/Framework-Tabelle oder bereits (im selben File) erstellt.
                }
                // Vorwärtsreferenz: nur mit Schema::hasTable()-Guard im selben File zulässig.
                if (preg_match('/Schema::hasTable\(\s*[\'"]' . preg_quote($t, '/') . '[\'"]/', $src)) {
                    continue;
                }
                $violations[] = sprintf('%s: Schema::table/DB::table -> %s wird erst später angelegt (ungeschützte Vorwärtsreferenz)', $base, $t);
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($violations)),
            "Migrationen verändern Tabellen, die erst SPÄTER angelegt werden (MySQL 1146 auf frischer DB;\n"
                . "SQLite verschluckt es lautlos). Lösung: die create-Migration vor die ändernde legen, ODER\n"
                . "die Änderung mit `if (Schema::hasTable('tabelle')) { … }` absichern und die finale\n"
                . "Spaltenform direkt in der create-Migration definieren.\n"
        );
    }
}
