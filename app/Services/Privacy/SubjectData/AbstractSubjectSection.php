<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AbstractSubjectSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy\SubjectData;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\{Builder, Model};
use InvalidArgumentException;

/** Gemeinsame Wert-/Aggregat-Formatierung aller Auskunfts-Abschnitte. */
abstract class AbstractSubjectSection implements SubjectDataSection {
    /** Erwarteten Modelltyp erzwingen (Sections sind kind-spezifisch verdrahtet). */
    protected function expect(Model $subject, string $class): void {
        if (! $subject instanceof $class) {
            throw new InvalidArgumentException(static::class . ' erwartet ' . $class . ', erhielt ' . $subject::class . '.');
        }
    }

    /** @return array{label: string, value: string|null} */
    protected function field(string $label, mixed $value): array {
        return ['label' => $label, 'value' => $this->str($value)];
    }

    protected function str(mixed $value): ?string {
        if ($value === null) {
            return null;
        }
        if ($value instanceof CarbonInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_bool($value)) {
            return $value ? __('Ja') : __('Nein');
        }
        if (is_scalar($value) || $value instanceof \Stringable) {
            $s = trim((string) $value);

            return $s === '' ? null : $s;
        }

        return null;
    }

    protected function date(mixed $value): ?string {
        return $value instanceof CarbonInterface ? $value->format('Y-m-d') : null;
    }

    /**
     * Aggregierte Familienzeile: Anzahl + Zeitraum (min/max der Datumsspalte)
     * in EINEM Query — keine Rohzeilen.
     *
     * @param  Builder<covariant Model>  $query
     * @param  literal-string  $dateColumn
     * @param  array<string, int|string>  $details
     * @return array{table: string, label: string, count: int, from: string|null, to: string|null, details?: array<string, int|string>}
     */
    protected function family(string $table, string $label, Builder $query, string $dateColumn, array $details = []): array {
        /** @var object{cnt: int|string|null, min_d: string|null, max_d: string|null}|null $agg */
        $agg = $query->toBase()
            ->reorder() // Relations-Sortierung entfernen — Aggregat + ORDER BY bricht unter ONLY_FULL_GROUP_BY
            ->selectRaw('COUNT(*) as cnt, MIN(' . $dateColumn . ') as min_d, MAX(' . $dateColumn . ') as max_d')
            ->first();

        $row = [
            'table' => $table,
            'label' => $label,
            'count' => (int) ($agg->cnt ?? 0),
            'from' => $this->dateString($agg->min_d ?? null),
            'to' => $this->dateString($agg->max_d ?? null),
        ];
        if ($details !== []) {
            $row['details'] = $details;
        }

        return $row;
    }

    private function dateString(mixed $value): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        return substr((string) $value, 0, 10);
    }
}
