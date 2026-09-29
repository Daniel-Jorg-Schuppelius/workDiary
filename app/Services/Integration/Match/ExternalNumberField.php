<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalNumberField.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Integration\Match;

use Illuminate\Database\Eloquent\Builder;

/**
 * Treffer bei gleicher Nummer im Fremdsystem (MVP-1028): die Nummer steht an
 * den Referenzen des Datensatzes (`external_references.external_number`),
 * nicht in einer Anbieterspalte. Das Modell braucht `externalReferences()` und
 * ein Attribut {@see $field} mit den Nummern (kommagetrennt bei mehreren).
 */
class ExternalNumberField extends MatchStrategy {
    public function __construct(
        public readonly string $field = 'accounting_number',
        string $confidence = MatchStrategy::EXACT,
        ?string $reason = null,
    ) {
        parent::__construct($confidence, $reason ?? $field);
    }

    public function query(Builder $base, array $fields): ?Builder {
        $value = Normalize::id($fields[$this->field] ?? null);
        if ($value === '') {
            return null;
        }

        return $base->whereHas('externalReferences', static fn (Builder $q) => $q->whereRaw("replace(lower(trim(external_number)), ' ', '') = ?", [$value]));
    }

    public function matches(array $a, array $b): bool {
        $numbers = static fn (mixed $value): array => array_filter(array_map(
            static fn (string $number): string => Normalize::id($number),
            explode(',', (string) $value),
        ));

        return array_intersect($numbers($a[$this->field] ?? null), $numbers($b[$this->field] ?? null)) !== [];
    }

    public function fields(): array {
        return [$this->field];
    }
}
