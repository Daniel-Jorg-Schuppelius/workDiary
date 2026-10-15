<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TrackDistanceSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Location\Contracts;

/**
 * Straßenstrecke einer GPS-Spur (MVP-1185): definiert von der
 * Standorterfassung, gebunden von der Planung (OSRM-Kartenabgleich). Null-Bindung:
 * kein Abgleich — der Aufrufer nimmt die Spurlänge.
 */
interface TrackDistanceSource {
    /**
     * @param  list<array{lat: float, lng: float}>  $points  zeitlich sortiert
     * @return float|null Kilometer, null wenn kein Abgleich möglich
     */
    public function distanceKm(array $points): ?float;
}
