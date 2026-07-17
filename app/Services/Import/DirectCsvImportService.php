<?php
/*
 * Created on   : Thu Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DirectCsvImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import;

use App\Enums\Import\ImportEntity;
use App\Models\Organization;
use App\Support\Toolkit\CsvFacade;
use CommonToolkit\Helper\FileSystem\File as ToolkitFile;
use CommonToolkit\Parsers\CSVDocumentParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Synchroner CSV-Direktimport über die {@see EntitySpec}-Pipeline (C13):
 * ersetzt Standalone-Importer wie den früheren CustomerCsvImporter. Läuft
 * ohne ImportRun/Preflight — Zeilen werden sofort normalisiert, validiert
 * und via {@see EntitySpec::upsert()} angelegt/aktualisiert (kein Inbox-First).
 *
 * Zeilen ohne Pflichtwert (z. B. Leerzeilen) werden wie im Alt-Importer still
 * übersprungen; Validierungs-/Persistenzfehler landen als „Zeile N: …" in der
 * Fehlerliste.
 */
class DirectCsvImportService {
    public function __construct(
        private readonly EntitySpecRegistry $registry,
    ) {}

    /**
     * @return array{created: int, updated: int, skipped: int, errors: list<string>}
     */
    public function import(UploadedFile $file, ImportEntity $entity, Organization $organization): array {
        $spec = $this->registry->for($entity);

        $path = $file->getRealPath();
        if ($path === false || ! ToolkitFile::isReadable($path)) {
            return $this->failure((string) __('errors.csv.unreadable'));
        }

        try {
            $delimiter = CSVDocumentParser::detectDelimiter($path);
            $rawHeader = array_values(CSVDocumentParser::readHeader($path, $delimiter)->getColumnNames());
        } catch (Throwable $e) {
            return $this->failure((string) __('errors.csv.header_missing', ['error' => $e->getMessage()]));
        }

        $headerMap = $this->buildHeaderMap($rawHeader, $spec);
        foreach ($spec->requiredColumns() as $required) {
            if (! in_array($required, $headerMap, true)) {
                return $this->failure($required === 'name'
                    ? (string) __('errors.csv.name_column_missing')
                    : (string) __('import.error.header.missing', ['column' => $required]));
            }
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        DB::transaction(function () use ($path, $delimiter, $headerMap, $spec, $organization, &$created, &$updated, &$skipped, &$errors): void {
            foreach (CsvFacade::streamAssoc($path, $delimiter) as $lineNumber => $rawRow) {
                $mapped = $this->applyHeaderMap($rawRow, $headerMap);
                $normalized = $spec->normalize($mapped);

                // Zeilen ohne Pflichtwert still überspringen (Alt-Importer-Verhalten, fängt Leerzeilen ab).
                foreach ($spec->requiredColumns() as $required) {
                    if ($normalized[$required] === null || $normalized[$required] === '') {
                        $skipped++;

                        continue 2;
                    }
                }

                $issues = $spec->validateRow($normalized, $organization);
                if ($issues !== []) {
                    foreach ($issues as $issue) {
                        $errors[] = sprintf('Zeile %d: %s', $lineNumber, $issue->message);
                    }
                    $skipped++;

                    continue;
                }

                [$outcome, $issue] = $spec->upsert($normalized, $organization);
                switch ($outcome) {
                    case ImportOutcome::Created:
                        $created++;
                        break;
                    case ImportOutcome::Updated:
                        $updated++;
                        break;
                    case ImportOutcome::Failed:
                        if ($issue !== null) {
                            $errors[] = sprintf('Zeile %d: %s', $lineNumber, $issue->message);
                        }
                        $skipped++;
                        break;
                    default:
                        $skipped++;
                }
            }
        });

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * @return array{created: int, updated: int, skipped: int, errors: list<string>}
     */
    private function failure(string $message): array {
        return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => [$message]];
    }

    /**
     * @param  list<string>  $rawHeader
     * @return array<int, string|null>
     */
    private function buildHeaderMap(array $rawHeader, EntitySpec $spec): array {
        $aliases = [];
        foreach ($spec->headerAliases() as $alias => $canonical) {
            $aliases[mb_strtolower(trim($alias))] = $canonical;
        }
        foreach ($spec->columns() as $col) {
            $aliases[mb_strtolower(trim($col))] = $col;
        }

        $out = [];
        foreach ($rawHeader as $i => $h) {
            $out[$i] = $aliases[mb_strtolower(trim($h))] ?? null;
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $raw
     * @param  array<int, string|null>  $headerMap
     * @return array<string, string>
     */
    private function applyHeaderMap(array $raw, array $headerMap): array {
        $values = array_values($raw);
        $out = [];
        foreach ($headerMap as $i => $canonical) {
            if ($canonical === null) {
                continue;
            }
            $out[$canonical] = $values[$i] ?? '';
        }

        return $out;
    }
}
