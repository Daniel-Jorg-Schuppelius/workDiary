<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FulltextMatchCompiler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Search\Engine;

use App\Services\Search\Query\{ParsedSearchQuery, SearchAlternative};
use App\Services\Search\SearchTextNormalizer;
use Illuminate\Database\Eloquent\Builder;

/**
 * MySQL/MariaDB-Volltext im Boolean Mode. Jede Gruppe wird `+(…)`, Synonym-
 * und Tippfehler-Varianten tragen `<` (schwächere Relevanz), Ausschlüsse `-`.
 * Die Wörter bestehen nach der Normalisierung nur aus [a-z0-9] — der Ausdruck
 * enthält keine Nutzer-Sonderzeichen und geht zusätzlich als Bindung.
 */
final class FulltextMatchCompiler implements SearchMatchCompiler {
    public function expression(ParsedSearchQuery $parsed): string {
        $parts = [];
        foreach ($parsed->groups as $group) {
            $terms = array_map(
                static fn(SearchAlternative $a): string => ($a->origin !== SearchAlternative::ORIGIN_INPUT && $a->kind !== SearchAlternative::PHRASE ? '<' : '') . self::term($a),
                $group,
            );
            $parts[] = '+(' . implode(' ', $terms) . ')';
        }
        foreach ($parsed->excluded as $alternative) {
            $parts[] = '-' . self::term($alternative);
        }

        return implode(' ', $parts);
    }

    public function apply(Builder $query, ParsedSearchQuery $parsed): void {
        if ($parsed->hasPositive()) {
            $query->whereRaw('MATCH(search_documents.search_text) AGAINST (? IN BOOLEAN MODE)', [$this->expression($parsed)]);

            return;
        }

        if ($parsed->excluded !== []) {
            $terms = implode(' ', array_map(static fn(SearchAlternative $a): string => self::term($a), $parsed->excluded));
            $query->whereRaw('NOT MATCH(search_documents.search_text) AGAINST (? IN BOOLEAN MODE)', [$terms]);
        }
    }

    public function orderByRelevance(Builder $query, ParsedSearchQuery $parsed): bool {
        if (! $parsed->hasPositive()) {
            return false;
        }

        $query->select('search_documents.*')
            ->selectRaw('MATCH(search_documents.search_text) AGAINST (? IN BOOLEAN MODE) AS relevance', [$this->expression($parsed)])
            ->orderByDesc('relevance');

        return true;
    }

    private static function term(SearchAlternative $alternative): string {
        $words = array_map(static fn(string $w): string => SearchTextNormalizer::TOKEN_PREFIX . $w, $alternative->words);

        return match ($alternative->kind) {
            SearchAlternative::PHRASE => '"' . implode(' ', $words) . '"',
            SearchAlternative::PREFIX => $words[0] . '*',
            default => $words[0],
        };
    }
}
