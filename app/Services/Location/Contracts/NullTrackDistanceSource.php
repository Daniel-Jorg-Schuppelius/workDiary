<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullTrackDistanceSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Location\Contracts;

final class NullTrackDistanceSource implements TrackDistanceSource {
    public function distanceKm(array $points): ?float {
        return null;
    }
}
