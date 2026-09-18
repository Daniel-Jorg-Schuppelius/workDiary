<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ValidatesImportDates.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import\Specs\Concerns;

use App\Services\Concerns\ParsesMixedDate;
use App\Services\Import\ValidationIssue;
use CommonToolkit\Enums\DateTimeFormat;
use CommonToolkit\Helper\Data\DateHelper;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Datumsspalten im Import (MVP-707): normalize() lässt den Rohwert stehen,
 * validateRow() meldet nicht Deutbares als Formatfehler, upsert() wandelt über
 * {@see DateHelper::normalizeToIso()} — kein eigener Datumsparser. Der Trait
 * {@see ParsesMixedDate} bleibt für Specs, die aus bereits normalisierten
 * ISO-Daten weiterrechnen.
 */
trait ValidatesImportDates {
    use ParsesMixedDate;

    /** `Y-m-d` oder null (leer); wirft bei nicht deutbarem Wert. */
    protected function dateString(mixed $value): ?string {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        // Deutsche Lesart (01/02/2026 = 1. Februar), keine relativen Angaben
        // („next monday") und kein Überlauf — Carbon::parse machte aus dem
        // 31.02. still den 3. März.
        $iso = DateHelper::normalizeToIso(trim((string) $value), DateTimeFormat::DE);
        if ($iso === null) {
            throw new InvalidArgumentException('Kein gültiges Datum: ' . (string) $value);
        }

        return substr($iso, 0, 10);
    }

    protected function isValidDate(mixed $value): bool {
        try {
            $this->dateString($value);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * @param  list<ValidationIssue>  $issues
     * @param  array<string, mixed>  $row
     */
    protected function validateDateField(array &$issues, array $row, string $field): void {
        $value = $row[$field] ?? null;
        if ($value === null || $value === '') {
            return;
        }
        if (! $this->isValidDate($value)) {
            $issues[] = $this->formatIssue($field, (string) __('import.error.format.date'));
        }
    }

    abstract protected function formatIssue(string $field, string $reason): ValidationIssue;
}
