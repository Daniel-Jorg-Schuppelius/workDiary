<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SortableColumnsExistTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Platform;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Gate (UI-Fuzz 2026-09-21): Die Dienstplanliste sortierte „Titel“ nach der
 * nicht existierenden Spalte `name` — ein Klick auf die Spaltenüberschrift
 * endete in 1054 (HTTP 500). Geprüft werden alle SortableQuery::apply()-
 * Zuordnungen, deren Query sich einem Modell zuordnen lässt, gegen den
 * Schema-Dump. Aggregat-Aliase (…_count/_sum/_min/_max) und Joins (a.b) zählen nicht.
 */
class SortableColumnsExistTest extends TestCase {
    public function test_sortable_query_mappings_point_to_existing_columns(): void {
        $columns = $this->schemaColumns();
        $missing = [];

        foreach (File::allFiles(app_path()) as $file) {
            $source = File::get($file->getPathname());
            if (! str_contains($source, 'SortableQuery::apply')) {
                continue;
            }
            preg_match_all('/SortableQuery::apply\(\s*\$(\w+)\s*,\s*\$\w+\s*,\s*\[(.*?)\]\s*,/s', $source, $calls, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

            foreach ($calls as $call) {
                $table = $this->tableFor(substr($source, 0, (int) $call[0][1]), $call[1][0], $source);
                if ($table === null || ! isset($columns[$table])) {
                    continue;
                }
                preg_match_all('/=>\s*[\'"]([\w.]+)[\'"]/', $call[2][0], $values);
                foreach ($values[1] as $column) {
                    if (str_contains($column, '.') || preg_match('/_(count|sum|min|max)$/', $column) === 1) {
                        continue;
                    }
                    if (! in_array($column, $columns[$table], true)) {
                        $missing[] = $file->getRelativePathname() . " — {$table}.{$column}";
                    }
                }
            }
        }

        $this->assertSame([], $missing, "Sortierzuordnung auf nicht existierende Spalte:\n" . implode("\n", $missing));
    }

    /** @return array<string, list<string>> */
    private function schemaColumns(): array {
        preg_match_all('/CREATE TABLE `(\w+)` \((.*?)\) ENGINE/s', File::get(database_path('schema/mysql-schema.sql')), $tables, PREG_SET_ORDER);
        $columns = [];
        foreach ($tables as $table) {
            preg_match_all('/^\s+`(\w+)`/m', $table[2], $names);
            $columns[$table[1]] = $names[1];
        }

        return $columns;
    }

    /** Letzte Zuweisung `$var = Modell::query()`/`::with(…)` vor dem Aufruf, Modell über die use-Liste. */
    private function tableFor(string $before, string $variable, string $source): ?string {
        if (preg_match_all('/\$' . $variable . '\s*=\s*\\\\?(?:[\w\\\\]*\\\\)?(\w+)::(?:query|with|where|withoutGlobalScopes|withTrashed)\(/', $before, $matches) === 0) {
            return null;
        }
        $model = end($matches[1]);
        $candidates = ['App\\Models\\' . $model];
        if (preg_match('/^use\s+([\w\\\\]*\\\\' . $model . ');/m', $source, $use) === 1) {
            $candidates[] = $use[1];
        }
        if (preg_match('/^use\s+([\w\\\\]+)\\\\\{[^}]*\b' . $model . '\b/m', $source, $group) === 1) {
            $candidates[] = $group[1] . '\\' . $model;
        }
        foreach ($candidates as $class) {
            if (class_exists($class) && is_subclass_of($class, Model::class)) {
                return (new $class)->getTable();
            }
        }

        return null;
    }
}
