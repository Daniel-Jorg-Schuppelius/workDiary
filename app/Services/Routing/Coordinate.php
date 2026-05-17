<?php

/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Coordinate.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Routing;

/**
 * Lightweight (lat, lng) tuple used by the tour optimizer.
 */
final class Coordinate
{
    public function __construct(
        public readonly float $lat,
        public readonly float $lng,
    ) {}

    /** @return array{0: float, 1: float} OSRM-style [lng, lat] tuple. */
    public function toLngLat(): array
    {
        return [$this->lng, $this->lat];
    }
}
