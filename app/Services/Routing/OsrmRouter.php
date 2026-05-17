<?php

/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OsrmRouter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Routing;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Thin OSRM client. Coordinates are passed as [lng, lat] pairs to match
 * OSRM's URL convention. Returns {@see RouteResult} for the first route
 * in the response.
 */
class OsrmRouter
{
    public function __construct(
        /** @var array<string, mixed> */
        private array $config,
    ) {}

    /**
     * @param  array<int, array{0: float, 1: float}>  $coordinates  ordered [lng, lat] pairs
     */
    public function route(array $coordinates, ?string $profile = null): RouteResult
    {
        if (count($coordinates) < 2) {
            throw new RoutingException('At least two coordinates required.');
        }

        $base = rtrim((string) ($this->config['base_url'] ?? ''), '/');
        $profile = $profile ?? (string) ($this->config['profile'] ?? 'driving');
        $timeout = (int) ($this->config['timeout'] ?? 10);

        $segments = array_map(
            static fn (array $c): string => sprintf('%F,%F', (float) $c[0], (float) $c[1]),
            $coordinates,
        );
        $path = $base.'/route/v1/'.rawurlencode($profile).'/'.implode(';', $segments);

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->get($path, [
                    'overview' => 'full',
                    'geometries' => 'geojson',
                    'steps' => 'false',
                ]);
        } catch (ConnectionException $e) {
            throw new RoutingException('OSRM unreachable: '.$e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            throw new RoutingException('OSRM returned HTTP '.$response->status());
        }

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];
        if (($body['code'] ?? null) !== 'Ok' || empty($body['routes'])) {
            throw new RoutingException('OSRM response invalid: '.($body['code'] ?? 'unknown'));
        }

        /** @var array<string, mixed> $route */
        $route = $body['routes'][0];

        return new RouteResult(
            distanceMeters: (int) round((float) ($route['distance'] ?? 0)),
            durationSeconds: (int) round((float) ($route['duration'] ?? 0)),
            geometry: isset($route['geometry']) && is_array($route['geometry']) ? $route['geometry'] : null,
            legs: isset($route['legs']) && is_array($route['legs']) ? $route['legs'] : [],
        );
    }
}
