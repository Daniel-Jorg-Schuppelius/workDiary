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

use App\Plugins\Support\PluginHttpFactory;
use App\Support\UrlSafety;
use GuzzleHttp\Exception\ConnectException;

/**
 * Thin OSRM client. Coordinates are passed as [lng, lat] pairs to match
 * OSRM's URL convention. Returns {@see RouteResult} for the first route
 * in the response.
 */
class OsrmRouter {
    public function __construct(
        /** @var array<string, mixed> */
        private array $config,
        private readonly PluginHttpFactory $http,
    ) {}

    private function client(string $base, int $timeout): \App\Plugins\Support\PluginApiClient {
        $client = $this->http->coreClient(
            'osrm',
            $base,
            allowPrivateNetwork: UrlSafety::operatorTargetAllowed($base, 'routing.osrm.base_url'),
        );
        $client->setTimeout((float) $timeout);

        return $client;
    }

    /**
     * @param  array<int, array{0: float, 1: float}>  $coordinates  ordered [lng, lat] pairs
     */
    public function route(array $coordinates, ?string $profile = null): RouteResult {
        if (count($coordinates) < 2) {
            throw new RoutingException('At least two coordinates required.');
        }

        $base = rtrim((string) ($this->config['base_url'] ?? ''), '/');
        $profile = $profile ?? (string) ($this->config['profile'] ?? 'driving');
        $timeout = (int) ($this->config['timeout'] ?? 10);

        $segments = array_map(
            static fn(array $c): string => sprintf('%F,%F', (float) $c[0], (float) $c[1]),
            $coordinates,
        );
        $path = $base . '/route/v1/' . rawurlencode($profile) . '/' . implode(';', $segments);

        try {
            $response = $this->client($base, $timeout)->getResponse($path, [
                'overview' => 'full',
                'geometries' => 'geojson',
                'steps' => 'false',
            ]);
        } catch (ConnectException $e) {
            throw new RoutingException('OSRM unreachable: ' . $e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            throw new RoutingException('OSRM returned HTTP ' . $response->status());
        }

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];
        if (($body['code'] ?? null) !== 'Ok' || empty($body['routes'])) {
            throw new RoutingException('OSRM response invalid: ' . ($body['code'] ?? 'unknown'));
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

    /**
     * Fetches the full N×N distance (meters) and duration (seconds) matrices
     * from OSRM's `/table` service. Both mirror the input ordering; the
     * distance matrix is directly consumable by {@see TourOptimizer::optimize()}.
     *
     * @param  array<int, array{0: float, 1: float}>  $coordinates  ordered [lng, lat] pairs
     */
    public function table(array $coordinates, ?string $profile = null): TableResult {
        if (count($coordinates) < 2) {
            throw new RoutingException('At least two coordinates required.');
        }

        $base = rtrim((string) ($this->config['base_url'] ?? ''), '/');
        $profile = $profile ?? (string) ($this->config['profile'] ?? 'driving');
        $timeout = (int) ($this->config['timeout'] ?? 10);

        $segments = array_map(
            static fn(array $c): string => sprintf('%F,%F', (float) $c[0], (float) $c[1]),
            $coordinates,
        );
        $path = $base . '/table/v1/' . rawurlencode($profile) . '/' . implode(';', $segments);

        try {
            $response = $this->client($base, $timeout)->getResponse($path, ['annotations' => 'duration,distance']);
        } catch (ConnectException $e) {
            throw new RoutingException('OSRM unreachable: ' . $e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            throw new RoutingException('OSRM returned HTTP ' . $response->status());
        }

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];
        if (($body['code'] ?? null) !== 'Ok' || ! isset($body['distances'], $body['durations']) || ! is_array($body['distances']) || ! is_array($body['durations'])) {
            throw new RoutingException('OSRM table response invalid: ' . ($body['code'] ?? 'unknown'));
        }

        $expected = count($coordinates);

        return new TableResult(
            $this->squareMatrix($body['distances'], $expected, 'distance'),
            $this->squareMatrix($body['durations'], $expected, 'duration'),
        );
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array<int, array<int, float>>
     */
    private function squareMatrix(array $rows, int $expected, string $kind): array {
        $matrix = [];
        foreach ($rows as $row) {
            if (! is_array($row) || count($row) !== $expected) {
                throw new RoutingException("OSRM table returned a malformed {$kind} row.");
            }
            // null = kein Weg (OSRM) → als unerreichbar bewerten.
            $matrix[] = array_map(static fn($v): float => $v === null ? INF : (float) $v, array_values($row));
        }
        if (count($matrix) !== $expected) {
            throw new RoutingException("OSRM table returned an incomplete {$kind} matrix.");
        }

        return $matrix;
    }

    /**
     * Kartenabgleich einer GPS-Spur (`/match`, MVP-1185): Summe der
     * Straßenstrecke aller Teilabgleiche in Metern; null, wenn OSRM nichts
     * zuordnen kann.
     *
     * @param  array<int, array{0: float, 1: float}>  $coordinates  ordered [lng, lat] pairs
     */
    public function match(array $coordinates, ?string $profile = null): ?float {
        if (count($coordinates) < 2) {
            throw new RoutingException('At least two coordinates required.');
        }

        $base = rtrim((string) ($this->config['base_url'] ?? ''), '/');
        $profile = $profile ?? (string) ($this->config['profile'] ?? 'driving');
        $timeout = (int) ($this->config['timeout'] ?? 10);

        $segments = array_map(
            static fn(array $c): string => sprintf('%F,%F', (float) $c[0], (float) $c[1]),
            $coordinates,
        );
        $path = $base . '/match/v1/' . rawurlencode($profile) . '/' . implode(';', $segments);

        try {
            $response = $this->client($base, $timeout)->getResponse($path, [
                'overview' => 'false',
                'steps' => 'false',
                'gaps' => 'ignore',
                'tidy' => 'true',
            ]);
        } catch (ConnectException $e) {
            throw new RoutingException('OSRM unreachable: ' . $e->getMessage(), 0, $e);
        }
        if (! $response->successful()) {
            throw new RoutingException('OSRM returned HTTP ' . $response->status());
        }

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];
        if (($body['code'] ?? null) !== 'Ok' || ! isset($body['matchings']) || ! is_array($body['matchings'])) {
            return null;
        }
        $meters = 0.0;
        foreach ($body['matchings'] as $matching) {
            $meters += (float) ($matching['distance'] ?? 0);
        }

        return $meters > 0 ? $meters : null;
    }
}
