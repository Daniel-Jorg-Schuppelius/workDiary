<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CompositeField.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Integration\Match;

use Illuminate\Database\Eloquent\Builder;

/**
 * Treffer, wenn ALLE angegebenen Felder (normalisiert) übereinstimmen und keines
 * leer ist — z. B. Firma + PLZ. Default-Confidence „likely".
 */
class CompositeField extends MatchStrategy {
    /** @param list<string> $fieldNames */
    public function __construct(
        public readonly array $fieldNames,
        string $confidence = MatchStrategy::LIKELY,
        ?string $reason = null,
    ) {
        parent::__construct($confidence, $reason ?? implode('_', $fieldNames));
    }

    public function query(Builder $base, array $fields): ?Builder {
        foreach ($this->fieldNames as $field) {
            $value = Normalize::id($fields[$field] ?? null);
            if ($value === '') {
                return null; // unvollständig → nicht anwendbar
            }
            $column = (string) preg_replace('/[^a-z0-9_]/', '', mb_strtolower($field));
            // $column ist auf [a-z0-9_] beschränkt (injection-sicher); der Wert läuft als Bindung —
            // PHPStan kann die literal-string-Eigenschaft nach der Sanitisierung nicht beweisen.
            // @phpstan-ignore argument.type
            $base = $base->whereRaw("replace(lower(trim($column)), ' ', '') = ?", [$value]);
        }

        return $base;
    }

    public function matches(array $a, array $b): bool {
        foreach ($this->fieldNames as $field) {
            $va = Normalize::id($a[$field] ?? null);
            $vb = Normalize::id($b[$field] ?? null);
            if ($va === '' || $va !== $vb) {
                return false;
            }
        }

        return true;
    }

    public function fields(): array {
        return $this->fieldNames;
    }
}
