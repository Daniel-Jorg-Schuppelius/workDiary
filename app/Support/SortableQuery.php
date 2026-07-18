<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SortableQuery.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;

/**
 * Wendet eine `sort`/`dir`-Querystring-Sortierung auf einen Eloquent-/DB-Builder an.
 *
 * Aufrufer übergeben eine Allowlist `key => column`, einen Default-Schlüssel
 * sowie eine Default-Richtung. Rückgabe ist das effektiv angewandte Tupel
 * `[key, dir]`, mit dem die View die Sort-Icons rendert.
 */
final class SortableQuery {
    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  EloquentBuilder<TModel>|QueryBuilder  $query
     * @param  array<string,string>  $allowed  Mapping `key => sql column`.
     * @return array{0:string,1:string} Effektiv angewandter `[key, dir]`-Eintrag.
     */
    public static function apply(
        EloquentBuilder|QueryBuilder $query,
        Request $request,
        array $allowed,
        string $defaultKey,
        string $defaultDir = 'desc',
        string $sortParam = 'sort',
        string $dirParam = 'dir',
    ): array {
        [$key, $dir] = self::resolve($request, $allowed, $defaultKey, $defaultDir, $sortParam, $dirParam);

        $column = $allowed[$key] ?? $allowed[$defaultKey];
        $query->orderBy($column, $dir);

        return [$key, $dir];
    }

    /**
     * Reine Whitelist-Auflösung ohne Builder (C21, eine Semantik app-weit):
     * bei fehlendem oder ungültigem Sort-Key werden Key UND Richtung auf die
     * Defaults zurückgesetzt.
     *
     * @param  array<int|string,string>  $allowed  Liste erlaubter Keys oder Mapping `key => sql column`.
     * @return array{0:string,1:'asc'|'desc'}
     */
    public static function resolve(
        Request $request,
        array $allowed,
        string $defaultKey,
        string $defaultDir = 'desc',
        string $sortParam = 'sort',
        string $dirParam = 'dir',
    ): array {
        $rawSort = (string) $request->query($sortParam, '');
        $normalizedDefault = strtolower($defaultDir) === 'asc' ? 'asc' : 'desc';
        $rawDir = strtolower((string) $request->query($dirParam, $normalizedDefault));
        $dir = $rawDir === 'asc' ? 'asc' : ($rawDir === 'desc' ? 'desc' : $normalizedDefault);

        $isAllowed = $rawSort !== '' && (array_is_list($allowed)
            ? in_array($rawSort, $allowed, true)
            : array_key_exists($rawSort, $allowed));

        return $isAllowed ? [$rawSort, $dir] : [$defaultKey, $normalizedDefault];
    }
}
