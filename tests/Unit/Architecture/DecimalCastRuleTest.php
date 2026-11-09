<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DecimalCastRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Gate MVP-1511 (ul-24): Jede DECIMAL-Spalte eines Modells aus der Morph-Map
 * hat `decimal:N` mit der Skala der Spalte oder einen Wertobjekt-Cast. Ohne
 * Cast liefert SQLite Zahlen und MariaDB Strings (Befund 2026-11-07, CI
 * sieben Wochen rot). Spalten aus dem MySQL-Dump plus `->decimal()` aus
 * Migrationen, die der Dump noch nicht kennt.
 */
final class DecimalCastRuleTest extends TestCase {
    use ScansSourceTree;

    /** @var array<string, string> Tabelle.Spalte → Grund */
    private const array FLOAT_ALLOWED = [
        'attendance_checkpoints.latitude' => 'Koordinate, float-Cast liest auf beiden Treibern gleich; Leser rechnen Distanzen in float.',
        'attendance_checkpoints.longitude' => 'Koordinate, float-Cast liest auf beiden Treibern gleich; Leser rechnen Distanzen in float.',
        'attendances.started_lat' => 'Koordinate, float-Cast liest auf beiden Treibern gleich; Leser rechnen Distanzen in float.',
        'attendances.started_lng' => 'Koordinate, float-Cast liest auf beiden Treibern gleich; Leser rechnen Distanzen in float.',
        'attendances.ended_lat' => 'Koordinate, float-Cast liest auf beiden Treibern gleich; Leser rechnen Distanzen in float.',
        'attendances.ended_lng' => 'Koordinate, float-Cast liest auf beiden Treibern gleich; Leser rechnen Distanzen in float.',
        'travel_logs.from_lat' => 'Koordinate, float-Cast liest auf beiden Treibern gleich; Leser rechnen Distanzen in float.',
        'travel_logs.from_lng' => 'Koordinate, float-Cast liest auf beiden Treibern gleich; Leser rechnen Distanzen in float.',
        'travel_logs.to_lat' => 'Koordinate, float-Cast liest auf beiden Treibern gleich; Leser rechnen Distanzen in float.',
        'travel_logs.to_lng' => 'Koordinate, float-Cast liest auf beiden Treibern gleich; Leser rechnen Distanzen in float.',
        'user_known_devices.latitude' => 'Koordinate, float-Cast liest auf beiden Treibern gleich; Leser rechnen Distanzen in float.',
        'user_known_devices.longitude' => 'Koordinate, float-Cast liest auf beiden Treibern gleich; Leser rechnen Distanzen in float.',
    ];

    public function test_decimal_columns_of_models_have_a_decimal_or_value_object_cast(): void {
        $columns = $this->decimalColumns();
        $this->assertNotSame([], $columns, 'Keine DECIMAL-Spalte gefunden — Dump- oder Migrationsleser defekt.');

        $aliases = (require $this->repoRoot() . '/config/morph-map.php')['aliases'];
        $violations = [];
        foreach ($columns as $table => $scales) {
            $class = $aliases[$table] ?? null;
            if (! is_string($class) || ! class_exists($class)) {
                continue;
            }
            $model = new $class();
            if (! $model instanceof Model) {
                continue;
            }
            $casts = $model->getCasts();
            foreach ($scales as $column => $scale) {
                $problem = $this->problem($casts[$column] ?? null, $scale, "{$table}.{$column}");
                if ($problem !== null) {
                    $violations[] = "{$class} ({$table}.{$column}): {$problem}";
                }
            }
        }
        sort($violations);

        $this->assertSame([], $violations, "DECIMAL-Spalten brauchen `decimal:N` (Skala der Spalte) oder einen Wertobjekt-Cast (konventionen/datenbank-konventionen.md):\n" . implode("\n", $violations));
    }

    public function test_float_allow_list_is_alive(): void {
        $columns = $this->decimalColumns();
        $dead = array_values(array_filter(array_keys(self::FLOAT_ALLOWED), static function (string $key) use ($columns): bool {
            [$table, $column] = explode('.', $key, 2);

            return ! isset($columns[$table][$column]);
        }));

        $this->assertSame([], $dead, 'Ausnahme ohne DECIMAL-Spalte — aus FLOAT_ALLOWED streichen.');
    }

    private function problem(?string $cast, int $scale, string $key): ?string {
        if ($cast === null) {
            return 'kein Cast';
        }
        if (str_starts_with($cast, 'decimal:')) {
            return (int) substr($cast, 8) === $scale ? null : "Cast {$cast}, Spalte hat Skala {$scale}";
        }
        $base = explode(':', $cast, 2)[0];
        if (class_exists($base) && is_subclass_of($base, CastsAttributes::class)) {
            return null;
        }
        if ($cast === 'float' && isset(self::FLOAT_ALLOWED[$key])) {
            return null;
        }

        return "Cast {$cast}";
    }

    /** @return array<string, array<string, int>> Tabelle → Spalte → Skala */
    private function decimalColumns(): array {
        $root = $this->repoRoot();
        $dump = (string) file_get_contents($root . '/database/schema/mysql-schema.sql');
        $columns = [];
        if (preg_match_all('/CREATE TABLE `(\w+)` \((.*?)\n\)[^\n]*;/s', $dump, $tables, PREG_SET_ORDER) > 0) {
            foreach ($tables as [$_, $table, $body]) {
                if (preg_match_all('/^\s*`(\w+)` decimal\(\d+,(\d+)\)/m', $body, $m, PREG_SET_ORDER) > 0) {
                    foreach ($m as [$__, $column, $scale]) {
                        $columns[$table][$column] = (int) $scale;
                    }
                }
            }
        }

        preg_match_all("/INSERT INTO `migrations` \\(`id`, `migration`, `batch`\\) VALUES \\(\\d+,'([^']+)'/", $dump, $known);
        $inDump = array_flip($known[1]);
        foreach ($this->migrationFiles() as $file) {
            if (isset($inDump[basename($file, '.php')])) {
                continue;
            }
            $source = $this->stripComments((string) file_get_contents($file));
            $up = preg_split('/function\s+down\s*\(/', $source)[0] ?? $source;
            if (preg_match_all('/Schema::(?:create|table)\(\s*[\'"](\w+)[\'"]|->decimal\(\s*[\'"](\w+)[\'"]\s*(?:,\s*(?:total:\s*)?\d+\s*(?:,\s*(?:places:\s*)?(\d+))?)?/', $up, $hits, PREG_SET_ORDER) === 0) {
                continue;
            }
            $table = null;
            foreach ($hits as $hit) {
                if (($hit[1] ?? '') !== '') {
                    $table = $hit[1];
                } elseif ($table !== null && ($hit[2] ?? '') !== '') {
                    $columns[$table][$hit[2]] = isset($hit[3]) && $hit[3] !== '' ? (int) $hit[3] : 2;
                }
            }
        }

        return $columns;
    }
}
