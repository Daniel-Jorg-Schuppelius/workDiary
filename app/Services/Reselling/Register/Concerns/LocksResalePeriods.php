<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LocksResalePeriods.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register\Concerns;

use App\Models\Reselling\ResalePeriod;

/**
 * Statuswechsel einer Periode nur auf dem gesperrten, frischen Stand
 * (Behebung 2026-11-02, Muster 3 „Prüfung am geladenen Modell, keine Sperre“).
 * Die übergebene Instanz behält Identität und geladene Relationen, trägt
 * danach aber die Spalten der gesperrten Zeile.
 */
trait LocksResalePeriods {
    protected function lockAndRefresh(ResalePeriod $period): ResalePeriod {
        $fresh = ResalePeriod::query()->withoutGlobalScopes()->whereKey($period->getKey())->lockForUpdate()->firstOrFail();
        $period->setRawAttributes($fresh->getAttributes(), true);

        return $period;
    }
}
