<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FormatsSectionValues.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\ProcedureDocumentation;

use Carbon\CarbonInterface;

/** Einheitliche Anzeigewerte der Abschnitte (Snapshot = reine Strings). */
trait FormatsSectionValues {
    private function dateTime(?CarbonInterface $value): string {
        return $value?->format('d.m.Y H:i') ?? '—';
    }

    private function date(?CarbonInterface $value): string {
        return $value?->format('d.m.Y') ?? '—';
    }

    private function yesNo(bool $value): string {
        return (string) __($value ? 'procedure-documentation.yes' : 'procedure-documentation.no');
    }

    private function text(mixed $value): string {
        if ($value === null || $value === '') {
            return '—';
        }
        if (is_bool($value)) {
            return $this->yesNo($value);
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        return '—';
    }

    /** @return array{label: string, value: string} */
    private function field(string $labelKey, mixed $value): array {
        return ['label' => (string) __($labelKey), 'value' => $this->text($value)];
    }
}
