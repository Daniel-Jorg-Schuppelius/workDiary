<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentZipImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import;

use App\Enums\Import\{ImportErrorCode, ImportRunState};
use App\Models\{ImportRun, ImportRunError, Organization, User};
use App\Services\Import\Specs\DocumentSpec;
use CommonToolkit\Helper\Data\CSV\StringHelper as CsvStringHelper;
use CommonToolkit\Helper\FileSystem\FileTypes\ZipFile;
use CommonToolkit\Parsers\CSVDocumentParser;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Dokument-ZIP-Import (MVP-707, Vollscan H20): ein Archiv mit `manifest.csv`
 * (Wurzel oder ein Ordnerpräfix) und den referenzierten Dateien. Gelesen wird
 * ausschließlich über {@see ZipFile::readEntries()} — Traversal-Einträge,
 * Entry- und Byte-Limits lehnt das Toolkit als Ganzes ab (kein Teilimport aus
 * einem manipulierten Archiv). Fehlende Ziele/Dateien/verbotene Typen sind
 * Zeilenfehler, nie ein Abbruch. Ergebnisse je Zeile als {@see ImportOutcome}.
 */
final class DocumentZipImportService {
    public const MANIFEST = 'manifest.csv';

    /** Upload-Limit des Archivs in KB (Laravel `max:`). */
    public const MAX_ZIP_KB = 51_200;

    public const MAX_ENTRIES = 1_000;

    /** Entpackte Gesamtgröße — readEntries hält alle Inhalte im Speicher. */
    public const MAX_UNPACKED_BYTES = 128 * 1024 * 1024;

    private const PREVIEW_ROWS = CsvPreflightAnalyzer::PREVIEW_ROWS;

    public function __construct(private readonly DocumentSpec $spec) {}

    /**
     * Öffnet das Archiv und mappt das Manifest auf die Spec-Spalten.
     *
     * @return array{delimiter: string, prefix: string, entries: array<string, string>, headerIssues: list<ValidationIssue>, rows: list<array{number: int, data: array<string, string>}>}
     *
     * @throws RuntimeException Archiv unlesbar/unsicher oder ohne Manifest
     */
    public function open(string $zipBinary): array {
        try {
            $entries = ZipFile::readEntries($zipBinary, self::MAX_ENTRIES, self::MAX_UNPACKED_BYTES);
        } catch (Throwable $e) {
            throw new RuntimeException((string) __('import.error.document.zipUnreadable', ['reason' => $e->getMessage()]), 0, $e);
        }

        $manifestKey = null;
        foreach (array_keys($entries) as $name) {
            if (strcasecmp(basename((string) $name), self::MANIFEST) === 0) {
                $manifestKey = (string) $name;
                break;
            }
        }
        if ($manifestKey === null) {
            throw new RuntimeException((string) __('import.error.document.manifestMissing'));
        }

        $dir = dirname($manifestKey);
        $prefix = $dir === '.' ? '' : $dir . '/';
        $csv = $this->spec->preprocessRaw(ltrim($entries[$manifestKey], "\u{FEFF}"));
        unset($entries[$manifestKey]);

        $delimiter = CsvStringHelper::detectDelimiter($csv);
        $document = CSVDocumentParser::fromString($csv, $delimiter, '"', true);
        $rawHeader = array_values(array_map('strval', $document->getColumnNames()));
        $headerMap = HeaderMapper::map($this->spec, $rawHeader);

        $rows = [];
        $number = 0;
        foreach ($document->getRows() as $line) {
            $number++;
            $values = array_values(array_map(static fn($field): string => (string) $field->getValue(), $line->getFields()));
            $rows[] = ['number' => $number, 'data' => HeaderMapper::apply($values, $headerMap)];
        }

        return [
            'delimiter' => $delimiter,
            'prefix' => $prefix,
            'entries' => $entries,
            'headerIssues' => HeaderMapper::issues($this->spec, $rawHeader),
            'rows' => $rows,
        ];
    }

    /**
     * Vorprüfung im Wizard: Kopfzeile, Zeilenvalidierung inkl. Datei-Präsenz,
     * Vorschau und Zähler auf dem {@see ImportRun}.
     */
    public function preflight(ImportRun $run, string $zipBinary, Organization $organization): void {
        $opened = $this->open($zipBinary);
        $run->delimiter = $opened['delimiter'];

        if ($opened['headerIssues'] !== []) {
            foreach ($opened['headerIssues'] as $issue) {
                $this->recordError($run, 0, $issue, null);
            }
            $run->state = ImportRunState::Failed;
            $run->save();

            return;
        }

        $preview = [];
        $rowsTotal = 0;
        $rowsFailed = 0;
        DB::transaction(function () use ($run, $opened, $organization, &$preview, &$rowsTotal, &$rowsFailed): void {
            foreach ($opened['rows'] as $row) {
                $rowsTotal++;
                $normalized = $this->spec->normalize($row['data']);
                $issues = $this->issuesFor($normalized, $organization, $opened);
                foreach ($issues as $issue) {
                    $this->recordError($run, $row['number'], $issue, $row['data']);
                }
                if ($issues !== []) {
                    $rowsFailed++;
                }
                if (count($preview) < self::PREVIEW_ROWS) {
                    $preview[] = [
                        'row' => $row['number'],
                        'data' => $row['data'],
                        'issues' => array_map(static fn(ValidationIssue $i): array => [
                            'field' => $i->field,
                            'code' => $i->code->value,
                            'message' => $i->message,
                        ], $issues),
                    ];
                }
            }
        });

        $run->rows_total = $rowsTotal;
        $run->rows_failed = $rowsFailed;
        $run->preview = $preview;
        $run->state = ImportRunState::AwaitingApproval;
        $run->save();
    }

