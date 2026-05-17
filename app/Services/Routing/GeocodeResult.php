<?php

/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GeocodeResult.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Routing;

/**
 * Immutable value object returned by {@see NominatimGeocoder}.
 */
final class GeocodeResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly float $lat,
        public readonly float $lng,
        public readonly ?string $displayName,
        public readonly array $raw = [],
        public readonly string $provider = 'nominatim',
        public readonly bool $fromCache = false,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'lat' => $this->lat,
            'lng' => $this->lng,
            'display_name' => $this->displayName,
            'provider' => $this->provider,
            'from_cache' => $this->fromCache,
        ];
    }
}
