<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TableResult.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Routing;

/**
 * Ergebnis von OSRM `/table`: Distanz- und Dauer-Matrix in der Reihenfolge
 * der angefragten Koordinaten (MVP-1171).
 */
final class TableResult {
    /**
     * @param  array<int, array<int, float>>  $distances  Meter
     * @param  array<int, array<int, float>>  $durations  Sekunden
     */
    public function __construct(
        public readonly array $distances,
        public readonly array $durations,
    ) {}
}
