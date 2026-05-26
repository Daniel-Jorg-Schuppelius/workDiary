<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WritesReportCsv.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Http\Response;

/**
 * MVP-043: Zentrales Helper-Trait für CSV-Exporte der Report-Controller.
 *
 * Liefert UTF-8/BOM-CSV mit optionalen Meta-Kommentarzeilen
 * (`#report:`, `#generated:`, `#filter_hash:`) und stellt einen
 * stabilen 8-stelligen Filter-Hash (SHA-256) bereit, der vom Audit-
 * Eintrag wiederverwendet wird (vgl. report.exported).
 */
trait WritesReportCsv {
    /**
     * Liefert eine CSV-Antwort mit optionalen Meta-Kommentarzeilen.
     *
     * @param  list<list<string|int|float|null>>  $rows
     * @param  array<string, mixed>               $filters
     */
    protected function csvWithMetadata(
        array $rows,
        string $filename,
        string $reportCode,
        array $filters,
    ): Response {
        $delimiter = (string) config('reports.csv_delimiter', ';');
        $delimiter = $delimiter === '' ? ';' : $delimiter[0];

        $csv = '';

        if ((bool) config('reports.csv_meta_lines', true)) {
            $csv .= '#report:' . $reportCode . "\r\n";
            $csv .= '#generated:' . CarbonImmutable::now('UTC')->format('Y-m-d\TH:i:s\Z') . "\r\n";
            $csv .= '#filter_hash:' . $this->reportFilterHash($filters) . "\r\n";
        }

        foreach ($rows as $row) {
            $csv .= implode($delimiter, array_map(
                static function ($value) use ($delimiter): string {
                    $string = $value === null ? '' : (string) $value;
                    if (
                        str_contains($string, $delimiter)
                        || str_contains($string, '"')
                        || str_contains($string, "\n")
                        || str_contains($string, "\r")
                    ) {
                        $string = '"' . str_replace('"', '""', $string) . '"';
                    }

                    return $string;
                },
                $row,
            )) . "\r\n";
        }

        return response("\xEF\xBB\xBF" . $csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Stabiler 8-stelliger Filter-Hash für Audit & Meta-Zeile.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function reportFilterHash(array $filters): string {
        return substr($this->reportFilterHashFull($filters), 0, 8);
    }

    /**
     * Voller SHA-256-Hash (Audit-Log) — identische Normalisierung.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function reportFilterHashFull(array $filters): string {
        $normalized = $this->normalizeForHash($filters);
        $payload = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash('sha256', $payload === false ? '' : $payload);
    }

    /**
     * Sortiert Filter-Keys rekursiv für reproduzierbare Hashes.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function normalizeForHash(array $filters): array {
        ksort($filters);
        foreach ($filters as $key => $value) {
            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                $filters[$key] = $this->normalizeForHash($value);
            }
        }

        return $filters;
    }
}
