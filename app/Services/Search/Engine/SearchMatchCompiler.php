<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SearchMatchCompiler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Search\Engine;

use App\Models\SearchDocument;
use App\Services\Search\Query\ParsedSearchQuery;
use Illuminate\Database\Eloquent\Builder;

/**
 * Übersetzt eine zerlegte Anfrage in SQL auf `search_documents.search_text`.
 * Alle Engines haben dieselbe Semantik: Wortanfang, genaues Wort, Phrase aus
 * benachbarten Wörtern.
 */
interface SearchMatchCompiler {
    /** @param  Builder<SearchDocument>  $query */
    public function apply(Builder $query, ParsedSearchQuery $parsed): void;

    /**
     * Sortiert nach Relevanz, wenn die Engine sie kennt.
     *
     * @param  Builder<SearchDocument>  $query
     */
    public function orderByRelevance(Builder $query, ParsedSearchQuery $parsed): bool;
}
