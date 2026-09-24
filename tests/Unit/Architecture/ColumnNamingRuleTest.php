<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ColumnNamingRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use Tests\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Gate MVP-870: neue Spalten folgen den Datenbank-Konventionen
 * (WorkDiary-Architecture/datenbank-konventionen.md). Geprüft werden nur
 * Migrationen nach dem Stichtag; der Bestand bleibt unberührt.
 */
class ColumnNamingRuleTest extends TestCase {
    use ScansSourceTree;

    private const CUTOFF = '2027_02_24_140000';

    /** @var array<string, string> verbotener Name → Konvention */
    private const FORBIDDEN = [
        'created_by_user_id' => 'created_by',
        'updated_by_user_id' => 'updated_by',
        'notes' => 'note',
        'active' => 'is_active',
        'valid_to' => 'valid_until',
        'type' => 'kind (Fachart) oder <name>_type + <name>_id (Morph-Paar)',
    ];

    /** Methoden, deren erstes Argument keine neue Spalte ist. */
    private const NOT_A_COLUMN = [
        'index', 'unique', 'primary', 'foreign', 'fullText', 'spatialIndex', 'rawIndex',
        'dropColumn', 'dropIndex', 'dropUnique', 'dropForeign', 'dropPrimary', 'dropMorphs',
        'dropConstrainedForeignId', 'dropForeignIdFor', 'renameIndex', 'renameColumn',
    ];

    public function test_new_migrations_follow_the_column_conventions(): void {
        $violations = [];
        foreach ($this->phpFiles('database/migrations') as $file) {
            $name = basename($file, '.php');
            if (strcmp(substr($name, 0, strlen(self::CUTOFF)), self::CUTOFF) <= 0) {
                continue;
            }
            foreach ($this->columnsByTable($this->stripComments((string) file_get_contents($file))) as $table => $columns) {
                foreach ($columns as $column) {
                    if (isset(self::FORBIDDEN[$column])) {
                        $violations[] = "{$name}: {$table}.{$column} → " . self::FORBIDDEN[$column];
                    } elseif (str_ends_with($column, '_type') && ! in_array(substr($column, 0, -5) . '_id', $columns, true)) {
                        $violations[] = "{$name}: {$table}.{$column} → <x>_kind (kein Morph-Paar)";
                    }
                }
            }
        }

        $this->assertSame([], $violations, "Spaltennamen gegen datenbank-konventionen.md:\n" . implode("\n", $violations));
    }

    /**
     * Neue Spaltennamen je Schema::create/table-Block; `morphs('x')` ergibt
     * das Paar x_type/x_id, `renameColumn('a', 'b')` die Spalte b.
     *
     * @return array<string, list<string>>
     */
    private function columnsByTable(string $source): array {
        $tables = [];
        if (preg_match_all('/Schema::(?:create|table)\(\s*[\'"](\w+)[\'"][^;]*?\{(.*?)\}\s*\);/s', $source, $blocks, PREG_SET_ORDER) === 0) {
            return $tables;
        }
        foreach ($blocks as [, $table, $body]) {
            $columns = [];
            preg_match_all('/\$\w+->(\w+)\(\s*[\'"](\w+)[\'"](?:\s*,\s*[\'"](\w+)[\'"])?/', $body, $calls, PREG_SET_ORDER);
            foreach ($calls as $call) {
                [$method, $first] = [$call[1], $call[2]];
                if ($method === 'renameColumn' && isset($call[3])) {
                    $columns[] = $call[3];
                } elseif (in_array($method, ['morphs', 'nullableMorphs', 'uuidMorphs', 'nullableUuidMorphs', 'ulidMorphs', 'nullableUlidMorphs'], true)) {
                    array_push($columns, $first . '_type', $first . '_id');
                } elseif (! in_array($method, self::NOT_A_COLUMN, true)) {
                    $columns[] = $first;
                }
            }
            $tables[$table] = array_values(array_unique([...($tables[$table] ?? []), ...$columns]));
        }

        return $tables;
    }
}
