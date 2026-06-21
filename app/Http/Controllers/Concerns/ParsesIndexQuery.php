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

use Illuminate\Http\Request;

/**
 * Einheitliches Einlesen der Standard-Listen-Parameter (Status-Filter,
 * Freitextsuche `q`, Sortierspalte gegen eine Whitelist, Richtung asc/desc).
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
    ): array {
        $sort = $request->string('sort')->toString();

        return [
            'status' => $request->string('status')->toString() ?: $defaultStatus,
            'search' => $request->string('q')->toString(),
            'sort' => in_array($sort, $allowedSorts, true) ? $sort : $defaultSort,
            'dir' => $request->string('dir')->toString() === 'desc' ? 'desc' : 'asc',
        ];
    }
}
