<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : config.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Billbee-Plugin (MVP-433/434, Phase 40). REST-API https://app.billbee.io
 * (OpenAPI-Fixture: tests/Fixtures/Plugins/Billbee/openapi.json).
 * Auth: X-Billbee-Api-Key + Basic (Billbee-Nutzer + API-Passwort);
 * Throttle 2 req/s je Key+Nutzer → Request-Intervall 0,5 s.
 * Produktiv kommen die Zugangsdaten pro Organisation aus plugin_settings
 * (verschlüsselt); ENV ist nur Fallback für Tests/Konsole.
 */
return [
    'enabled' => env('BILLBEE_ENABLED', false),

    'base_url' => env('BILLBEE_BASE_URL', 'https://app.billbee.io'),

    // Nur Fallback (Tests/Konsole) — produktiv je Org verschlüsselt in plugin_settings.
    'api_key' => env('BILLBEE_API_KEY'),
    'username' => env('BILLBEE_USERNAME'),
    'api_password' => env('BILLBEE_API_PASSWORD'),

    // 2 req/s (dokumentiert, 429 + Retry-After) → 0,5 s Intervall.
    'request_interval' => (float) env('BILLBEE_REQUEST_INTERVAL', 0.5),

    // Seitengröße der Bestell-/Produktabfragen (Billbee-Maximum: 250).
    'page_size' => (int) env('BILLBEE_PAGE_SIZE', 100),

    // Erstlauf-Fenster (Tage) ohne vorhandenen Aufholpunkt.
    'initial_window_days' => (int) env('BILLBEE_INITIAL_WINDOW_DAYS', 30),

    // Überlappung (Minuten) auf den modifiedAtMin-Aufholpunkt.
    'overlap_minutes' => (int) env('BILLBEE_OVERLAP_MINUTES', 5),
];
