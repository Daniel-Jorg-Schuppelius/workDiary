<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OsrmTrackMatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Routing;

use App\Services\Location\Contracts\TrackDistanceSource;

/**
 * Kartenabgleich einer GPS-Spur über OSRM `/match` (MVP-1185): liefert die
 * Straßenstrecke der Fahrt; ohne OSRM null, dann zählt die Spurlänge.
 */
final class OsrmTrackMatcher implements TrackDistanceSource {
    /** OSRM begrenzt `/match` auf 100 Koordinaten je Anfrage. */
    public const MAX_POINTS = 100;

    public function __construct(private readonly OsrmRouter $router) {}

    public function distanceKm(array $points): ?float {
        if (count($points) < 2) {
            return null;
        }
        $sample = $this->thin($points, self::MAX_POINTS);
        try {
            $meters = $this->router->match(array_map(static fn(array $p): array => [(float) $p['lng'], (float) $p['lat']], $sample));
        } catch (RoutingException) {
            return null;
        }

        return $meters !== null ? round($meters / 1000, 2) : null;
    }

    /**
     * Gleichmäßig ausdünnen, erster und letzter Punkt bleiben.
     *
     * @param  list<array{lat: float, lng: float}>  $points
     * @return list<array{lat: float, lng: float}>
     */
    private function thin(array $points, int $max): array {
        $count = count($points);
        if ($count <= $max) {
            return $points;
        }
        $result = [];
        for ($i = 0; $i < $max; $i++) {
            $result[] = $points[(int) round($i * ($count - 1) / ($max - 1))];
        }

        return $result;
    }
}
