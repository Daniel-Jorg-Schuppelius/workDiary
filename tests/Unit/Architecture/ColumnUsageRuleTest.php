<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ColumnUsageRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\{MeasuresEffect, ScansSourceTree};

/**
 * Gate MVP-1620 „Spalte ohne Lese- oder Schreibstelle“: Jede Spalte des
 * Schemas kommt im Code außerhalb von `$fillable`, `casts`, `$hidden` und
 * Docblocks vor — als Regel-Schlüssel, Zuweisung, Abfrage, Ausgabe oder
 * Spec-Spalte. Eine Spalte, die nur deklariert ist, wird gespeichert und
 * nie gelesen oder nie geschrieben (Scan 2026-11-10: 79 Kandidaten).
 *
 * Grundlage ist der Schema-Dump plus die Migrationen, die er noch nicht
 * kennt (angelegte und entfernte Spalten). Gemessen wird auf Namensebene:
 * kommt der Name irgendwo im Code vor, gilt die Spalte als benutzt.
 */
final class ColumnUsageRuleTest extends TestCase {
    use MeasuresEffect;
    use ScansSourceTree;

    /** Tabellen des Frameworks und von Spatie — ihre Spalten liest das Paket. */
    private const array FRAMEWORK_TABLES = [
        'cache', 'cache_locks', 'failed_jobs', 'job_batches', 'jobs', 'migrations', 'model_has_permissions', 'model_has_roles',
        'notifications', 'password_reset_tokens', 'permissions', 'personal_access_tokens', 'role_has_permissions', 'roles', 'sessions',
    ];

    /** Spalten, die Eloquent selbst pflegt. */
    private const array FRAMEWORK_COLUMNS = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /** Blueprint-Methoden, deren erstes Argument keine neue Spalte ist. */
    private const string NOT_A_COLUMN = '/^(?:drop\w*|index|unique|foreign|primary|fullText|spatialIndex|rename\w*|hasColumn|references|on|after|comment|default|change|charset|collation)$/';

    public function test_every_column_is_read_or_written(): void {
        /** @var array<string, string> $baseline */
        $baseline = require __DIR__ . '/baselines/column-usage.php';
        $new = array_values(array_diff($this->offenders(), array_keys($baseline)));

        $this->assertSame([], $new, "Spalte ohne Lese- oder Schreibstelle (nur \$fillable, casts, Docblock):\n  " . implode("\n  ", $new)
            . "\n\nAnschließen (Formular, Dienst, Anzeige) oder per Migration entfernen — vorher den Bestand prüfen."
            . "\nNur eine begründete Ausnahme gehört mit Grund in baselines/column-usage.php.");
    }

    public function test_baseline_has_no_stale_entries(): void {
        /** @var array<string, string> $baseline */
        $baseline = require __DIR__ . '/baselines/column-usage.php';
        $stale = array_values(array_diff(array_keys($baseline), $this->offenders()));

        $this->assertSame([], $stale, "Diese Spalten werden inzwischen benutzt oder gibt es nicht mehr — aus der Baseline streichen:\n  " . implode("\n  ", $stale));
        $this->assertSame([], $this->baselineEntriesWithoutReason($baseline), 'Baseline-Eintrag ohne konkreten Grund.');
    }

    public function test_the_scan_sees_the_schema(): void {
        $columns = array_sum(array_map('count', $this->schemaColumns()));
        $this->assertGreaterThan(10000, $columns);
    }

    /** @return list<string> tabelle.spalte */
    private function offenders(): array {
        $words = $this->usedWords();
        $found = [];
        foreach ($this->schemaColumns() as $table => $columns) {
            if (in_array($table, self::FRAMEWORK_TABLES, true)) {
                continue;
            }
            foreach (array_keys($columns) as $column) {
                if (in_array($column, self::FRAMEWORK_COLUMNS, true) || isset($words[$column])) {
                    continue;
                }
                // Morph-Paar und Fremdschlüssel: gelesen über die Relation (morphTo('x'), belongsTo → x_id).
                if (preg_match('/^(\w+)_(?:type|id)$/', $column, $pair) === 1 && (isset($words[$pair[1]]) || isset($words[lcfirst(str_replace('_', '', ucwords($pair[1], '_')))]))) {
                    continue;
                }
                $found[] = $table . '.' . $column;
            }
        }
        sort($found);

        return $found;
    }

    /** @return array<string, true> Bezeichner im Code ohne Deklarationslisten der Modelle */
    private function usedWords(): array {
        $words = [];
        $corpus = $this->codeCorpus(['app', 'routes', 'config', 'database/seeders']);
        $corpus += $this->codeCorpus(['resources/views'], '/\.blade\.php$/');
        $corpus += $this->codeCorpus(['resources/js'], '/\.(?:js|ts|mjs)$/');
        foreach ($corpus as $relative => $source) {
            if (str_contains($relative, '/Database/Migrations/')) {
                continue;
            }
            $source = (string) preg_replace('~(?:protected|public)\s+(?:array\s+)?\$(?:fillable|casts|hidden|guarded|visible|dates)\s*=\s*\[.*?\];~s', '', $source);
            $source = (string) preg_replace('~function\s+casts\(\)\s*:\s*array\s*\{\s*return\s*\[.*?\];\s*\}~s', '', $source);
            if (preg_match_all('/[A-Za-z_][A-Za-z0-9_]*/', $source, $m) > 0) {
                $words += array_fill_keys($m[0], true);
            }
        }

        return $words;
    }

