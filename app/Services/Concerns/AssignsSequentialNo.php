<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssignsSequentialNo.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Concerns;

use Illuminate\Database\Eloquent\{Model, SoftDeletes};

/**
 * Laufende Nummern je Scope, Vergabe INNERHALB der Transaktion (Vollscan
 * 2026-08-23, B18): aus dem ISMS-Namespace gehoben — SafetyEventService trug
 * eine wörtliche Kopie, `DatevBookingService::nextBatchNo` dieselbe Logik
 * OHNE `lockForUpdate` (1062 bei parallelen Stapeln, Unique org+batch_no).
 */
trait AssignsSequentialNo {
    /**
     * Nächste laufende Nummer je Scope (withTrashed bei SoftDeletes,
     * lockForUpdate, Start bei 1). Nur in einer Transaktion aufrufen —
     * sonst sperrt lockForUpdate nichts.
     *
     * @param  class-string<Model>  $model
     */
    private function nextNo(string $model, string $column, string $scopeColumn, int $scopeId): int {
        $query = $model::query();
        if (isset(class_uses_recursive($model)[SoftDeletes::class])) {
            /** @phpstan-ignore method.notFound */
            $query = $query->withTrashed();
        }

        $max = $query
            ->where($scopeColumn, $scopeId)
            ->lockForUpdate()
            ->max($column);

        return ((int) $max) + 1;
    }
}
