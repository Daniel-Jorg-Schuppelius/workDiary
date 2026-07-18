<?php
/*
 * Created on   : Sun Jun 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ParsesIndexQuery.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Concerns;

use App\Support\SortableQuery;
use Illuminate\Http\Request;

/**
 * Einheitliches Einlesen der Standard-Listen-Parameter (Status-Filter,
 * Freitextsuche `q`, Sortierspalte gegen eine Whitelist, Richtung asc/desc).
 *
 * Sort/Dir delegiert an {@see SortableQuery::resolve} (C21, eine
 * Whitelist-Semantik). Delta zur früheren Eigenlogik: bei fehlendem oder
 * ungültigem `sort`-Key wird auch `dir` auf den Default zurückgesetzt
 * (vorher blieb der Query-`dir` erhalten); `dir` wird case-insensitiv gelesen.
 */
trait ParsesIndexQuery {
    /**
     * @param  list<string>  $allowedSorts  erlaubte Sortierspalten (Whitelist)
     * @return array{status: string, search: string, sort: string, dir: 'asc'|'desc'}
     */
    protected function parseIndexQuery(
        Request $request,
        array $allowedSorts,
        string $defaultSort,
        string $defaultStatus = 'active',
        string $defaultDir = 'asc',
    ): array {
        [$sort, $dir] = SortableQuery::resolve($request, $allowedSorts, $defaultSort, $defaultDir);

        return [
            'status' => $request->string('status')->toString() ?: $defaultStatus,
            'search' => $request->string('q')->toString(),
            'sort' => $sort,
            'dir' => $dir,
        ];
    }
}
