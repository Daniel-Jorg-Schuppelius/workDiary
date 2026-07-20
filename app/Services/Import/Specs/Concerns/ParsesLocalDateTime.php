<?php
/*
 * Created on   : Sun Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ParsesLocalDateTime.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import\Specs\Concerns;

use App\Models\Organization;
use App\Support\Tz;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Datums-/Zeit-Normalisierung für Zeiterfassungs-Specs (MVP-438).
 *
 * Kanonische Zeilen tragen `date` (`Y-m-d`) und `HH:MM`-Zeiten als lokale
 * Wanduhrzeit (CSV: wie eingegeben; iCal: bereits in die Org-Zeitzone
 * überführt). {@see localToUtc()} verankert sie über die **Organisations**-
 * Zeitzone in UTC — bewusst nicht über die Ambient-Zeitzone ({@see Tz::current()}),
 * da der Import-Job ohne angemeldeten Nutzer läuft.
 */
trait ParsesLocalDateTime {
    /** @var list<string> */
    private const IMPORT_DATE_FORMATS = ['Y-m-d', 'd.m.Y', 'd.m.y', 'd/m/Y', 'Y/m/d', 'm/d/Y'];

    protected function normalizeImportDate(?string $value): ?string {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $value = trim($value);
        foreach (self::IMPORT_DATE_FORMATS as $format) {
            try {
                $parsed = CarbonImmutable::createFromFormat('!' . $format, $value);
            } catch (Throwable) {
                continue;
            }
            if ($parsed instanceof CarbonImmutable && $parsed->format($format) === $value) {
                return $parsed->format('Y-m-d');
            }
        }

        return null;
    }

    protected function normalizeImportTime(?string $value): ?string {
        if ($value === null || trim($value) === '') {
            return null;
        }
        if (preg_match('/^(\d{1,2}):(\d{2})/', trim($value), $m) === 1) {
            $h = (int) $m[1];
            $min = (int) $m[2];
            if ($h <= 23 && $min <= 59) {
                return sprintf('%02d:%02d', $h, $min);
            }
        }

        return trim($value); // ungültiges Format → validateRow markiert es
    }

    protected function orgTimezone(Organization $organization): string {
        return Tz::isValid($organization->timezone) ? (string) $organization->timezone : Tz::FALLBACK;
    }

    /**
     * Verankert lokale Wanduhrzeit (`Y-m-d` + `HH:MM`) über die Org-Zeitzone in UTC.
     */
    protected function localToUtc(string $date, string $time, string $timezone): CarbonImmutable {
        $parsed = CarbonImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $time, $timezone);
        if (! $parsed instanceof CarbonImmutable) {
            throw new \RuntimeException('Ungültige Datums-/Zeitkombination: ' . $date . ' ' . $time);
        }

        return $parsed->setTimezone('UTC');
    }
}
