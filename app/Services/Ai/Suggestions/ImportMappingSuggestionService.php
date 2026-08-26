<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImportMappingSuggestionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Suggestions;

use App\Models\Ai\AiTextSuggestion;
use App\Models\{ImportRun, Organization, User};
use App\Services\Ai\AiInvocationService;
use App\Services\Ai\Dto\{AiClassificationResult, ClassifyRequest};
use App\Services\Ai\Exceptions\AiException;
use App\Services\Ai\Suggestions\Concerns\DecidesSuggestions;
use App\Services\Import\{CsvPreflightAnalyzer, EntitySpecRegistry, HeaderMapper};
use App\Services\Import\Source\CsvImportSource;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * KI-Welle 3 — Spaltenzuordnung der Import-Drehscheibe (Feature 148,
 * MVP-732; Feature 053): scheitert die Vorprüfung an der Kopfzeile, sagt die
 * KI, welche kanonische Spalte hinter einer unbekannten Überschrift stecken
 * dürfte.
 *
 * Der {@see HeaderMapper} (MVP-707) bleibt die verbindliche, deterministische
 * Zuordnung — er läuft ZUERST; die KI bekommt nur die Kopfzellen, die er
 * nicht kennt, und nur die dann noch freien Spec-Spalten als Katalog.
 * In den Prompt gehen ausschließlich KOPFZELLEN, nie Datenzeilen (daher
 * `low`). Das Ergebnis ist ein Hinweis zum Umbenennen der Kopfzeile — es
 * verändert weder Lauf noch Datei (nie Auto-Apply).
 */
class ImportMappingSuggestionService {
    use DecidesSuggestions;

    public const CAPABILITY = 'import.column_mapping';

    /** Höchstzahl unbekannter Kopfzellen, für die gefragt wird (Budget). */
    public const MAX_UNKNOWN_COLUMNS = 10;

    public function __construct(
        private readonly AiInvocationService $invocation,
        private readonly EntitySpecRegistry $specs,
    ) {}

    /**
     * Zuordnungsvorschlag für die unbekannten Kopfzellen — null, wenn es
     * keine gibt oder die KI keine Spalte aus dem Katalog nennt.
     */
    public function suggestMapping(ImportRun $run, ?User $user, ?int $connectionId = null): ?AiTextSuggestion {
        $organization = $this->organizationOf($run);
        $spec = $this->specs->for($run->entity);

        $header = $this->rawHeaderOf($run);
        if ($header === []) {
            throw new AiException((string) __('ai.error.import_header_missing'));
        }

        // Schritt 1 — deterministisch: was der HeaderMapper kennt, bleibt seins.
        $mapped = HeaderMapper::map($spec, $header);
        $taken = array_values(array_filter($mapped, static fn (?string $c): bool => $c !== null));

        $unknown = [];
        foreach ($header as $index => $cell) {
            $cell = trim($cell);
            if ($cell !== '' && ($mapped[$index] ?? null) === null) {
                $unknown[] = $cell;
            }
        }
        $unknown = array_slice(array_values(array_unique($unknown)), 0, self::MAX_UNKNOWN_COLUMNS);
        if ($unknown === []) {
            return null;
        }

        // Schritt 2 — KI: je unbekannter Kopfzelle EIN Klassifikationsaufruf
        // gegen die noch freien Spec-Spalten.
        $entries = [];
        foreach ($unknown as $cell) {
            $catalog = array_values(array_diff($spec->columns(), $taken));
            if ($catalog === []) {
                break;
            }

            $request = new ClassifyRequest(
                text: $cell,
                catalog: $catalog,
                multiple: false,
                language: app()->getLocale(),
            );

            $result = $this->invocation->invoke($organization, self::CAPABILITY, $request, $connectionId);
            $payload = $result->result;
            if (! $payload instanceof AiClassificationResult) {
                throw new AiException((string) __('ai.error.unexpected_classification_type'));
            }

            $column = $payload->values[0] ?? null;
            if ($column === null || ! in_array($column, $catalog, true)) {
                continue; // Katalog-Garantie: Unbekanntes wird verworfen.
            }

            $entries[] = ['header' => $cell, 'column' => $column];
            $taken[] = $column;
            $last = $result;
        }

        if ($entries === [] || ! isset($last)) {
            return null;
        }

        return $this->storeProposal(
            (int) $organization->id,
            $run,
            self::CAPABILITY,
            implode(' | ', $header),
            (string) json_encode($entries, JSON_UNESCAPED_UNICODE),
            $last,
            $user,
        );
    }

    /**
     * Vorgeschlagene Zuordnungen.
     *
     * @return list<array{header: string, column: string}>
     */
    public static function mappingValues(AiTextSuggestion $suggestion): array {
        $decoded = json_decode((string) $suggestion->suggestion, true);
        if (! is_array($decoded)) {
            return [];
        }

        $entries = [];
        foreach ($decoded as $row) {
            if (is_array($row) && isset($row['header'], $row['column'])) {
                $entries[] = ['header' => (string) $row['header'], 'column' => (string) $row['column']];
            }
        }

        return $entries;
    }

    /** @return list<string> */
    private function rawHeaderOf(ImportRun $run): array {
        if ($run->entity->acceptsZip()) {
            throw new AiException((string) __('ai.error.import_header_missing'));
        }

        $stored = (string) $run->storage_path;
        if ($stored === '' || ! Storage::disk(CsvPreflightAnalyzer::DISK)->exists($stored)) {
            throw new AiException((string) __('ai.error.import_header_missing'));
        }

        try {
            return (new CsvImportSource(
                Storage::disk(CsvPreflightAnalyzer::DISK)->path($stored),
                $run->delimiter,
            ))->rawHeader();
        } catch (Throwable $e) {
            throw new AiException((string) __('ai.error.import_header_missing'), 0, $e);
        }
    }

    private function organizationOf(ImportRun $run): Organization {
        return $run->organization ?? Organization::query()->withoutGlobalScopes()->findOrFail($run->organization_id);
    }
}
