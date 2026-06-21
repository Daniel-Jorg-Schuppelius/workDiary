<?php
/*
 * Created on   : Sun Jun 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Searchable.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Einheitliche Freitext-Suche (LIKE) über eine pro Modell definierte
 * Spaltenliste. Modelle überschreiben searchableColumns().
 */
trait Searchable {
    /**
     * Durchsuchte Spalten. Standard nur „name"; Modelle überschreiben dies.
     *
     * @return list<string>
     */
    protected function searchableColumns(): array {
        return ['name'];
    }

    /**
     * Freitextsuche über searchableColumns(); leerer Term lässt die Query
     * unverändert.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        $like = '%' . $term . '%';

        return $query->where(function (Builder $q) use ($like): void {
            foreach ($this->searchableColumns() as $i => $column) {
                $i === 0
                    ? $q->where($column, 'like', $like)
                    : $q->orWhere($column, 'like', $like);
            }
        });
    }
}
