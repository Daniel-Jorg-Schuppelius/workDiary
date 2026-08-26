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
use Throwable;

/**
 * Datumsspalten im Import (MVP-707): normalize() lässt den Rohwert stehen,
 * validateRow() meldet nicht Deutbares als Formatfehler, upsert() wandelt über
 * das gemeinsame {@see ParsesMixedDate}-Muster — kein eigener Datumsparser.
 */
trait ValidatesImportDates {
    use ParsesMixedDate;

    /** `Y-m-d` oder null (leer); wirft bei nicht deutbarem Wert. */
    protected function dateString(mixed $value): ?string {
        return $this->parseDate($value)?->toDateString();
    }

    protected function isValidDate(mixed $value): bool {
        try {
            $this->parseDate($value);

            return true;
        } catch (Throwable) {
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
