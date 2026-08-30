<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NominatimGeocoder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Routing;

use App\Models\GeocodeCache;
use App\Plugins\Support\PluginHttpFactory;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Cache, Log};

/**
 * Forward geocoding via Nominatim with persistent cache and rate limit.
 *
 * The Nominatim usage policy requires:
 *  - identifying User-Agent (we add config('routing.nominatim.user_agent'))
 *  - max 1 req/s when using the public instance — keep the throttle for
 *    self-hosted too so backfill commands cannot floods the local service.
 */
class NominatimGeocoder {
    public function __construct(
        /** @var array<string, mixed> */
        private array $config,
        private readonly PluginHttpFactory $http,
    ) {}

    public function geocode(string $query): ?GeocodeResult {
        $query = trim($query);
        if ($query === '') {
            return null;
        }

        $hash = GeocodeCache::hashFor($query);

        /** @var GeocodeCache|null $cached */
        $cached = GeocodeCache::query()->where('query_hash', $hash)->first();
        if ($cached instanceof GeocodeCache && ! $cached->isExpired()) {
            return new GeocodeResult(
                lat: (float) $cached->lat,
                lng: (float) $cached->lng,
                displayName: $cached->address_formatted,
                raw: $cached->raw ?? [],
                provider: $cached->provider,
                fromCache: true,
            );
        }

        $this->throttle();

        $result = $this->fetch($query);
        if ($result === null) {
            return null;
        }

        $ttlDays = (int) (config('routing.cache.ttl_days') ?? 365);
        GeocodeCache::query()->updateOrCreate(
            ['query_hash' => $hash],
            [
                'query' => $query,
                'address_formatted' => $result->displayName,
                'lat' => $result->lat,
                'lng' => $result->lng,
                'provider' => $result->provider,
                'raw' => $result->raw,
                'expires_at' => Carbon::now()->addDays($ttlDays),
            ]
        );

        return $result;
    }

    private function fetch(string $query): ?GeocodeResult {
        $base = (string) ($this->config['base_url'] ?? '');
        $userAgent = (string) ($this->config['user_agent'] ?? 'workDiary');
        $email = (string) ($this->config['email'] ?? '');
        $timeout = (int) ($this->config['timeout'] ?? 8);

        $client = $this->http->coreClient(
            'nominatim',
            $base,
            allowPrivateNetwork: $this->mayUsePrivateTarget($base),
        );
        $client->setUserAgent($userAgent);
        $client->setTimeout((float) $timeout);

        try {
            $response = $client->getResponse(rtrim($base, '/') . '/search', array_filter([
                'q' => $query,
                'format' => 'jsonv2',
                'limit' => 1,
                'addressdetails' => 1,
                'email' => $email !== '' ? $email : null,
            ], static fn($v): bool => $v !== null));
        } catch (ConnectException $e) {
            throw new GeocodingException('Nominatim unreachable: ' . $e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            throw new GeocodingException('Nominatim returned HTTP ' . $response->status());
        }

        /** @var list<array<string, mixed>> $body */
        $body = $response->json() ?? [];
        if ($body === []) {
            return null;
        }

        $first = $body[0];
        if (! isset($first['lat'], $first['lon'])) {
            Log::warning('Nominatim response missing lat/lon', ['query' => $query]);

            return null;
        }

        return new GeocodeResult(
            lat: (float) $first['lat'],
            lng: (float) $first['lon'],
            displayName: isset($first['display_name']) ? (string) $first['display_name'] : null,
            raw: $first,
        );
    }

    private function throttle(): void {
        $rate = max(1, (int) ($this->config['rate_limit_per_sec'] ?? 1));
        $key = 'routing:nominatim:last_call';
        $lastCall = (float) (Cache::get($key) ?? 0.0);
        $now = microtime(true);
        $minGap = 1.0 / $rate;
        $delta = $now - $lastCall;
        if ($delta < $minGap) {
            $sleepUs = (int) (($minGap - $delta) * 1_000_000);
            if ($sleepUs > 0) {
                usleep($sleepUs);
            }
        }
        Cache::put($key, microtime(true), 60);
    }

    /**
     * Darf dieses Ziel im privaten Netz liegen?
     *
     * Zwei Fälle, die auseinandergehalten gehören (Sicherheitsscan
     * 2026-08-23, S-10): Der **Betreiber** richtet den Dienst ein — die
     * Vorgabe zeigt ab Werk auf `http://localhost/nominatim`, ein selbst gehosteter Routing-Server
     * ist der Normalfall. Eine **Organisation** kann die Adresse über die
     * Einstellungen aber überschreiben; dann ist sie eine von außen gesetzte
     * URL und braucht das ausdrückliche Opt-in.
     */
    private function mayUsePrivateTarget(string $base): bool {
        $operatorDefault = rtrim((string) config('routing.nominatim.base_url', ''), '/');

        if ($operatorDefault !== '' && rtrim($base, '/') === $operatorDefault) {
            return true;
        }

        return (bool) \App\Support\Setting::get('routing.allow_private_network', false);
    }

}
