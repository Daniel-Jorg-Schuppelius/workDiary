<?php
/*
 * Created on   : Sat Jul 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SyncsScopedRelations.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Isms\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Org-gescopter Pivot-Sync für ISMS-Verknüpfungen (Vollaudit 2026-07, N36) —
 * bündelt Normalisierung roher Request-Werte, Auflösung über die
 * org-gescopte Query des Zielmodells und den eigentlichen sync().
 * Fremde Organisationen können dadurch nicht verknüpft werden — die
 * Pivot-Tabellen tragen bewusst keine eigene organization_id
 * (siehe jeweilige Migration).
 */
trait SyncsScopedRelations {
    /**
     * @param  BelongsToMany<covariant Model, covariant Model>  $relation
     * @param  class-string<Model>  $related
     */
    protected function syncScopedIds(BelongsToMany $relation, string $related, mixed $rawIds): void {
        $requested = array_values(array_filter(
            (array) $rawIds,
            static fn(mixed $id): bool => is_int($id) || is_string($id),
        ));

        $ids = $related::query()
            ->whereIn('id', array_map(intval(...), $requested))
            ->pluck('id')
            ->all();

        $relation->sync($ids);
    }
}
