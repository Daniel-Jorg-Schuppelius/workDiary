<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GeofenceMatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Location;

use App\Models\Location\CustomerGeofence;

/**
 * Ordnet eine Koordinate dem nächstgelegenen aktiven Geofence zu, dessen Radius
 * den Punkt einschließt. Reine Geometrie, keine Persistenz.
 */
class GeofenceMatcher {
    private const EARTH_RADIUS_M = 6_371_000.0;

    /**
     * Liefert den nächsten Geofence, in dessen Radius (lat,lng) liegt, oder null.
     *
     * @param iterable<CustomerGeofence> $geofences
     */
    public function match(float $lat, float $lng, iterable $geofences): ?CustomerGeofence {
        $best = null;
        $bestDistance = INF;

        foreach ($geofences as $geofence) {
            $distance = self::distanceMeters(
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

    /**
     * Haversine-Distanz in Metern zwischen zwei WGS84-Koordinaten.
     */
    public static function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_M * 2 * asin(min(1.0, sqrt($a)));
    }
}
