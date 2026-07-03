<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CsvPreflightAnalyzer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import;

use App\Enums\Import\{ImportEntity, ImportErrorCode, ImportRunState};
use App\Models\{ImportRun, ImportRunError, Organization, User};
use App\Support\Toolkit\CsvFacade;
use CommonToolkit\Helper\FileSystem\File as ToolkitFile;
use CommonToolkit\Parsers\CSVDocumentParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Throwable;

/**
 * MVP-049 — CSV-Vorprüfung.
 *
 * Liest eine hochgeladene Datei, erkennt Delimiter, prüft Header gegen
 * die {@see EntitySpec}, normalisiert die ersten ≤20 Zeilen für eine
 * Vorschau und validiert die gesamte Datei zeilenweise. Erzeugt einen
 * {@see ImportRun} im Zustand `awaitingApproval` (alles ok) oder
 * `failed` (Header-Probleme); per-Zeilen-Fehler werden in
 * `import_run_errors` persistiert.
 *
 * Die eigentliche Upsert-Pipeline läuft asynchron im
 * {@see ProcessCsvImportJob}.
 */
class CsvPreflightAnalyzer {
    public const PREVIEW_ROWS = 20;
    public const MAX_ROWS = 50_000;
    public const MAX_BYTES = 5 * 1024 * 1024;
    public const DISK = 'local';
    public const STORAGE_DIR = 'imports';

    public function __construct(
        private readonly EntitySpecRegistry $registry,
    ) {}

