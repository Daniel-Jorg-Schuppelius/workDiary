<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SurchargeShare.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Surcharge;

use App\Models\Surcharge\SurchargeRule;

/**
 * Ergebnis-Anteil einer Zuschlagsberechnung: zuschlagsfähige Minuten
 * einer Regel an einem Kalendertag (Feature 005, MVP).
 */
final class SurchargeShare {
    public function __construct(
        public readonly SurchargeRule $rule,
        /** Kalendertag (Y-m-d), dem die Minuten zugerechnet werden. */
        public readonly string $date,
        public readonly int $minutes,
    ) {}
}
