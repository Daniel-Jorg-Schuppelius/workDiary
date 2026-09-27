<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InspectionTourPlanner.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\AssetCompliance\Contracts;

use App\Models\Diary\Tour;
use App\Models\Platform\{Organization, User};
use Carbon\CarbonInterface;

/** Tourenplanung für Prüfertouren (MVP-918), gebunden vom Planungsmodul. */
interface InspectionTourPlanner {
    public function available(Organization $organization): bool;

    /**
     * Tour-Entwurf mit den Aufträgen als Stopps, Reihenfolge optimiert.
     *
     * @param  list<int>  $orderIds
     */
    public function planTour(User $driver, CarbonInterface $date, array $orderIds): Tour;
}
