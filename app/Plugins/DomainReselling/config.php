<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : config.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * DomainReselling-Plugin (Feature 083). Angebunden wird je Organisation mit
 * Login + verschlüsseltem Passwort (kein installationsweiter App-Key). Der
 * Endpunkt ist FEST allowlistet — es gibt kein frei eingebbares URL-Feld,
 * damit Zugangsdaten nie an einen fremden Host gesendet werden.
 */

return [
    'enabled' => env('DOMAINRESELLING_ENABLED', false),

    // Guzzle-Timeout je Provider-Request (Sekunden).
    'timeout' => (int) env('DOMAINRESELLING_TIMEOUT', 20),

    // Feste Endpoint-Allowlist je Umgebung (OT&E/Produktiv).
    'endpoints' => [
        'ote' => env('DOMAINRESELLING_ENDPOINT_OTE', 'https://api-ote.domainreselling.de'),
        'production' => env('DOMAINRESELLING_ENDPOINT_PRODUCTION', 'https://api.domainreselling.de'),
    ],
    'call_path' => '/api/call.cgi',

    // Org-Laufbudget der Bulk-Verfügbarkeitsprüfung (Strafpunktschutz):
    // maximale CheckDomain(s)-Aufrufe je Organisation und Stunde.
    'check_budget_per_hour' => (int) env('DOMAINRESELLING_CHECK_BUDGET', 300),

    // Sekunden, in denen ein Verfügbarkeitsergebnis gecacht/debounced wird.
    'check_cache_ttl' => (int) env('DOMAINRESELLING_CHECK_CACHE_TTL', 300),

    // Seiten-/Batchgröße der paginierten Listenabfragen.
    'list_page_size' => (int) env('DOMAINRESELLING_LIST_PAGE_SIZE', 100),

    // Datenalter (Stunden), ab dem eine Projektion als „veraltet" gilt.
    'stale_after_hours' => (int) env('DOMAINRESELLING_STALE_AFTER_HOURS', 24),
];
