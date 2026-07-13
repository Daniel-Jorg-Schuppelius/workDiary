<?php

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
];
