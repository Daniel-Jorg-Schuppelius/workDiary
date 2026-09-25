<?php
/*
 * Created on   : Sun Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImportSourceFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import\Source;

use App\Enums\Import\ImportEntity;
use App\Models\Platform\Organization;
use App\Services\Import\Source\Ical\{AttendanceIcalMapper, ProjectTimeIcalMapper};
use App\Support\Tz;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\FileSystem\File;
use RuntimeException;

/**
 * Wählt die passende {@see ImportSource} für eine gespeicherte Import-Datei
 * (MVP-438). Erkennung über den Inhalt (`BEGIN:VCALENDAR`) — robust in Preflight
 * UND Job, ohne dass das Format zusätzlich persistiert werden muss. XLSX ist zu
 * diesem Zeitpunkt bereits in CSV überführt (A13), sieht hier also wie CSV aus.
 */
final class ImportSourceFactory {
    private const DETECT_BYTES = 1024;

    /**
     * @param  array<string, mixed>  $options  Quellen-Optionen (z. B. iCal-`category_allowlist`)
     */
    public function make(
        string $absolutePath,
        ImportEntity $entity,
        Organization $organization,
        ?string $delimiter = null,
        array $options = [],
    ): ImportSource {
        if ($this->isIcal($absolutePath)) {
            $mapper = $this->icalMapper($entity)
                ?? throw new RuntimeException((string) __('import.error.ical.unsupportedEntity'));

            $timezone = Tz::ofOrganization($organization);

            return new IcalImportSource(
                $absolutePath,
                $mapper,
                $timezone,
                $this->categoryAllowlist($options),
                $this->recurrenceWindow($options, $timezone),
            );
        }

        return new CsvImportSource($absolutePath, $delimiter);
    }

    /**
     * Prüft, ob die Datei ein iCalendar-Dokument ist (Kopfbytes enthalten
     * `BEGIN:VCALENDAR`).
     */
    public function isIcal(string $absolutePath): bool {
        $head = File::readPartial($absolutePath, self::DETECT_BYTES);

        return $head !== false && stripos($head, 'BEGIN:VCALENDAR') !== false;
    }

    private function icalMapper(ImportEntity $entity): ?IcalEventMapper {
        return match ($entity) {
            ImportEntity::Attendances => new AttendanceIcalMapper(),
            ImportEntity::ProjectTimes => new ProjectTimeIcalMapper(),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<string>
     */
    private function categoryAllowlist(array $options): array {
        $raw = $options['category_allowlist'] ?? null;
        if (! is_array($raw)) {
            return [];
        }

        $normalized = [];
        foreach ($raw as $value) {
            $value = mb_strtolower(trim((string) $value));
            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * Zeitraum der Serien-Auflösung (MVP-885): `recurrence_from`/`recurrence_until`
     * (JJJJ-MM-TT, bis einschließlich), sonst die letzten 90 Tage bis heute —
     * Zeiterfassung betrifft Vergangenes, künftige Vorkommen sind keine Arbeitszeit.
     *
     * @param  array<string, mixed>  $options
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function recurrenceWindow(array $options, string $timezone): array {
        $parse = static fn (mixed $raw): ?CarbonImmutable => is_string($raw) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1
            ? CarbonImmutable::createFromFormat('Y-m-d', $raw, $timezone)?->startOfDay()
            : null;
        $until = $parse($options['recurrence_until'] ?? null) ?? CarbonImmutable::now($timezone)->startOfDay();
        $from = $parse($options['recurrence_from'] ?? null) ?? $until->subDays(90);

        return [$from, $until->addDay()];
    }
}
