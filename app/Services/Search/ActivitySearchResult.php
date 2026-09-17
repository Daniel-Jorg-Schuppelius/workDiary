<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ActivitySearchResult.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Search;

use App\Services\Search\Query\ParsedSearchQuery;
use Illuminate\Pagination\LengthAwarePaginator;

final class ActivitySearchResult {
    /**
     * @param  LengthAwarePaginator<int, ActivitySearchHit>  $hits
     * @param  list<ActivitySearchAggregate>  $aggregates
     * @param  array<string, int>  $typeCounts  Treffer je Quelle (ohne Quellen-Filter)
     * @param  list<array{id: int, name: string, hits: int}>  $tagFacets  häufigste Schlagwörter der Treffer (MVP-812)
     */
    public function __construct(
        public readonly LengthAwarePaginator $hits,
        public readonly array $aggregates,
        public readonly array $typeCounts,
        public readonly ParsedSearchQuery $parsed,
        public readonly bool $searched,
        public readonly bool $relevance,
        public readonly array $tagFacets = [],
    ) {}
}
