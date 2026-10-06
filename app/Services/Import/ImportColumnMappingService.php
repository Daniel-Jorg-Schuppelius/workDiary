<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImportColumnMappingService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import;

use App\Enums\Import\ImportRunState;
use App\Models\Integration\{ImportColumnMapping, ImportRun};
use App\Models\Platform\{Organization, User};
use App\Services\Import\Source\{CsvImportSource, ImportSourceFactory};
use CommonToolkit\Helper\Data\StringHelper;
use CommonToolkit\Helper\FileSystem\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Throwable;

/**
 * Gespeicherte Spaltenzuordnung (Feature 148/024, MVP-1020): was der
 * {@see HeaderMapper} an einer Kopfzeile nicht kennt, ordnet der Nutzer einmal
 * zu; die Zuordnung gilt ab dann für jede Datei dieser Importart.
 */
class ImportColumnMappingService {
    public function __construct(
        private readonly EntitySpecRegistry $specs,
        private readonly ImportSourceFactory $sources,
        private readonly CsvPreflightAnalyzer $analyzer,
    ) {}

    /**
     * Unbekannte Kopfzellen des Laufs und die noch freien Spalten der Spec;
     * leer für ZIP, iCal und abgeschlossene Läufe.
     *
     * @return array{headers: list<string>, columns: list<string>}
     */
    public function openHeaders(ImportRun $run): array {
        $none = ['headers' => [], 'columns' => []];
        if (! in_array($run->state, [ImportRunState::Failed, ImportRunState::AwaitingApproval], true) || $run->entity->acceptsZip()) {
            return $none;
        }
        $path = $this->storedPath($run);
        if ($path === null || $this->sources->isIcal($path)) {
            return $none;
        }

        try {
            $raw = (new CsvImportSource($path, $run->delimiter))->rawHeader();
        } catch (Throwable) {
            return $none;
        }

        $spec = $this->specs->for($run->entity);
        $mapped = HeaderMapper::map($spec, $raw, ImportColumnMapping::aliasesFor((int) $run->organization_id, $run->entity));
        $headers = [];
        foreach ($raw as $index => $cell) {
            $cell = trim($cell);
            if ($cell !== '' && ($mapped[$index] ?? null) === null) {
                $headers[] = $cell;
            }
        }

        return [
            'headers' => array_values(array_unique($headers)),
            'columns' => array_values(array_diff($spec->columns(), array_filter($mapped))),
        ];
    }

    /**
     * Speichert die Zuordnungen und prüft die Datei damit neu; der alte Lauf
     * wird verworfen, weil Vorschau und Fehler auf der alten Kopfzeile beruhen.
     *
     * @param  array<string, string>  $pairs  Kopfzelle => kanonische Spalte
     * @return array{run: ImportRun, saved: array<string, string>}
     */
    public function saveAndReanalyze(ImportRun $run, Organization $organization, array $pairs, User $actor): array {
        $open = $this->openHeaders($run);
        $saved = [];
        foreach ($pairs as $header => $column) {
            if (in_array($header, $open['headers'], true) && in_array($column, $open['columns'], true) && ! in_array($column, $saved, true)) {
                $saved[$header] = $column;
            }
        }

        DB::transaction(function () use ($run, $saved, $actor): void {
            foreach ($saved as $header => $column) {
                $mapping = ImportColumnMapping::query()->firstOrNew([
                    'organization_id' => $run->organization_id,
                    'entity' => $run->entity->value,
                    'source_header' => StringHelper::normalizeColumnName($header),
                ]);
                $mapping->created_by ??= $actor->id;
                $mapping->fill(['target_column' => $column, 'updated_by' => $actor->id])->save();
            }
        });

        if ($saved === []) {
            return ['run' => $run, 'saved' => []];
        }

        $path = (string) $this->storedPath($run);
        // XLSX liegt nach der Vorprüfung schon als CSV vor — der Name muss das sagen.
        $name = strtolower(File::extension($path)) === 'csv' && strtolower(File::extension($run->input_filename)) !== 'csv'
            ? File::filename($run->input_filename, false) . '.csv'
            : $run->input_filename;

        $fresh = $this->analyzer->analyze(
            new UploadedFile($path, $name, null, null, true),
            $run->entity,
            $organization,
            $actor,
            (string) $run->match_policy,
            (array) ($run->source_options ?? []),
        );
        $fresh->forceFill(['input_filename' => $run->input_filename])->save();

        Storage::disk(CsvPreflightAnalyzer::DISK)->delete($run->storage_path);
        $run->delete();

        return ['run' => $fresh, 'saved' => $saved];
    }

    private function storedPath(ImportRun $run): ?string {
        $stored = (string) $run->storage_path;
        $disk = Storage::disk(CsvPreflightAnalyzer::DISK);

        return $stored !== '' && $disk->exists($stored) ? $disk->path($stored) : null;
    }
}
