<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GeofenceMatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Support\Geo;

use App\Models\Location\CustomerGeofence;
use CommonToolkit\Helper\Geo\GeoHelper;

/**
 * Ordnet eine Koordinate dem nächstgelegenen aktiven Geofence zu, dessen Radius
 * den Punkt einschließt. Reine Geometrie, keine Persistenz.
 */
class GeofenceMatcher {
    /**
     * Liefert den nächsten Geofence, in dessen Radius (lat,lng) liegt, oder null.
     *
     * @param iterable<CustomerGeofence> $geofences
     */
    public function match(float $lat, float $lng, iterable $geofences): ?CustomerGeofence {
        $best = null;
        $bestDistance = INF;

        foreach ($geofences as $geofence) {
            $distance = GeoHelper::haversineMeters(
                $lat,
                $lng,
                (float) $geofence->center_lat,
                (float) $geofence->center_lng,
            );

            if ($distance <= $geofence->radius_m && $distance < $bestDistance) {
                $best = $geofence;
                $bestDistance = $distance;
            }
        }

        return $best;
    }
}