    /**
     * Ausführung (Job): Dokumente anlegen, Zeilenfehler protokollieren.
     *
     * @return array{0: int, 1: int, 2: int, 3: int} created, updated, skipped, failed
     */
    public function import(ImportRun $run, string $zipBinary, Organization $organization, User $actor): array {
        $opened = $this->open($zipBinary);
        if ($opened['headerIssues'] !== []) {
            foreach ($opened['headerIssues'] as $issue) {
                $this->recordError($run, 0, $issue, null);
            }

            return [0, 0, 0, 1];
        }

        $counts = $this->process($opened, $organization, $actor, function (int $rowNumber, ValidationIssue $issue, array $raw) use ($run): void {
            $this->recordError($run, $rowNumber, $issue, $raw);
        });

        return [$counts['created'], $counts['updated'], $counts['skipped'], $counts['failed']];
    }

    /**
     * Synchrone Variante ohne ImportRun (Tests, Kommandozeile).
     *
     * @return array{created: int, updated: int, skipped: int, failed: int, errors: list<string>}
     */
    public function importBinary(string $zipBinary, Organization $organization, User $actor): array {
        $errors = [];
        try {
            $opened = $this->open($zipBinary);
        } catch (RuntimeException $e) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 1, 'errors' => [$e->getMessage()]];
        }
        foreach ($opened['headerIssues'] as $issue) {
            $errors[] = $issue->message;
        }
        if ($errors !== []) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 1, 'errors' => $errors];
        }

        $counts = $this->process($opened, $organization, $actor, static function (int $rowNumber, ValidationIssue $issue) use (&$errors): void {
            $errors[] = sprintf('Zeile %d: %s', $rowNumber, $issue->message);
        });

        return $counts + ['errors' => $errors];
    }

    /**
     * @param  array{prefix: string, entries: array<string, string>, rows: list<array{number: int, data: array<string, string>}>}  $opened
     * @param  callable(int, ValidationIssue, array<string, string>): void  $onIssue
     * @return array{created: int, updated: int, skipped: int, failed: int}
     */
    private function process(array $opened, Organization $organization, User $actor, callable $onIssue): array {
        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($opened['rows'] as $row) {
            $normalized = $this->spec->normalize($row['data']);
            $issues = $this->issuesFor($normalized, $organization, $opened);
            if ($issues !== []) {
                foreach ($issues as $issue) {
                    $onIssue($row['number'], $issue, $row['data']);
                }
                $counts['skipped']++;

                continue;
            }

            $content = $opened['entries'][$this->entryKey($opened['prefix'], (string) $normalized['file'])];
            [$outcome, $issue] = $this->spec->persist($normalized, $organization, $actor, $content);
            match ($outcome) {
                ImportOutcome::Created => $counts['created']++,
                ImportOutcome::Updated => $counts['updated']++,
                ImportOutcome::Skipped => $counts['skipped']++,
                ImportOutcome::Failed => $counts['failed']++,
            };
            if ($outcome === ImportOutcome::Failed && $issue !== null) {
                $onIssue($row['number'], $issue, $row['data']);
            }
        }

        return $counts;
    }

    /**
     * Spec-Validierung plus Datei-Präsenz im Archiv.
     *
     * @param  array<string, mixed>  $normalized
     * @param  array{prefix: string, entries: array<string, string>}  $opened
     * @return list<ValidationIssue>
     */
    private function issuesFor(array $normalized, Organization $organization, array $opened): array {
        $issues = $this->spec->validateRow($normalized, $organization);
        $file = $normalized['file'] ?? null;
        if ($file !== null && ! isset($opened['entries'][$this->entryKey($opened['prefix'], (string) $file)])) {
            $issues[] = new ValidationIssue(
                ImportErrorCode::FkMissing,
                'file',
                (string) __('import.error.document.fileMissing', ['file' => $file]),
            );
        }

        return $issues;
    }

    private function entryKey(string $prefix, string $file): string {
        return $prefix . $file;
    }

    /**
     * @param  array<string, string>|null  $rowData
     */
    private function recordError(ImportRun $run, int $rowNumber, ValidationIssue $issue, ?array $rowData): void {
        ImportRunError::create([
            'import_run_id' => $run->id,
            'row_number' => $rowNumber,
            'field' => $issue->field,
            'code' => $issue->code,
            'message' => $issue->message,
            'row_data' => $rowData,
        ]);
    }
}
