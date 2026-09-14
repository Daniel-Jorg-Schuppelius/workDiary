<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ParsedSearchQuery.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Search\Query;

/**
 * Zerlegte Suchanfrage: Gruppen sind UND-verknüpft, innerhalb einer Gruppe
 * genügt eine Variante. Dazu, was die Oberfläche transparent machen muss —
 * ersetzte Tippfehler, mitgesuchte Synonyme, ignorierte Füllwörter.
 */
final class ParsedSearchQuery {
    /**
     * @param  list<list<SearchAlternative>>  $groups
     * @param  list<SearchAlternative>  $excluded
     * @param  array<string, list<string>>  $corrections  Suchwort → ähnliche indizierte Wörter
     * @param  array<string, list<string>>  $synonyms  Suchbegriff → mitgesuchte Begriffe
     * @param  list<string>  $ignored  weggelassene Füllwörter
     */
    public function __construct(
        public readonly array $groups = [],
        public readonly array $excluded = [],
        public readonly array $corrections = [],
        public readonly array $synonyms = [],
        public readonly array $ignored = [],
    ) {}

    public function isEmpty(): bool {
        return $this->groups === [] && $this->excluded === [];
    }

    public function hasPositive(): bool {
        return $this->groups !== [];
    }

    /**
     * Wortfolgen aller positiven Varianten — für die Hervorhebung im Auszug.
     *
     * @return list<list<string>>
     */
    public function highlightWords(): array {
        $words = [];
        foreach ($this->groups as $group) {
            foreach ($group as $alternative) {
                $words[implode(' ', $alternative->words)] = $alternative->words;
            }
        }

        return array_values($words);
    }
}
