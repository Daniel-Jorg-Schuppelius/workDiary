<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LikeMatchCompiler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Search\Engine;

use App\Services\Search\Query\{ParsedSearchQuery, SearchAlternative};
use App\Services\Search\SearchTextNormalizer;
use Illuminate\Database\Eloquent\Builder;

/**
 * LIKE auf dem kodierten Suchtext (SQLite, Tests) — über das escapte Makro.
 * Der Leerzeichen-Rahmen des Suchtexts macht Wortanfang und genaues Wort
 * eindeutig: die Nadel ` wexch` findet Wortanfänge, ` wad ` nur das Wort.
 */
final class LikeMatchCompiler implements SearchMatchCompiler {
    public function apply(Builder $query, ParsedSearchQuery $parsed): void {
        foreach ($parsed->groups as $group) {
            $query->where(static function (Builder $inner) use ($group): void {
                foreach ($group as $alternative) {
                    $inner->orWhereLikeEscaped('search_documents.search_text', self::needle($alternative));
                }
            });
        }
        foreach ($parsed->excluded as $alternative) {
            $query->whereNot(static fn(Builder $inner) => $inner->whereLikeEscaped('search_documents.search_text', self::needle($alternative)));
        }
    }

    public function orderByRelevance(Builder $query, ParsedSearchQuery $parsed): bool {
        return false;
    }

    /** Suchnadel im kodierten Text; das Makro umschließt sie mit `%`. */
    public static function needle(SearchAlternative $alternative): string {
        $words = array_map(static fn(string $w): string => SearchTextNormalizer::TOKEN_PREFIX . $w, $alternative->words);

        return match ($alternative->kind) {
            SearchAlternative::PHRASE => ' ' . implode(' ', $words) . ' ',
            SearchAlternative::PREFIX => ' ' . $words[0],
            default => ' ' . $words[0] . ' ',
        };
    }
}
