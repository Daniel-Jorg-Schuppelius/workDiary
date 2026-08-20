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
use App\Services\Import\Source\{CsvImportSource, ImportSource, ImportSourceFactory};
use CommonToolkit\Helper\Data\CSV\StringHelper;
use CommonToolkit\Helper\FileSystem\File as ToolkitFile;
use CommonToolkit\Parsers\XLSXDocumentParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use RuntimeException;
use Throwable;

/**
 * MVP-049 — CSV-/XLSX-Vorprüfung.
 *
 * Liest eine hochgeladene Datei (XLSX wird vorab über den Toolkit-Parser in
 * die interne CSV-Struktur überführt, A13), erkennt Delimiter, prüft Header
 * gegen die {@see EntitySpec}, normalisiert die ersten ≤20 Zeilen für eine
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
        private readonly ImportSourceFactory $sources,
    ) {}

    /**
     * Führt die Vorprüfung durch und persistiert einen Import-Lauf.
     *
     * @param  array<string, mixed>  $options  Quellen-Optionen (z. B. iCal-`category_allowlist`)
     */
    public function analyze(
        UploadedFile $file,
        ImportEntity $entity,
        Organization $organization,
        ?User $actor = null,
        string $matchPolicy = 'auto_create',
        array $options = [],
    ): ImportRun {
        $spec = $this->registry->for($entity);

        // Datei speichern (Tenant-Pfad), damit der Job sie später nachladen kann.
        $stored = $file->store(self::STORAGE_DIR . '/' . $organization->id, self::DISK);
        if ($stored === false) {
            throw new RuntimeException('Konnte Import-Datei nicht speichern.');
        }
        $absolutePath = Storage::disk(self::DISK)->path($stored);
        $hash = ToolkitFile::hash($absolutePath);
        $isXlsx = strtolower($file->getClientOriginalExtension()) === 'xlsx';

        $run = new ImportRun([
            'organization_id' => $organization->id,
            'entity' => $entity,
            'state' => ImportRunState::Preflight,
            'input_filename' => $file->getClientOriginalName(),
            'input_hash' => $hash,
            'storage_path' => $stored,
            'match_policy' => $matchPolicy === 'inbox_first' ? 'inbox_first' : 'auto_create',
            'source_options' => $options === [] ? null : $options,
            'created_by_user_id' => $actor?->id,
        ]);
        $run->save();

        try {
            // A13: XLSX vorab in die interne CSV-Struktur überführen (erstes
            // Tabellenblatt) — danach läuft EIN gemeinsamer Wizard-Pfad.
            if ($isXlsx) {
                $stored = $this->convertXlsxToCsv($absolutePath, $stored);
                $run->storage_path = $stored;
                $absolutePath = Storage::disk(self::DISK)->path($stored);
            }

            // MVP-438: Format-Schicht. iCal überspringt Kopfzeile/Delimiter und
            // liefert kanonische Zeilen direkt aus den VEVENTs.
            if ($this->sources->isIcal($absolutePath)) {
                $source = $this->sources->make($absolutePath, $entity, $organization, null, $options);
            } else {
                // Entitätsspezifische Vorverarbeitung des Roh-Inhalts (z. B. Excel-`sep=`-
                // Vorzeile entfernen). Nur bei tatsächlicher Änderung neu schreiben, damit
                // der Default-Pfad (keine Vorverarbeitung) das gestreamte File unberührt lässt.
                $raw = ToolkitFile::read($absolutePath);
                $processed = $spec->preprocessRaw($raw);
                if ($processed !== $raw) {
                    Storage::disk(self::DISK)->put($stored, $processed);
                }

                $csv = new CsvImportSource($absolutePath);
                $run->delimiter = $csv->delimiter();

                $headerIssues = $csv->headerIssues($spec);
                if ($headerIssues !== []) {
                    $this->persistHeaderIssues($run, $headerIssues);
                    $run->state = ImportRunState::Failed;
                    $run->save();

                    return $run;
                }
                $source = $csv;
            }

            $result = $this->ingestRows($run, $source, $spec, $organization);

            // Rang 58/A13: nur wirklich unbekannte Werte behalten (kein Mapping,
            // kein Namens-Tag, kein eindeutiger Klassifikations-Code) — sie
            // blockieren die Bestätigung bis zur Zuordnung.
            if ($spec instanceof \App\Services\Import\HasMappableValues && $result['unresolvedValues'] !== []) {
                $pending = [];
                foreach ($result['unresolvedValues'] as $value) {
                    if ($spec->unresolvedMappableValues($organization, $value, $spec->entity()->value) !== []) {
                        $pending[] = $value;
                    }
                }
                sort($pending);
                $run->unresolved_values = $pending === [] ? null : [$spec->mappableColumn() => $pending];
            }

            $run->rows_total = $result['rowsTotal'];
            $run->rows_failed = $result['rowsFailed'];
            $run->preview = $result['preview'];
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
     * Geteilte Zeilenaufnahme über die {@see ImportSource} (MVP-438): validiert
     * Datenzeilen, sammelt die Vorschau und persistiert Zeilenfehler sowie nicht
     * blockierende Quellen-Hinweise (z. B. übersprungene iCal-Ganztags-Events).
     *
     * @return array{preview: list<array<string, mixed>>, rowsTotal: int, rowsFailed: int, unresolvedValues: array<string, string>}
     */
    private function ingestRows(ImportRun $run, ImportSource $source, EntitySpec $spec, Organization $organization): array {
        $preview = [];
        $rowsTotal = 0;
        $rowsFailed = 0;
        // Rang 58: unbekannte Tag-/Kategorie-Quellwerte fürs Mapping-Formular.
        $unresolvedValues = [];

        DB::transaction(function () use ($run, $source, $spec, $organization, &$preview, &$rowsTotal, &$rowsFailed, &$unresolvedValues): void {
            foreach ($source->rows($spec) as $sourceRow) {
                // Nicht blockierende Quellen-Hinweise (iCal-Ganztags-/OOF-/Serien-Zeilen).
                $warning = $sourceRow->warning;
                if ($warning !== null) {
                    ImportRunError::create([
                        'import_run_id' => $run->id,
                        'row_number' => $sourceRow->number,
                        'field' => $warning->field,
                        'code' => $warning->code,
                        'message' => $warning->message,
                        'row_data' => null,
                    ]);
                    $rowsFailed++;
                    if (count($preview) < self::PREVIEW_ROWS) {
                        $preview[] = [
                            'row' => $sourceRow->number,
                            'data' => [],
                            'issues' => [[
                                'field' => $warning->field,
                                'code' => $warning->code->value,
                                'message' => $warning->message,
                            ]],
                        ];
                    }

                    continue;
                }

                if ($rowsTotal >= self::MAX_ROWS) {
                    ImportRunError::create([
                        'import_run_id' => $run->id,
                        'row_number' => $sourceRow->number,
                        'field' => null,
                        'code' => ImportErrorCode::OutOfRange,
                        'message' => __('import.error.outOfRange.rowLimit', ['max' => self::MAX_ROWS]),
                        'row_data' => null,
                    ]);
                    $rowsFailed++;

                    break;
                }

                $rowsTotal++;
                $mapped = $sourceRow->data;
                $normalized = $spec->normalize($mapped);

                if ($spec instanceof \App\Services\Import\HasMappableValues) {
                    $raw = $normalized[$spec->mappableColumn()] ?? null;
                    foreach ($spec->splitMappableValues(is_string($raw) ? $raw : null) as $value) {
                        $unresolvedValues[\App\Models\ImportValueMapping::normalize($value)] = $value;
                    }
                }

                $issues = $spec->validateRow($normalized, $organization);
                foreach ($issues as $issue) {
                    ImportRunError::create([
                        'import_run_id' => $run->id,
                        'row_number' => $sourceRow->number,
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
                        'row' => $sourceRow->number,
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

        return [
            'preview' => $preview,
            'rowsTotal' => $rowsTotal,
            'rowsFailed' => $rowsFailed,
            'unresolvedValues' => $unresolvedValues,
        ];
    }

    /**
     * @param  list<\App\Services\Import\ValidationIssue>  $issues
     */
    private function persistHeaderIssues(ImportRun $run, array $issues): void {
        foreach ($issues as $issue) {
            ImportRunError::create([
                'import_run_id' => $run->id,
                'row_number' => 0,
                'field' => $issue->field,
                'code' => $issue->code,
                'message' => $issue->message,
                'row_data' => null,
            ]);
        }
    }

    /**
     * A13: Überführt das ERSTE Tabellenblatt einer XLSX-Datei über den
     * Toolkit-Parser in die interne CSV-Struktur (Kopfzeile + Datenzeilen,
     * Semikolon-getrennt) und ersetzt die gespeicherte Datei. Zellwerte
     * werden so normalisiert, wie der CSV-Pfad sie erwartet (Datum `Y-m-d`
     * bzw. `Y-m-d H:i:s`, Zahlen mit Dezimalpunkt, Bool als 1/0).
     *
     * @param  string  $absolutePath  absoluter Pfad der gespeicherten XLSX-Datei
     * @param  string  $stored  relativer Storage-Pfad der XLSX-Datei
     * @return string relativer Storage-Pfad der erzeugten CSV-Datei
     */
    private function convertXlsxToCsv(string $absolutePath, string $stored): string {
        try {
            $document = XLSXDocumentParser::fromFile($absolutePath, hasHeader: false, sheetIndex: 0);
        } catch (Throwable) {
            throw new RuntimeException((string) __('import.error.format.xlsxUnreadable'));
        }

        $sheet = $document->getFirstSheet();
        if ($sheet === null || $sheet->count() === 0) {
            throw new RuntimeException((string) __('import.error.format.xlsxEmpty'));
        }

        $lines = [];
        foreach ($sheet->getRows() as $row) {
            $cells = array_map(
                fn (\CommonToolkit\Entities\XLSX\Cell $cell): string => $cell->toCanonicalString(),
                $row->getCells(),
            );
            $lines[] = StringHelper::encodeLine($cells, ';', '"');
        }

        $csvPath = (string) preg_replace('/\.[A-Za-z0-9]+$/', '', $stored) . '.csv';
        Storage::disk(self::DISK)->put($csvPath, implode("\r\n", $lines) . "\r\n");
        Storage::disk(self::DISK)->delete($stored);

        return $csvPath;
    }
}