    /**
     * Spalten je Tabelle: Schema-Dump, darauf die Migrationen, die der Dump
     * noch nicht verzeichnet (der Dump wird im Hauptlauf aufgefrischt).
     *
     * @return array<string, array<string, true>>
     */
    private function schemaColumns(): array {
        $tables = [];
        foreach ($this->schemaTables() as $table => $definition) {
            // Generierte Spalten (Unique-Index über offene Datensätze) liest die Datenbank selbst.
            $columns = array_filter($definition['columns'], static fn (string $column): bool => ! str_contains($column, 'GENERATED ALWAYS'));
            $tables[$table] = array_fill_keys(array_keys($columns), true);
        }
        $dump = (string) file_get_contents($this->repoRoot() . '/database/schema/mysql-schema.sql');
        foreach ($this->migrationFiles() as $file) {
            if (str_contains($dump, "'" . basename($file, '.php') . "'")) {
                continue;
            }
            $source = (string) file_get_contents($file);
            $up = preg_match('/function up\(\)[^{]*\{/', $source, $start, PREG_OFFSET_CAPTURE) === 1
                ? $this->balancedFrom($source, (int) $start[0][1] + strlen($start[0][0]) - 1)
                : $source;
            $this->applyMigration($tables, $up);
        }

        return $tables;
    }

    /** @param array<string, array<string, true>> $tables */
    private function applyMigration(array &$tables, string $up): void {
        $loops = [];
        if (preg_match_all('/foreach\s*\(\s*\[([^\]]*)\]\s*as\s*\$(\w+)\s*\)/', $up, $foreach, PREG_SET_ORDER) > 0) {
            foreach ($foreach as $loop) {
                preg_match_all('/[\'"](\w+)[\'"]/', $loop[1], $names);
                $loops[$loop[2]] = $names[1];
            }
        }
        preg_match_all('/Schema::(create|table|dropIfExists|drop|rename)\(\s*(?:[\'"](\w+)[\'"]|\$(\w+))(?:\s*,\s*[\'"](\w+)[\'"])?(.*?)(?=Schema::(?:create|table|dropIfExists|drop|rename)\(|$)/s', $up, $operations, PREG_SET_ORDER);
        foreach ($operations as $operation) {
            $kind = $operation[1];
            $targets = $operation[2] !== '' ? [$operation[2]] : ($loops[$operation[3]] ?? []);
            $body = $operation[5];
            foreach ($targets as $table) {
                if ($kind === 'dropIfExists' || $kind === 'drop') {
                    unset($tables[$table]);

                    continue;
                }
                if ($kind === 'rename') {
                    $tables[$operation[4]] = $tables[$table] ?? [];
                    unset($tables[$table]);

                    continue;
                }
                if ($kind === 'create') {
                    $tables[$table] = [];
                }
                if (preg_match_all('/\$\w+->(\w+)\(\s*[\'"](\w+)[\'"][^;]*/', $body, $columns, PREG_SET_ORDER) > 0) {
                    foreach ($columns as [$statement, $method, $column]) {
                        if (str_contains($statement, 'virtualAs(') || str_contains($statement, 'storedAs(')) {
                            continue;
                        }
                        if ($method === 'dropColumn' || $method === 'dropConstrainedForeignId') {
                            unset($tables[$table][$column]);
                        } elseif ($method === 'renameColumn' && preg_match('/renameColumn\(\s*[\'"]' . $column . '[\'"]\s*,\s*[\'"](\w+)[\'"]/', $body, $renamed) === 1) {
                            unset($tables[$table][$column]);
                            $tables[$table][$renamed[1]] = true;
                        } elseif (in_array($method, ['morphs', 'nullableMorphs', 'uuidMorphs', 'nullableUuidMorphs'], true)) {
                            $tables[$table][$column . '_type'] = true;
                            $tables[$table][$column . '_id'] = true;
                        } elseif (preg_match(self::NOT_A_COLUMN, $method) !== 1) {
                            $tables[$table][$column] = true;
                        }
                    }
                }
                if (preg_match_all('/dropColumn\(\s*\[([^\]]*)\]/', $body, $lists) > 0) {
                    foreach ($lists[1] as $list) {
                        preg_match_all('/[\'"](\w+)[\'"]/', $list, $names);
                        foreach ($names[1] as $name) {
                            unset($tables[$table][$name]);
                        }
                    }
                }
            }
        }
    }
}
