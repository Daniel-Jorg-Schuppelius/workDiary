<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : weather.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Weather data for the construction diary (Feature 062, MVP-131).
 */

return [
    'block' => ['title' => 'Weather'],
    'temp' => 'Temperature',
    'precipitation' => 'Precipitation',
    'wind' => 'Wind gust',
    'source' => 'Source',
    'fetched' => 'Fetched',
    'none' => 'No weather data attached.',
    'providers' => [
        // Weather service display names (proper nouns; DWD requires CC-BY attribution).
        'open-meteo' => 'Open-Meteo',
        'dwd' => 'Deutscher Wetterdienst (DWD)',
    ],
    'attach' => [
        'button' => 'Fetch weather',
        'success' => 'Weather for the day attached.',
        'unavailable' => 'No weather data available (no location/coordinates or service unreachable) — can be added later.',
    ],
    // Warnschwellen der Vorhersage (Feature 062, MVP-716).
    'warning' => [
        'threshold' => [
            'rain' => 'Heavy rain',
            'gust' => 'Storm gusts',
            'frost' => 'Frost',
            'heat' => 'Heat',
        ],
    ],
];
