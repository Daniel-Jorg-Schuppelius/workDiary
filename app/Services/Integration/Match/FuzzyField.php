<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FuzzyField.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Integration\Match;

use Illuminate\Database\Eloquent\Builder;

/**
 * Unscharfer Treffer über Namens-/Firmen-Ähnlichkeit. Vergleicht das Kreuzprodukt
 * der angegebenen Felder (z. B. name×name, name×company, company×company) und
 * matcht, wenn die höchste Ähnlichkeit die Schwelle erreicht.
 *
 * Nicht query-fähig (keine DB-seitige Ähnlichkeit) — der {@see EntityMatcher}
 * lädt dafür Kandidaten und vergleicht in-memory.
 */
class FuzzyField extends MatchStrategy {
    /** @param list<string> $fieldNames */
    public function __construct(
        public readonly array $fieldNames,
        public readonly float $threshold = 0.86,
        ?string $reason = null,
    ) {
        parent::__construct(MatchStrategy::FUZZY, $reason ?? 'name');
    }

    public function query(Builder $base, array $fields): ?Builder {
        return null; // Fuzzy wird in-memory ausgewertet
    }

    public function matches(array $a, array $b): bool {
        $best = 0.0;
        foreach ($this->fieldNames as $fa) {
            foreach ($this->fieldNames as $fb) {
                $best = max($best, Normalize::similarity(
                    Normalize::text($a[$fa] ?? null),
                    Normalize::text($b[$fb] ?? null),
                ));
            }
        }

        return $best >= $this->threshold;
    }

    public function fields(): array {
        return $this->fieldNames;
    }
}
