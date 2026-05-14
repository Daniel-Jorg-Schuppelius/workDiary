<?php

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
     * @return array{0:string,1:string}  Effektiv angewandter `[key, dir]`-Eintrag.
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
        $rawSort = (string) $request->query($sortParam, '');
        $rawDir = strtolower((string) $request->query($dirParam, $defaultDir));
        $dir = in_array($rawDir, ['asc', 'desc'], true) ? $rawDir : $defaultDir;

        if ($rawSort !== '' && array_key_exists($rawSort, $allowed)) {
            $key = $rawSort;
        } else {
            $key = $defaultKey;
            $dir = $defaultDir;
        }

        $column = $allowed[$key] ?? $allowed[$defaultKey];
        $query->orderBy($column, $dir);

        return [$key, $dir];
    }
}
