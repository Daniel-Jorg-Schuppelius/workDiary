<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenMeteoProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Weather;

use App\Services\Weather\Contracts\WeatherProvider;
use Carbon\CarbonInterface;
use GuzzleHttp\ClientInterface;
use Throwable;

/**
 * Open-Meteo-Wetterprovider (Feature 062, MVP-131): frei, ohne Schlüssel.
 * Nutzt die Forecast-API mit `start_date`/`end_date` für einen Tag (deckt
 * heutige und jüngst vergangene Arbeitstage ab). Der HTTP-Client ist
 * injizierbar (Guzzle) — im Test über `MockHandler` ersetzt.
 */
class OpenMeteoProvider implements WeatherProvider {
    private const ENDPOINT = 'https://api.open-meteo.com/v1/forecast';

    public function __construct(private readonly ClientInterface $http) {}

    public function key(): string {
        return 'open-meteo';
    }

    public function daily(float $lat, float $lng, CarbonInterface $date): ?array {
        try {
            $response = $this->http->request('GET', self::ENDPOINT, [
                'query' => [
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'start_date' => $date->toDateString(),
                    'end_date' => $date->toDateString(),
                    'daily' => 'temperature_2m_max,temperature_2m_min,precipitation_sum,wind_gusts_10m_max,weather_code',
                    'timezone' => 'auto',
                ],
                'timeout' => 8,
            ]);
            /** @var array<string, mixed> $json */
            $json = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        $daily = $json['daily'] ?? null;
        if (! is_array($daily) || ! isset($daily['time'][0])) {
            return null;
        }

        return [
            'temp_min' => $this->firstFloat($daily['temperature_2m_min'] ?? null),
            'temp_max' => $this->firstFloat($daily['temperature_2m_max'] ?? null),
            'precipitation_mm' => $this->firstFloat($daily['precipitation_sum'] ?? null),
            'wind_gust_kmh' => $this->firstFloat($daily['wind_gusts_10m_max'] ?? null),
            'weather_code' => is_array($daily['weather_code'] ?? null) && isset($daily['weather_code'][0]) ? (int) $daily['weather_code'][0] : null,
            'raw' => $json,
        ];
    }

    private function firstFloat(mixed $values): ?float {
        return is_array($values) && isset($values[0]) && is_numeric($values[0]) ? (float) $values[0] : null;
    }
}
