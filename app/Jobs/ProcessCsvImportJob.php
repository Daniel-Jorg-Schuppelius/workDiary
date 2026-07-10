<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcessCsvImportJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\Import\{ImportErrorCode, ImportRunState};
use App\Models\{AuditLog, ImportRun, ImportRunError};
use App\Services\Import\{CsvPreflightAnalyzer, EntitySpecRegistry, ImportOutcome};
use App\Support\Toolkit\CsvFacade;
use Carbon\CarbonImmutable;
use CommonToolkit\Parsers\CSVDocumentParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\{DB, Storage};
use Throwable;

/**
 * MVP-049 — Async-Job für den eigentlichen CSV-Import.
 *
 * Liest den per Preflight gespeicherten Datei-Pfad aus dem
 * {@see ImportRun}, mappt die Header über die zugehörige
 * {@see \App\Services\Import\EntitySpec} und upsertet Zeile für Zeile
 * in Chunks. Erfolgreiche Zeilen erhöhen `rows_created` / `rows_updated`,
 * Fehler werden als {@see ImportRunError} persistiert und der Status
 * abschließend auf `succeeded`, `partial` oder `failed` gesetzt.
 */
class ProcessCsvImportJob implements ShouldQueue {
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;
    public int $tries = 1;
    public const CHUNK = 500;

    public function __construct(public readonly int $importRunId) {}

    /**
     * Sicherheitsnetz: Stirbt der Worker mitten im Lauf (Timeout, Deploy,
     * OOM), bliebe der Run sonst für immer in `Running` — der Idempotenz-
     * Guard in handle() blockiert jede Wiederaufnahme. Die Re-Zustellung
     * nach `retry_after` endet bei tries=1 hier und schließt den Lauf
     * sichtbar als `failed` ab.
     */
    public function failed(?Throwable $exception): void {
        $run = ImportRun::query()->withoutGlobalScopes()->find($this->importRunId);
        if ($run === null || ! in_array($run->state, [ImportRunState::Running, ImportRunState::AwaitingApproval], true)) {
            return;
        }

        ImportRunError::create([
            'import_run_id' => $run->id,
            'row_number' => 0,
            'field' => null,
            'code' => ImportErrorCode::Persist,
            'message' => $exception?->getMessage() ?: 'Import-Job abgebrochen (Worker-Ausfall/Timeout).',
            'row_data' => null,
        ]);

        $run->state = ImportRunState::Failed;
        $run->finished_at = CarbonImmutable::now();
        $run->save();
    }

    public function handle(EntitySpecRegistry $registry): void {
        $run = ImportRun::query()->find($this->importRunId);
        if ($run === null) {
            return;
        }

        // Idempotenz-Guard: Nur ein noch nicht gestarteter Lauf wird verarbeitet.
        // Verhindert, dass versehentliches Mehrfach-Bestätigen denselben Import
        // mehrfach ausführt (jeder Klick dispatcht einen eigenen Job).
        if ($run->state !== ImportRunState::AwaitingApproval) {
            return;
        }

        $spec = $registry->for($run->entity);

        $organization = $run->organization;
        if ($organization === null) {
            $run->state = ImportRunState::Failed;
            $run->save();

            return;
        }

        $run->state = ImportRunState::Running;
        $run->started_at = CarbonImmutable::now();
        $run->save();

        AuditLog::create([
            'organization_id' => $run->organization_id,
            'user_id' => $run->created_by_user_id,
            'event' => 'import.started',
            'auditable_type' => ImportRun::class,
            'auditable_id' => $run->id,
            'changes' => [
                'entity' => $run->entity->value,
                'rows_total' => $run->rows_total,
                'input_filename' => $run->input_filename,
            ],
            'ip' => null,
            'user_agent' => null,
        ]);

        $path = Storage::disk(CsvPreflightAnalyzer::DISK)->path($run->storage_path);

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;

        try {
            $delimiter = $run->delimiter ?: CSVDocumentParser::detectDelimiter($path);
            $rawHeader = array_values(CSVDocumentParser::readHeader($path, $delimiter)->getColumnNames());
            $headerMap = $this->buildHeaderMap($rawHeader, $spec);

            $rowNumber = 0;
            $chunk = [];
            foreach (CsvFacade::streamAssoc($path, $delimiter) as $rawRow) {
                $rowNumber++;
                $mapped = $this->applyHeaderMap($rawRow, $headerMap);
                $normalized = $spec->normalize($mapped);
                $chunk[] = ['row' => $rowNumber, 'raw' => $mapped, 'norm' => $normalized];

                if (count($chunk) >= self::CHUNK) {
                    [$c, $u, $s, $f] = $this->processChunk($run, $spec, $chunk, $organization);
                    $created += $c;
                    $updated += $u;
                    $skipped += $s;
                    $failed += $f;
                    $chunk = [];
                }
            }
            if ($chunk !== []) {
                [$c, $u, $s, $f] = $this->processChunk($run, $spec, $chunk, $organization);
                $created += $c;
                $updated += $u;
                $skipped += $s;
                $failed += $f;
            }
        } catch (Throwable $e) {
            ImportRunError::create([
                'import_run_id' => $run->id,
                'row_number' => 0,
                'field' => null,
                'code' => ImportErrorCode::Persist,
                'message' => $e->getMessage(),
                'row_data' => null,
            ]);
            $failed++;
        }

        $run->rows_created = $created;
        $run->rows_updated = $updated;
        $run->rows_skipped = $skipped;
        $run->rows_failed = $failed;
        $run->finished_at = CarbonImmutable::now();
        $run->state = $this->finalState($created + $updated, $failed);
        $run->save();

        $event = match ($run->state) {
            ImportRunState::Succeeded => 'import.finished',
            ImportRunState::Partial => 'import.partial',
            default => 'import.finished',
        };

        AuditLog::create([
            'organization_id' => $run->organization_id,
            'user_id' => $run->created_by_user_id,
            'event' => $event,
            'auditable_type' => ImportRun::class,
            'auditable_id' => $run->id,
            'changes' => [
                'entity' => $run->entity->value,
                'state' => $run->state->value,
                'rows_created' => $created,
                'rows_updated' => $updated,
                'rows_skipped' => $skipped,
                'rows_failed' => $failed,
            ],
            'ip' => null,
            'user_agent' => null,
        ]);
    }

