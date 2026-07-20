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
use App\Models\Organization;
use App\Services\Import\Source\Ical\{AttendanceIcalMapper, ProjectTimeIcalMapper};
use App\Support\Tz;
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

            return new IcalImportSource(
                $absolutePath,
                $mapper,
                $this->timezone($organization),
                $this->categoryAllowlist($options),
            );
        }

        return new CsvImportSource($absolutePath, $delimiter);
    }

    /**
     * Prüft, ob die Datei ein iCalendar-Dokument ist (Kopfbytes enthalten
     * `BEGIN:VCALENDAR`).
     */
    public function isIcal(string $absolutePath): bool {
        $handle = @fopen($absolutePath, 'rb');
        if ($handle === false) {
            return false;
        }
        $head = (string) fread($handle, self::DETECT_BYTES);
        fclose($handle);

        return stripos($head, 'BEGIN:VCALENDAR') !== false;
    }

    private function icalMapper(ImportEntity $entity): ?IcalEventMapper {
        return match ($entity) {
            ImportEntity::Attendances => new AttendanceIcalMapper(),
            ImportEntity::ProjectTimes => new ProjectTimeIcalMapper(),
            default => null,
        };
    }

    private function timezone(Organization $organization): string {
        return Tz::isValid($organization->timezone) ? (string) $organization->timezone : Tz::FALLBACK;
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
}
