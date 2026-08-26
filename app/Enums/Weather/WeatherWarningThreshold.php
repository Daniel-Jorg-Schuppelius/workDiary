<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WeatherWarningThreshold.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Weather;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Warnschwellen der Wettervorhersage für disponierte Einsätze (Feature 062,
 * MVP-716). Jede Schwelle ist ein Org-Setting `weather.warn_*` mit
 * dokumentiertem Default; Regen/Böen/Hitze warnen ab Überschreitung, Frost ab
 * Unterschreitung der Tages-Tiefsttemperatur.
 */
enum WeatherWarningThreshold: string implements HasLabel {
    use HasOptions;

    case Rain = 'rain';
    case Gust = 'gust';
    case Frost = 'frost';
    case Heat = 'heat';

    public function label(): string {
        return (string) __('weather.warning.threshold.' . $this->value);
    }

    /** Org-Setting-Schlüssel der Schwelle. */
    public function settingKey(): string {
        return match ($this) {
            self::Rain => 'weather.warn_rain_mm',
            self::Gust => 'weather.warn_gust_kmh',
            self::Frost => 'weather.warn_frost_c',
            self::Heat => 'weather.warn_heat_c',
        };
    }

    /**
     * Dokumentierte Defaults: 20 mm/Tag (DWD „ergiebiger Regen" beginnt bei
     * 25–35 mm/6 h; für Außenarbeit ist die Tagessumme das praktikablere Maß),
     * Böen 60 km/h (Bft 7–8, Gerüst-/Kranarbeit), Frost 0 °C, Hitze 30 °C
     * (ab hier greifen ASR-A3.5-Maßnahmen im Freien).
     */
    public function defaultLimit(): float {
        return match ($this) {
            self::Rain => 20.0,
            self::Gust => 60.0,
            self::Frost => 0.0,
            self::Heat => 30.0,
        };
    }

    /** Einheit des Vorhersagewerts (Anzeige). */
    public function unit(): string {
        return match ($this) {
            self::Rain => 'mm',
            self::Gust => 'km/h',
            self::Frost, self::Heat => '°C',
        };
    }

    /**
     * Vorhersagewert der Schwelle aus einer Tageszeile des Providers.
     *
     * @param  array{temp_min: float|null, temp_max: float|null, precipitation_mm: float|null, wind_max_kmh: float|null, wind_gust_kmh: float|null, weather_code: int|null}  $day
     */
    public function valueOf(array $day): ?float {
        return match ($this) {
            self::Rain => $day['precipitation_mm'],
            self::Gust => $day['wind_gust_kmh'] ?? $day['wind_max_kmh'],
            self::Frost => $day['temp_min'],
            self::Heat => $day['temp_max'],
        };
    }

    /** Reißt der Vorhersagewert die Schwelle? */
    public function isExceeded(float $value, float $limit): bool {
        return $this === self::Frost ? $value <= $limit : $value >= $limit;
    }
}
