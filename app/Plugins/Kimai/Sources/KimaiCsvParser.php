<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KimaiCsvParser.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Kimai\Sources;

use App\Plugins\Support\ImportedTimeEntry;
use App\Support\Toolkit\CsvFacade;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\CSV\StringHelper as CsvStringHelper;
use CommonToolkit\Helper\Data\StringHelper;

/**
 * Parst einen Kimai-Timesheet-CSV-Export zu {@see ImportedTimeEntry}-DTOs.
 *
 * Spalten werden über die Kopfzeile per Namen aufgelöst (case-insensitiv, EN
 * UND DE, spaltenreihenfolge-/spaltenmengen-tolerant — Rate-Spalten fehlen ohne
 * Rechte). Kimai-Besonderheiten (vgl. Kimai-Exporter):
 *  - `date` ist ein reines Datum (`Y-m-d`), `from`/`to` sind reine Uhrzeiten
 *    (`H:i`, kein Datum) → Ende = Datum + `to` (Mitternachtssprung: +1 Tag,
 *    wenn `to <= from`), sonst Start + Dauer.
 *  - `duration` ist `H:MM` (2 Teile = Stunden:Minuten, NICHT MM:SS!),
 *    `H:MM:SS` (3 Teile) oder dezimal `1.50` (Stunden mit Punkt).
 *  - Trennzeichen `,` oder `;` (Kimai-Option) → wird aus der Kopfzeile erkannt.
 *  - `billable` als `1/0` (Kimais BooleanFormatter), zusätzlich yes/ja tolerant.
 */
class KimaiCsvParser {
    /** Header-Aliasse (lowercase, EN + DE) → kanonischer Feldname. */
    private const ALIASES = [
        // Datum + Zeiten
        'date' => 'date', 'datum' => 'date',
        'from' => 'begin', 'begin' => 'begin', 'von' => 'begin', 'start' => 'begin',
        'to' => 'end', 'end' => 'end', 'bis' => 'end',
        'duration' => 'duration', 'dauer' => 'duration',
        // Zuordnung
        'customer' => 'customer', 'kunde' => 'customer',
        'project' => 'project', 'projekt' => 'project',
        'activity' => 'activity', 'tätigkeit' => 'activity', 'taetigkeit' => 'activity',
        'description' => 'description', 'beschreibung' => 'description',
        'billable' => 'billable', 'abrechenbar' => 'billable',
        'tags' => 'tags', 'schlagworte' => 'tags',
        'e-mail' => 'email', 'e-mail-adresse' => 'email', 'email' => 'email',
    ];

    /**
     * @return array<int, ImportedTimeEntry>
     */
    public function parse(string $content): array {
        $content = StringHelper::stripBom($content);
        $rows = $this->readRows($content);
        if (count($rows) < 2) {
            return [];
        }

        $header = array_shift($rows);
        $map = $this->mapColumns($header);
        // Ohne Datum und ohne (Ende|Dauer) lässt sich kein Intervall bilden.
        if (! isset($map['date']) || (! isset($map['end']) && ! isset($map['duration']))) {
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
            return CsvFacade::parseRows($content, CsvStringHelper::detectDelimiter($content, [',', ';'], 1, ','));
        } catch (\Throwable) {
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
            if ($key !== null && ! isset($map[$key])) {
                $map[$key] = $index;
            }
        }

        return $map;
    }

    /**
     * @param  array<int, string>  $row
     * @param  array<string, int>  $map
     */
    private function mapRow(array $row, array $map): ?ImportedTimeEntry {
        $get = static fn (string $field): ?string => isset($map[$field]) ? trim((string) ($row[$map[$field]] ?? '')) : null;

        $date = $get('date');
        $begin = $get('begin') ?? '00:00';
        $duration = $get('duration');
        $end = $get('end');
        if ($date === null || $date === '' || (($end === null || $end === '') && ($duration === null || $duration === ''))) {
            return null;
        }

        $startedAt = CarbonImmutable::parse(trim($date . ' ' . $begin));

        if ($end !== null && $end !== '') {
            $endedAt = CarbonImmutable::parse(trim($date . ' ' . $end));
            if ($endedAt->lessThanOrEqualTo($startedAt)) {
                // `to <= from` → Eintrag lief über Mitternacht.
                $endedAt = $endedAt->addDay();
            }
        } else {
            $endedAt = $startedAt->addSeconds($this->durationToSeconds((string) $duration));
        }

        $client = $this->nullIfBlank($get('customer'));
        $project = $this->nullIfBlank($get('project'));
        $activity = $this->nullIfBlank($get('activity'));
        $description = $this->nullIfBlank($get('description'));

        return new ImportedTimeEntry(
            entryKey: ImportedTimeEntry::csvKey($startedAt->toIso8601String(), $endedAt->toIso8601String(), $client, $project, $activity, $description, $get('email')),
            clientName: $client,
            projectName: $project,
            activity: $activity,
            description: $description,
            startedAt: $startedAt,
            endedAt: $endedAt,
            billable: $this->isBillable($get('billable')),
            userEmail: $this->nullIfBlank($get('email')),
            tags: $this->parseTags($get('tags')),
        );
    }

    /**
     * Kimai-Dauer → Sekunden: `H:MM:SS`, `H:MM` (Stunden:Minuten!) oder dezimal
     * `1.50` (Stunden mit Punkt).
     */
    private function durationToSeconds(string $duration): int {
        $duration = trim($duration);
        if ($duration === '') {
            return 0;
        }

        if (str_contains($duration, ':')) {
            $parts = array_map('intval', explode(':', $duration));

            return match (count($parts)) {
                3 => $parts[0] * 3600 + $parts[1] * 60 + $parts[2],
                2 => $parts[0] * 3600 + $parts[1] * 60, // H:MM — NICHT MM:SS
                default => 0,
            };
        }

        // Dezimalstunden (Kimai „Export dezimal", Punkt-separiert).
        return (int) round(((float) $duration) * 3600);
    }

    private function isBillable(?string $value): bool {
        $value = strtolower(trim((string) $value));

        return in_array($value, ['1', 'true', 'yes', 'ja', 'oui', 'sì', 'si'], true);
    }

    /**
     * @return list<string>
     */
    private function parseTags(?string $value): array {
        if ($value === null || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value)), static fn (string $t): bool => $t !== ''));
    }

    private function nullIfBlank(?string $value): ?string {
        return $value === null || $value === '' ? null : $value;
    }
}
