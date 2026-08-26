<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WeatherProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Weather\Contracts;

use Carbon\CarbonInterface;

/**
 * Providerneutraler Tages-Wetterabruf (Feature 062, MVP-131). Referenz-Provider
 * ist Open-Meteo; DWD-Open-Data u. a. sind austauschbar über dieselbe
 * Schnittstelle. Ausfälle werden als `null` signalisiert (nie Exception nach
 * außen) — ein fehlender Wert darf keinen Tagesbericht blockieren.
 */
interface WeatherProvider {
    /** Stabiler Schlüssel des Providers (z. B. `open-meteo`). */
    public function key(): string;

    /**
     * Tageswerte für Koordinate und Datum, oder `null` bei Ausfall.
     *
     * @return array{temp_min: float|null, temp_max: float|null, precipitation_mm: float|null, wind_gust_kmh: float|null, weather_code: int|null, raw: array<string, mixed>}|null
     */
    public function daily(float $lat, float $lng, CarbonInterface $date): ?array;

    /**
     * Tagesvorhersage ab heute für bis zu 7 Tage (MVP-716), oder `null`, wenn
     * der Provider keine Vorhersage liefert (DWD-KL sind Beobachtungen) oder
     * ausfällt. Schlüssel = Datum (Y-m-d). Vorhersagen sind KEINE Snapshots und
     * werden nie als solche gespeichert.
     *
     * @return array<string, array{date: string, temp_min: float|null, temp_max: float|null, precipitation_mm: float|null, wind_max_kmh: float|null, wind_gust_kmh: float|null, weather_code: int|null}>|null
     */
    public function forecast(float $lat, float $lng, int $days): ?array;
}
