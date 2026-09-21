<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FormRequestColumnCapacityTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Rules\{Bic, HexColor, Iban, TimestampRange};
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rules\{Enum, In};
use ReflectionClass;
use Tests\TestCase;
use Throwable;

/**
 * Gate (UI-Fuzz 2026-09-21): Regeln, die mehr zulassen als die Spalte fasst,
 * enden erst beim INSERT in 1406/1264 (HTTP 500). Geprüft werden alle
 * Save/Store/Update{Modell}Request gegen die Spalten der Modelltabelle im
 * Schema-Dump: varchar/char brauchen `max` ≤ Länge, decimal/int ein `max`
 * (vorzeichenbehaftet auch `min`) innerhalb der Kapazität, Datumsfelder auf
 * TIMESTAMP-Spalten die Regel TimestampRange.
 */
class FormRequestColumnCapacityTest extends TestCase {
    /** Regeln, die ihren Wertebereich selbst festlegen. */
    private const SELF_BOUNDED = ['boolean', 'date', 'uuid', 'ulid', 'ip', 'ipv4', 'ipv6', 'mac_address', 'timezone', 'accepted'];

    public function test_form_request_rules_fit_their_columns(): void {
        $schema = $this->schemaColumns();
        $violations = [];

        foreach (File::allFiles(app_path('Http/Requests')) as $file) {
            $class = 'App\\Http\\Requests\\' . str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());
            if (preg_match('/(?:Save|Store|Update)(\w+)Request$/', $class, $m) !== 1 || ! is_subclass_of($class, FormRequest::class) || (new ReflectionClass($class))->isAbstract()) {
                continue;
            }
            $table = $this->tableOf($m[1]);
            if ($table === null || ! isset($schema[$table])) {
                continue;
            }
            try {
                $rules = (new $class())->setContainer($this->app)->rules();
            } catch (Throwable) {
                continue; // Regeln hängen an Route/Nutzer — nicht statisch prüfbar.
            }

            foreach ($rules as $field => $rule) {
                if (! is_string($field) || str_contains($field, '.') || str_ends_with($field, '_id') || ! isset($schema[$table][$field])) {
                    continue;
                }
                $problem = $this->problem($schema[$table][$field], is_string($rule) ? explode('|', $rule) : (array) $rule);
                if ($problem !== null) {
                    $violations[] = class_basename($class) . " — {$table}.{$field}: {$problem}";
                }
            }
        }

        $this->assertSame([], $violations, "Regel lässt mehr zu als die Spalte fasst:\n" . implode("\n", $violations));
    }

    /** @param  array<int, mixed>  $parts */
    private function problem(string $type, array $parts): ?string {
        $strings = array_values(array_filter($parts, 'is_string'));
        // TIMESTAMP fasst nur 1970–2038; ohne Regel scheiterte erst das INSERT (22007).
        if (str_starts_with($type, 'timestamp')) {
            $isDate = (bool) array_filter($strings, static fn (string $p): bool => $p === 'date' || str_starts_with($p, 'date_format'));
            $bounded = (bool) array_filter($parts, static fn (mixed $p): bool => $p instanceof TimestampRange);

            return $isDate && ! $bounded ? "{$type}, Datumsbereich 1970–2038 fehlt (App\\Rules\\TimestampRange)" : null;
        }
        foreach ($parts as $part) {
            if (is_object($part) && ($part instanceof Enum || $part instanceof In || $part instanceof HexColor || $part instanceof Iban || $part instanceof Bic)) {
                return null;
            }
        }
        $min = null;
        $max = null;
        $numeric = false;
        foreach ($strings as $part) {
            [$name, $arg] = array_pad(explode(':', $part, 2), 2, '');
            if (in_array($name, self::SELF_BOUNDED, true) || in_array($name, ['in', 'date_format', 'digits'], true)) {
                return null;
            }
            $numeric = $numeric || in_array($name, ['numeric', 'integer', 'decimal'], true);
            match ($name) {
                'max', 'lt', 'lte', 'size' => $max = is_numeric($arg) ? (float) $arg : $max,
                'min', 'gt', 'gte' => $min = is_numeric($arg) ? (float) $arg : $min,
                'between' => [$min, $max] = array_map('floatval', explode(',', $arg)),
                'digits_between' => $max = (float) str_repeat('9', (int) (explode(',', $arg)[1] ?? 0)),
                default => null,
            };
        }

        if (preg_match('/^(?:var)?char\((\d+)\)/', $type, $m) === 1) {
            return ! $numeric && ($max === null || $max > (int) $m[1]) ? "{$type}, max " . ($max ?? '–') : null;
        }
        if (preg_match('/^decimal\((\d+),(\d+)\)/', $type, $m) === 1) {
            $cap = 10 ** ((int) $m[1] - (int) $m[2]) - 10 ** -(int) $m[2];

            return $this->range($type, $cap, str_contains($type, 'unsigned') ? 0.0 : -$cap, $min, $max);
        }
        if ($numeric && preg_match('/^(tinyint|smallint|mediumint|int|bigint)\(\d+\)/', $type, $m) === 1) {
            $cap = ['tinyint' => 127, 'smallint' => 32767, 'mediumint' => 8388607, 'int' => 2147483647, 'bigint' => PHP_INT_MAX][$m[1]];
            $unsigned = str_contains($type, 'unsigned');

            return $this->range($type, $unsigned ? $cap * 2 + 1 : $cap, $unsigned ? 0.0 : -$cap - 1, $min, $max);
        }

        return null;
    }

    private function range(string $type, float $cap, float $floor, ?float $min, ?float $max): ?string {
        if ($max === null || $max > $cap + 1e-9) {
            return "{$type}, max " . ($max ?? '–') . " (Kapazität {$cap})";
        }
        if ($min === null || $min < $floor - 1e-9) {
            return "{$type}, min " . ($min ?? '–') . " (Untergrenze {$floor})";
        }

        return null;
    }

    private function tableOf(string $model): ?string {
        foreach (glob(app_path('Models/{,*/}' . $model . '.php'), GLOB_BRACE) ?: [] as $path) {
            $class = 'App\\Models\\' . str_replace(['/', '.php'], ['\\', ''], substr($path, strlen(app_path('Models/'))));
            if (class_exists($class) && ! (new ReflectionClass($class))->isAbstract()) {
                return (new $class())->getTable();
            }
        }

        return null;
    }

    /** @return array<string, array<string, string>> */
    private function schemaColumns(): array {
        preg_match_all('/CREATE TABLE `(\w+)` \((.*?)\) ENGINE/s', File::get(database_path('schema/mysql-schema.sql')), $tables, PREG_SET_ORDER);
        $columns = [];
        foreach ($tables as $table) {
            preg_match_all('/^\s+`(\w+)` ([a-z]+(?:\([^)]*\))?(?: unsigned)?)/m', $table[2], $cols, PREG_SET_ORDER);
            foreach ($cols as $col) {
                $columns[$table[1]][$col[1]] = $col[2];
            }
        }

        return $columns;
    }
}
