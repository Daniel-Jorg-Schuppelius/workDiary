<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RouteResult.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Routing;

/**
 * Immutable value object returned by {@see OsrmRouter}.
 */
final class RouteResult {
    /**
     * @param  array<string, mixed>|null  $geometry  GeoJSON LineString
     * @param  array<int, array<string, mixed>>  $legs
     */
    public function __construct(
        public readonly int $distanceMeters,
        public readonly int $durationSeconds,
        public readonly ?array $geometry,
        public readonly array $legs = [],
    ) {
    }

    public function distanceKm(): float {
        return round($this->distanceMeters / 1000, 2);
    }

    public function durationMinutes(): int {
        return (int) ceil($this->durationSeconds / 60);
    }
}
