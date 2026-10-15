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
    /** Meter je Breitengrad; Längengrade schrumpfen mit dem Kosinus der Breite. */
    private const METERS_PER_DEGREE = 111_320.0;

    /**
     * @param  iterable<int, CustomerGeofence>  $geofences
     * @param  int|null  $accuracyM  Genauigkeit des Punkts: ein Punkt zählt erst als
     *                               drinnen, wenn Abstand plus Genauigkeit im Radius
     *                               liegen (MVP-1186) — Punkte am Rand flattern nicht.
     */
    public function match(float $lat, float $lng, iterable $geofences, ?int $accuracyM = null): ?CustomerGeofence {
        $best = null;
        $bestDistance = INF;
        $slack = max(0, (int) $accuracyM);
        $cosLat = max(0.01, cos(deg2rad($lat)));

        foreach ($geofences as $geofence) {
            $reach = (int) $geofence->radius_m + $slack;
            // Rechteck-Vorfilter vor der Haversine: spart bei vielen Zonen den Großteil der Rechnung.
            if (abs($lat - (float) $geofence->center_lat) * self::METERS_PER_DEGREE > $reach
                || abs($lng - (float) $geofence->center_lng) * self::METERS_PER_DEGREE * $cosLat > $reach) {
                continue;
            }
            $distance = GeoHelper::haversineMeters(
                $lat,
                $lng,
                (float) $geofence->center_lat,
                (float) $geofence->center_lng,
            );

            if ($distance + $slack <= $geofence->radius_m && $distance < $bestDistance) {
                $best = $geofence;
                $bestDistance = $distance;
            }
        }

        return $best;
    }

    /** Liegt der Punkt (mit seiner Genauigkeit) noch im Band einer Zone, die er schon betreten hat? */
    public function stillInside(float $lat, float $lng, CustomerGeofence $geofence, ?int $accuracyM = null): bool {
        $distance = GeoHelper::haversineMeters($lat, $lng, (float) $geofence->center_lat, (float) $geofence->center_lng);

        return $distance - max(0, (int) $accuracyM) <= $geofence->radius_m;
    }
}
