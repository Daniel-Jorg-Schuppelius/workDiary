<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglCsvParser.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Toggl\Sources;

use App\Support\Toolkit\CsvFacade;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\StringHelper;

/**
 * Parst einen Toggl „Detailed Report"-CSV-Export zu {@see TogglEntry}-DTOs.
 *
 * Spalten werden über die Kopfzeile per Namen aufgelöst (case-insensitiv,
 * sprach-/spaltenreihenfolge-tolerant): Client, Project, Description,
 * Start date, Start time, End date, End time, Duration, Billable, Email, Tags.
 * Da der Detailbericht keine Eintrags-ID enthält, wird der Idempotenz-Schlüssel
 * deterministisch aus Start/Ende/Client/Projekt/Beschreibung gehasht.
 */
class TogglCsvParser {
    /** Header-Aliasse (lowercase) → kanonischer Feldname. */
    private const ALIASES = [
        'client' => 'client',
        'project' => 'project',
        'description' => 'description',
        'start date' => 'start_date',
        'start time' => 'start_time',
        'end date' => 'end_date',
        'end time' => 'end_time',
        'duration' => 'duration',
        'billable' => 'billable',
        'email' => 'email',
        'tags' => 'tags',
        'schlagworte' => 'tags',
        'schlagwörter' => 'tags',
    ];

    /**
     * @return array<int, TogglEntry>
     */
    public function parse(string $content): array {
        // BOM entfernen, in Zeilen zerlegen (Feature 052: Common-Toolkit).
        $content = StringHelper::stripBom($content);
        $rows = $this->readRows($content);
        if (count($rows) < 2) {
            return [];
        }

        $header = array_shift($rows);
        $map = $this->mapColumns($header);
        if (! isset($map['start_date'], $map['start_time'], $map['duration'])) {
            return [];
        }

        $entries = [];
        foreach ($rows as $row) {
            $entry = $this->mapRow($row, $map);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function readRows(string $content): array {
        try {
            // Toolkit-Parser inkl. Kopfzeile (parse() trennt den Header selbst ab).
            return CsvFacade::parseRows($content, ',');
        } catch (\Throwable) {
            // Leere/unlesbare Datei → keine Zeilen (wie zuvor bei < 2 Zeilen).
            return [];
        }
    }

    /**
     * @param  array<int, string>  $header
     * @return array<string, int>
     */
    private function mapColumns(array $header): array {
        $map = [];
        foreach ($header as $index => $name) {
            $key = self::ALIASES[strtolower(trim($name))] ?? null;
            if ($key !== null) {
                $map[$key] = $index;
            }
        }

        return $map;
    }

    /**
     * @param  array<int, string>  $row
     * @param  array<string, int>  $map
     */
    private function mapRow(array $row, array $map): ?TogglEntry {
        $get = static fn(string $field): ?string => isset($map[$field]) ? trim((string) ($row[$map[$field]] ?? '')) : null;

        $startDate = $get('start_date');
        $startTime = $get('start_time') ?? '00:00:00';
        $duration = $get('duration');
        if ($startDate === null || $startDate === '' || $duration === null || $duration === '') {
            return null;
        }

        $startedAt = CarbonImmutable::parse(trim($startDate . ' ' . $startTime));

        $endDate = $get('end_date');
        $endTime = $get('end_time');
        if ($endDate !== null && $endDate !== '') {
            $endedAt = CarbonImmutable::parse(trim($endDate . ' ' . ($endTime ?? '00:00:00')));
        } else {
            $endedAt = $startedAt->addSeconds($this->durationToSeconds($duration));
        }

        $client = $this->nullIfBlank($get('client'));
        $project = $this->nullIfBlank($get('project'));
        $description = $this->nullIfBlank($get('description'));
        $email = $this->nullIfBlank($get('email'));

        return new TogglEntry(
            source: TogglEntry::SOURCE_CSV,
            entryKey: TogglEntry::csvKey($startedAt->toIso8601String(), $endedAt->toIso8601String(), $client, $project, $description, $email),
            clientName: $client,
            projectName: $project,
            description: $description,
            startedAt: $startedAt,
            endedAt: $endedAt,
            billable: $this->isBillable($get('billable')),
            userEmail: $email,
            tags: $this->parseTags($get('tags')),
            legacyEntryKey: TogglEntry::legacyCsvKey($startedAt->toIso8601String(), $endedAt->toIso8601String(), $client, $project, $description),
        );
    }

    /**
     * Toggl exportiert Tags kommasepariert in einer (gequoteten) Spalte.
     *
     * @return list<string>
     */
    private function parseTags(?string $value): array {
        if ($value === null || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value)), static fn (string $tag): bool => $tag !== ''));
    }

    private function durationToSeconds(string $duration): int {
        try {
            // Toolkit statt Handzerlegung (Vollscan 2026-08-23, C14, v1.26);
            // Toggl-Zweiteiler sind MM:SS. Kaputtes Format zählt wie bisher 0.
            return \CommonToolkit\ValueObjects\Duration::fromClock($duration, twoPartsAreHoursMinutes: false)->getTotalSeconds();
        } catch (\InvalidArgumentException) {
            return 0;
        }
    }

    private function isBillable(?string $value): bool {
        $value = strtolower((string) $value);

        return in_array($value, ['yes', 'ja', 'oui', 'sì', 'si', 'true', '1'], true);
    }

    private function nullIfBlank(?string $value): ?string {
        return $value === null || $value === '' ? null : $value;
    }
}