    /**
     * Führt die Vorprüfung durch und persistiert einen Import-Lauf.
     */
    public function analyze(
        UploadedFile $file,
        ImportEntity $entity,
        Organization $organization,
        ?User $actor = null,
        string $matchPolicy = 'auto_create',
    ): ImportRun {
        $spec = $this->registry->for($entity);

        // Datei speichern (Tenant-Pfad), damit der Job sie später nachladen kann.
        $stored = $file->store(self::STORAGE_DIR . '/' . $organization->id, self::DISK);
        if ($stored === false) {
            throw new \RuntimeException('Konnte Import-Datei nicht speichern.');
        }
        $absolutePath = Storage::disk(self::DISK)->path($stored);
        $hash = ToolkitFile::hash($absolutePath);

        // Entitätsspezifische Vorverarbeitung des Roh-Inhalts (z. B. Excel-`sep=`-
        // Vorzeile entfernen). Nur bei tatsächlicher Änderung neu schreiben, damit
        // der Default-Pfad (keine Vorverarbeitung) das gestreamte File unberührt lässt.
        $raw = ToolkitFile::read($absolutePath);
        $processed = $spec->preprocessRaw($raw);
        if ($processed !== $raw) {
            Storage::disk(self::DISK)->put($stored, $processed);
        }

        $run = new ImportRun([
            'organization_id' => $organization->id,
            'entity' => $entity,
            'state' => ImportRunState::Preflight,
            'input_filename' => $file->getClientOriginalName(),
            'input_hash' => $hash,
            'storage_path' => $stored,
            'match_policy' => $matchPolicy === 'inbox_first' ? 'inbox_first' : 'auto_create',
            'created_by_user_id' => $actor?->id,
        ]);
        $run->save();

        try {
            $delimiter = CSVDocumentParser::detectDelimiter($absolutePath);
            $run->delimiter = $delimiter;

            $rawHeader = array_values(CSVDocumentParser::readHeader($absolutePath, $delimiter)->getColumnNames());
            [$headerMap, $headerIssues] = $this->mapHeader($rawHeader, $spec);

            if ($headerIssues !== []) {
                $this->persistHeaderIssues($run, $headerIssues);
                $run->state = ImportRunState::Failed;
                $run->save();

                return $run;
            }

            $preview = [];
            $rowsTotal = 0;
            $rowsFailed = 0;

            DB::transaction(function () use ($run, $absolutePath, $delimiter, $headerMap, $spec, $organization, &$preview, &$rowsTotal, &$rowsFailed): void {
                foreach (CsvFacade::streamAssoc($absolutePath, $delimiter) as $lineNumber => $rawRow) {
                    if ($rowsTotal >= self::MAX_ROWS) {
                        ImportRunError::create([
                            'import_run_id' => $run->id,
                            'row_number' => $rowsTotal + 1,
                            'field' => null,
                            'code' => ImportErrorCode::OutOfRange,
                            'message' => __('import.error.outOfRange.rowLimit', ['max' => self::MAX_ROWS]),
                            'row_data' => null,
                        ]);
                        $rowsFailed++;

                        break;
                    }

                    $rowsTotal++;
                    $rowNumber = $rowsTotal; // 1-basiert ohne Header

                    $mapped = $this->applyHeaderMap($rawRow, $headerMap);
                    $normalized = $spec->normalize($mapped);

                    $issues = $spec->validateRow($normalized, $organization);
                    foreach ($issues as $issue) {
                        ImportRunError::create([
                            'import_run_id' => $run->id,
                            'row_number' => $rowNumber,
                            'field' => $issue->field,
                            'code' => $issue->code,
                            'message' => $issue->message,
                            'row_data' => $mapped,
                        ]);
                    }
                    if ($issues !== []) {
                        $rowsFailed++;
                    }

                    if (count($preview) < self::PREVIEW_ROWS) {
                        $preview[] = [
                            'row' => $rowNumber,
                            'data' => $mapped,
                            'issues' => array_map(static fn($i) => [
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
        } catch (Throwable $e) {
            ImportRunError::create([
                'import_run_id' => $run->id,
                'row_number' => 0,
                'field' => null,
                'code' => ImportErrorCode::Format,
                'message' => __('import.error.format.parse', ['reason' => $e->getMessage()]),
                'row_data' => null,
            ]);
            $run->state = ImportRunState::Failed;
            $run->save();
        }

        return $run;
    }

    /**
     * @param  list<string>  $rawHeader
     * @return array{0: array<int, string|null>, 1: list<array{field: ?string, code: ImportErrorCode, message: string}>}
     */
    private function mapHeader(array $rawHeader, EntitySpec $spec): array {
        $aliases = [];
        foreach ($spec->headerAliases() as $alias => $canonical) {
            $aliases[$this->normKey($alias)] = $canonical;
        }
        foreach ($spec->columns() as $col) {
            $aliases[$this->normKey($col)] = $col;
        }

        $map = [];
        $seen = [];
        $issues = [];
        foreach ($rawHeader as $idx => $headerCell) {
            $key = $this->normKey($headerCell);
            $canonical = $aliases[$key] ?? null;
            $map[$idx] = $canonical;
            if ($canonical !== null) {
                if (isset($seen[$canonical])) {
                    $issues[] = [
                        'field' => $canonical,
                        'code' => ImportErrorCode::HeaderUnknown,
                        'message' => __('import.error.header.duplicate', ['column' => $canonical]),
                    ];
                } else {
                    $seen[$canonical] = true;
                }
            }
        }

        foreach ($spec->requiredColumns() as $required) {
            if (! isset($seen[$required])) {
                $issues[] = [
                    'field' => $required,
                    'code' => ImportErrorCode::HeaderMissing,
                    'message' => __('import.error.header.missing', ['column' => $required]),
                ];
            }
        }

        return [$map, $issues];
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

    /**
     * @param  list<array{field: ?string, code: ImportErrorCode, message: string}>  $issues
     */
    private function persistHeaderIssues(ImportRun $run, array $issues): void {
        foreach ($issues as $issue) {
            ImportRunError::create([
                'import_run_id' => $run->id,
                'row_number' => 0,
                'field' => $issue['field'],
                'code' => $issue['code'],
                'message' => $issue['message'],
                'row_data' => null,
            ]);
        }
    }

    private function normKey(string $key): string {
        return mb_strtolower(trim($key));
    }
}
