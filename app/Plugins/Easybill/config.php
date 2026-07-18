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
 * easybill-Plugin (MVP-431, Phase 40). REST-API https://api.easybill.de/rest/v1
 * (Swagger-Fixture: tests/Fixtures/Plugins/Easybill/openapi.json).
 * Produktiv kommt der API-Key pro Organisation aus plugin_settings
 * (verschlüsselt); ENV ist nur Fallback für Tests/Konsole. Preise der
 * easybill-Positionen sind CENTS (150 = 1,50 €) — Umrechnung im Target.
 */
return [
    'enabled' => env('EASYBILL_ENABLED', false),

    'base_url' => env('EASYBILL_BASE_URL', 'https://api.easybill.de/rest/v1'),

    // Nur Fallback (Tests/Konsole) — produktiv je Org verschlüsselt in plugin_settings.
    'api_key' => env('EASYBILL_API_KEY'),

    // Standard-USt-Satz der Positionen (netto), analog sevDesk-Defaults.
    'default_vat_rate' => (float) env('EASYBILL_DEFAULT_VAT_RATE', 19.0),

    // Tarifabhängiges Request-Limit: PLUS 10/min, BUSINESS 60/min (429 sonst).
    'rate_limit_per_minute' => (int) env('EASYBILL_RATE_LIMIT_PER_MINUTE', 10),

    // Seitengröße paginierter Reads: easybill erlaubt limit bis 1000.
    'page_size' => (int) env('EASYBILL_PAGE_SIZE', 100),

    // Reconciliation-Fenster in Tagen: GET /documents kennt keinen
    // external_id-Filter — der Scan läuft über document_date der jüngsten
    // Tage und vergleicht das external_id-Feld (Quellmarker).
    'reconcile_scan_days' => (int) env('EASYBILL_RECONCILE_SCAN_DAYS', 7),
];
