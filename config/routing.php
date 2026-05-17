<?php

/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routing.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Nominatim (geocoding)
    |--------------------------------------------------------------------------
    | Self-hosted instance is recommended. Per Nominatim's usage policy the
    | User-Agent header must identify the application and contain a contact
    | email — populate both even when using a private endpoint.
    */
    'nominatim' => [
        'base_url' => env('ROUTING_NOMINATIM_URL', 'http://localhost/nominatim'),
        'user_agent' => env('ROUTING_NOMINATIM_UA', 'workDiary/'.env('APP_URL', 'http://localhost')),
        'email' => env('ROUTING_NOMINATIM_EMAIL', 'admin@example.com'),
        'rate_limit_per_sec' => (int) env('ROUTING_NOMINATIM_RATE', 1),
        'timeout' => (int) env('ROUTING_NOMINATIM_TIMEOUT', 8),
    ],

    /*
    |--------------------------------------------------------------------------
    | OSRM (routing)
    |--------------------------------------------------------------------------
    */
    'osrm' => [
        'base_url' => env('ROUTING_OSRM_URL', 'http://localhost:5000'),
        'profile' => env('ROUTING_OSRM_PROFILE', 'driving'),
        'timeout' => (int) env('ROUTING_OSRM_TIMEOUT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Leaflet tile layer
    |--------------------------------------------------------------------------
    */
    'tiles' => [
        'url' => env('ROUTING_TILES_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'attribution' => env(
            'ROUTING_TILES_ATTRIBUTION',
            '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        ),
        'max_zoom' => (int) env('ROUTING_TILES_MAX_ZOOM', 19),
    ],

    /*
    |--------------------------------------------------------------------------
    | Geocode cache
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'ttl_days' => (int) env('ROUTING_CACHE_TTL_DAYS', 365),
    ],
];
