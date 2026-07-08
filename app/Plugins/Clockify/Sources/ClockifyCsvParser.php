<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClockifyCsvParser.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Clockify\Sources;

use App\Plugins\Support\ImportedTimeEntry;
use App\Support\Toolkit\CsvFacade;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\StringHelper;

/**
 * Parst ein Clockify-Detailed-Report-CSV zu {@see ImportedTimeEntry}-DTOs.
 *
 * Clockify-Besonderheiten (vgl. Anbindungs-Recherche §20):
 *  - Header: `Project, Client, Description, Task, User, Group, Email, Tags,
 *    Billable, Start Date, Start Time, End Date, End Time, Duration (h),
 *    Duration (decimal), Billable Rate (XXX), Billable Amount (XXX)` — die
 *    Rate-/Amount-Spalten tragen die Währung im Namen (Präfix-Match!),
 *    Spalten sind im Export abwählbar (Teilmengen tolerieren).
 *  - Datums-/Zeitformat folgt der Profileinstellung: ISO `YYYY-MM-DD`,
 *    `DD.MM.YYYY` oder Slash-Format (`MM/DD/YYYY` vs `DD/MM/YYYY` — Heuristik
 *    über Werte > 12, sonst US-Default); Zeit 24h oder 12h mit AM/PM.
 *  - `Duration (h)` ist `H:MM:SS`, `Duration (decimal)` Dezimalstunden.
 *  - `Billable` als Yes/No.
 */
class ClockifyCsvParser {
    /** Header-Aliasse (lowercase, EN + DE defensiv) → kanonischer Feldname. */
    private const ALIASES = [
        'project' => 'project', 'projekt' => 'project',
        'client' => 'client', 'kunde' => 'client',
        'description' => 'description', 'beschreibung' => 'description',
        'task' => 'task', 'aufgabe' => 'task',
        'email' => 'email', 'e-mail' => 'email',
        'tags' => 'tags', 'schlagworte' => 'tags', 'schlagwörter' => 'tags',
        'billable' => 'billable', 'abrechenbar' => 'billable',
        'start date' => 'start_date', 'startdatum' => 'start_date',
        'start time' => 'start_time', 'startzeit' => 'start_time',
        'end date' => 'end_date', 'enddatum' => 'end_date',
        'end time' => 'end_time', 'endzeit' => 'end_time',
        'duration (h)' => 'duration_h', 'dauer (h)' => 'duration_h',
        'duration (decimal)' => 'duration_dec', 'dauer (dezimal)' => 'duration_dec',
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
        // Ohne Startdatum und ohne (Ende|Dauer) lässt sich kein Intervall bilden.
        if (! isset($map['start_date']) || (! isset($map['end_time']) && ! isset($map['duration_h']) && ! isset($map['duration_dec']))) {
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
            return CsvFacade::parseRows($content, $this->sniffDelimiter($content));
        } catch (\Throwable) {
            return [];
        }
    }

    /** Erkennt das Trennzeichen aus der ersten Zeile (`;` vs `,`). */
    private function sniffDelimiter(string $content): string {
        $firstLine = strtok($content, "\r\n");
        $firstLine = $firstLine === false ? '' : $firstLine;

        return substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
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

        $startDate = $this->parseDate((string) $get('start_date'));
        if ($startDate === null) {
            return null;
        }

        $startTime = $this->nullIfBlank($get('start_time')) ?? '00:00:00';
        $startedAt = $this->combine($startDate, $startTime);
        if ($startedAt === null) {
            return null;
        }

        $endTime = $this->nullIfBlank($get('end_time'));
        if ($endTime !== null) {
            $endDate = $this->parseDate((string) $get('end_date')) ?? $startDate;
            $endedAt = $this->combine($endDate, $endTime);
            if ($endedAt === null) {
                return null;
            }
            if ($endedAt->lessThanOrEqualTo($startedAt) && ! isset($map['end_date'])) {
                // Ohne End-Datum-Spalte: Eintrag lief über Mitternacht.
                $endedAt = $endedAt->addDay();
            }
        } else {
            $seconds = $this->durationToSeconds($get('duration_h'), $get('duration_dec'));
            if ($seconds <= 0) {
                return null;
            }
            $endedAt = $startedAt->addSeconds($seconds);
        }

        $client = $this->nullIfBlank($get('client'));
        $project = $this->nullIfBlank($get('project'));
        $task = $this->nullIfBlank($get('task'));
        $description = $this->nullIfBlank($get('description'));
        $email = $this->nullIfBlank($get('email'));

        return new ImportedTimeEntry(
            entryKey: ImportedTimeEntry::csvKey($startedAt->toIso8601String(), $endedAt->toIso8601String(), $client, $project, $task, $description, $email),
            clientName: $client,
            projectName: $project,
            activity: $task,
            description: $description,
            startedAt: $startedAt,
            endedAt: $endedAt,
            billable: $this->isBillable($get('billable')),
            userEmail: $email,
            tags: $this->parseTags($get('tags')),
        );
    }

    /**
     * Profilabhängige Datumsformate: ISO, `DD.MM.YYYY` oder Slash
     * (`MM/DD/YYYY` vs `DD/MM/YYYY` — Werte > 12 entscheiden, sonst
     * US-Default MM/DD wie das Clockify-Standardprofil).
     */
    private function parseDate(string $date): ?CarbonImmutable {
        $date = trim($date);
        if ($date === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $date, $m) === 1) {
            return CarbonImmutable::create((int) $m[1], (int) $m[2], (int) $m[3])?->startOfDay();
        }

        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $date, $m) === 1) {
            return CarbonImmutable::create((int) $m[3], (int) $m[2], (int) $m[1])?->startOfDay();
        }

        if (preg_match('~^(\d{1,2})/(\d{1,2})/(\d{4})$~', $date, $m) === 1) {
            $a = (int) $m[1];
            $b = (int) $m[2];
            $year = (int) $m[3];

            [$month, $day] = match (true) {
                $a > 12 => [$b, $a], // eindeutig DD/MM
                $b > 12 => [$a, $b], // eindeutig MM/DD
                default => [$a, $b], // mehrdeutig → US-Default MM/DD
            };

            return CarbonImmutable::create($year, $month, $day)?->startOfDay();
        }

        return null;
    }

    /** Kombiniert Datum + Uhrzeit (24h `HH:MM[:SS]` oder 12h mit AM/PM). */
    private function combine(CarbonImmutable $date, string $time): ?CarbonImmutable {
        try {
            return CarbonImmutable::parse($date->toDateString() . ' ' . trim($time));
        } catch (\Throwable) {
            return null;
        }
    }

    /** `Duration (h)` = H:MM:SS; `Duration (decimal)` = Dezimalstunden. */
    private function durationToSeconds(?string $hms, ?string $decimal): int {
        $hms = trim((string) $hms);
        if ($hms !== '' && str_contains($hms, ':')) {
            $parts = array_map('intval', explode(':', $hms));

            return match (count($parts)) {
                3 => $parts[0] * 3600 + $parts[1] * 60 + $parts[2],
                2 => $parts[0] * 3600 + $parts[1] * 60,
                default => 0,
            };
        }

        $decimal = str_replace(',', '.', trim((string) $decimal));
        if ($decimal !== '' && is_numeric($decimal)) {
            return (int) round(((float) $decimal) * 3600);
        }

        return 0;
    }

    private function isBillable(?string $value): bool {
        $value = strtolower(trim((string) $value));

        return in_array($value, ['yes', '1', 'true', 'ja', 'oui', 'sì', 'si'], true);
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