    /**
     * @param  list<array{row:int, raw:array<string,string>, norm:array<string,mixed>}>  $chunk
     * @return array{0:int,1:int,2:int,3:int}
     */
    private function processChunk(ImportRun $run, \App\Services\Import\EntitySpec $spec, array $chunk, \App\Models\Organization $organization): array {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;

        DB::transaction(function () use ($run, $spec, $chunk, $organization, &$created, &$updated, &$skipped, &$failed): void {
            foreach ($chunk as $entry) {
                $issues = $spec->validateRow($entry['norm'], $organization);
                if ($issues !== []) {
                    foreach ($issues as $issue) {
                        ImportRunError::create([
                            'import_run_id' => $run->id,
                            'row_number' => $entry['row'],
                            'field' => $issue->field,
                            'code' => $issue->code,
                            'message' => $issue->message,
                            'row_data' => $entry['raw'],
                        ]);
                    }
                    $skipped++;

                    continue;
                }

                [$outcome, $issue] = ($spec instanceof \App\Services\Import\InboxFirstSpec && $run->match_policy === 'inbox_first')
                    ? $spec->upsertOrStage($entry['norm'], $organization)
                    : $spec->upsert($entry['norm'], $organization);
                switch ($outcome) {
                    case ImportOutcome::Created:
                        $created++;
                        break;
                    case ImportOutcome::Updated:
                        $updated++;
                        break;
                    case ImportOutcome::Skipped:
                        $skipped++;
                        break;
                    case ImportOutcome::Failed:
                        $failed++;
                        if ($issue !== null) {
                            ImportRunError::create([
                                'import_run_id' => $run->id,
                                'row_number' => $entry['row'],
                                'field' => $issue->field,
                                'code' => $issue->code,
                                'message' => $issue->message,
                                'row_data' => $entry['raw'],
                            ]);
                        }
                        break;
                }
            }
        });

        return [$created, $updated, $skipped, $failed];
    }

    private function finalState(int $successful, int $failed): ImportRunState {
        if ($successful === 0 && $failed > 0) {
            return ImportRunState::Failed;
        }
        if ($failed > 0) {
            return ImportRunState::Partial;
        }

        return ImportRunState::Succeeded;
    }

    /**
     * @param  list<string>  $rawHeader
     * @return array<int, string|null>
     */
    private function buildHeaderMap(array $rawHeader, \App\Services\Import\EntitySpec $spec): array {
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
     * @param  array<string,string>  $raw
     * @param  array<int,string|null>  $headerMap
     * @return array<string,string>
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
