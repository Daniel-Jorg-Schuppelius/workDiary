<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MeasuresQueryLoad.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Concerns;

use Illuminate\Support\Facades\DB;

/** Messung der Last-Kommandos: Laufzeit und Abfragen je Fall, die zwei langsamsten mit EXPLAIN. */
trait MeasuresQueryLoad {
    /** @param  array<string, callable(): mixed>  $cases */
    private function measureCases(array $cases): void {
        // Genau ein Zuhörer für alle Fälle — je Fall einen zu registrieren
        // würde jede Abfrage mehrfach zählen.
        /** @var list<array<string, mixed>> $queries */
        $queries = [];
        $recording = new \ArrayObject(['on' => false]);
        DB::listen(static function ($query) use (&$queries, $recording): void {
            if ($recording['on'] === true) {
                $queries[] = ['sql' => $query->sql, 'bindings' => $query->bindings, 'time' => $query->time];
            }
        });

        foreach ($cases as $name => $case) {
            // Ein Aufwärmlauf: Die erste Abfrage misst den kalten Puffer der
            // Datenbank, nicht das Verhalten der Anwendung.
            $case();

            $queries = [];
            $recording['on'] = true;

            $startedAt = microtime(true);
            $case();
            $elapsed = (microtime(true) - $startedAt) * 1000;
            $recording['on'] = false;

            $this->line(sprintf('  %-20s %8.1f ms  %3d Abfragen', $name, $elapsed, count($queries)));

            foreach ($this->slowest($queries) as $query) {
                $this->line(sprintf('    %6.1f ms  %s', $query['time'], mb_substr((string) $query['sql'], 0, 140)));
                foreach ($this->explain($query) as $row) {
                    $this->line('      EXPLAIN: ' . $row);
                }
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $queries
     * @return list<array<string, mixed>>
     */
    private function slowest(array $queries): array {
        usort($queries, static fn (array $a, array $b): int => $b['time'] <=> $a['time']);

        return array_slice($queries, 0, 2);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<string>
     */
    private function explain(array $query): array {
        if (! str_starts_with(strtolower(trim((string) $query['sql'])), 'select')) {
            return [];
        }

        try {
            $rows = DB::select('EXPLAIN ' . $query['sql'], (array) $query['bindings']);
        } catch (\Throwable $exception) {
            return ['nicht verfügbar (' . $exception->getMessage() . ')'];
        }

        return array_values(array_map(static function (object $row): string {
            $data = (array) $row;

            return implode(' | ', array_map(
                static fn (string $key): string => $key . '=' . (string) ($data[$key] ?? '—'),
                array_values(array_filter(array_keys($data), static fn (string $key): bool => in_array($key, ['table', 'type', 'key', 'rows', 'Extra'], true))),
            ));
        }, $rows));
    }
}
