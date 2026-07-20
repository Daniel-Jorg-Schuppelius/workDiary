<?php
/*
 * Created on   : Sun Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CsvImportSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import\Source;

use App\Enums\Import\ImportErrorCode;
use App\Services\Import\{EntitySpec, ValidationIssue};
use App\Support\Toolkit\CsvFacade;
use CommonToolkit\Parsers\CSVDocumentParser;

/**
 * CSV-/XLSX-Quelle (MVP-438).
 *
 * Kapselt Delimiter-Erkennung, Header-Aliasauflösung und das Streamen der
 * Datenzeilen — die Logik, die zuvor im {@see \App\Services\Import\CsvPreflightAnalyzer}
 * UND im {@see \App\Jobs\ProcessCsvImportJob} dupliziert war. XLSX wird bereits
 * im Preflight in die interne CSV-Struktur überführt (A13); diese Quelle sieht
 * daher stets CSV.
 */
final class CsvImportSource implements ImportSource {
    private ?string $delimiter;

    public function __construct(
        private readonly string $absolutePath,
        ?string $delimiter = null,
    ) {
        $this->delimiter = $delimiter;
    }

    /**
     * Aufgelöster Delimiter (wird auf dem {@see \App\Models\ImportRun}
     * persistiert, damit der Job dieselbe Trennung nutzt).
     */
    public function delimiter(): string {
        return $this->delimiter ??= CSVDocumentParser::detectDelimiter($this->absolutePath);
    }

    public function headerIssues(EntitySpec $spec): array {
        $aliases = $this->aliasMap($spec);
        $seen = [];
        $issues = [];

        foreach ($this->rawHeader() as $headerCell) {
            $canonical = $aliases[$this->normKey($headerCell)] ?? null;
            if ($canonical === null) {
                continue;
            }
            if (isset($seen[$canonical])) {
                $issues[] = new ValidationIssue(
                    ImportErrorCode::HeaderUnknown,
                    $canonical,
                    (string) __('import.error.header.duplicate', ['column' => $canonical]),
                );
            } else {
                $seen[$canonical] = true;
            }
        }

        foreach ($spec->requiredColumns() as $required) {
            if (! isset($seen[$required])) {
                $issues[] = new ValidationIssue(
                    ImportErrorCode::HeaderMissing,
                    $required,
                    (string) __('import.error.header.missing', ['column' => $required]),
                );
            }
        }

        return $issues;
    }

    public function rows(EntitySpec $spec): iterable {
        $headerMap = $this->buildHeaderMap($spec);
        $number = 0;

        foreach (CsvFacade::streamAssoc($this->absolutePath, $this->delimiter()) as $rawRow) {
            $number++;

            yield SourceRow::data($number, $this->applyHeaderMap($rawRow, $headerMap));
        }
    }

    /**
     * @return list<string>
     */
    private function rawHeader(): array {
        return array_values(CSVDocumentParser::readHeader($this->absolutePath, $this->delimiter())->getColumnNames());
    }

    /**
     * {alias|kanonischer Code (normalisiert) => kanonischer Code}.
     *
     * @return array<string, string>
     */
    private function aliasMap(EntitySpec $spec): array {
        $aliases = [];
        foreach ($spec->headerAliases() as $alias => $canonical) {
            $aliases[$this->normKey($alias)] = $canonical;
        }
        foreach ($spec->columns() as $col) {
            $aliases[$this->normKey($col)] = $col;
        }

        return $aliases;
    }

    /**
     * @return array<int, string|null>
     */
    private function buildHeaderMap(EntitySpec $spec): array {
        $aliases = $this->aliasMap($spec);
        $map = [];
        foreach ($this->rawHeader() as $idx => $headerCell) {
            $map[$idx] = $aliases[$this->normKey($headerCell)] ?? null;
        }

        return $map;
    }

    /**
     * @param  array<string, string>  $raw
     * @param  array<int, string|null>  $headerMap
     * @return array<string, string>
     */
    private function applyHeaderMap(array $raw, array $headerMap): array {
        $values = array_values($raw);
        $out = [];
        foreach ($headerMap as $idx => $canonical) {
            if ($canonical === null) {
                continue;
            }
            $out[$canonical] = $values[$idx] ?? '';
        }

        return $out;
    }

    private function normKey(string $key): string {
        return mb_strtolower(trim($key));
    }
}
