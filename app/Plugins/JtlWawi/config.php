<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : config.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * JTL-Wawi-Plugin (Feature 078). ENV ist nur Installations-Fallback —
 * die Verbindung je Organisation liegt in `jtl_connections`. Die
 * Cloud-Endpunkte entsprechen der veröffentlichten OpenAPI 2.0
 * (Stand 2026-07-11, siehe Abweichungsregister in Feature 078).
 */
return [
    'enabled' => env('JTL_WAWI_ENABLED', false),

    // Cloud-Gateway (OAuth2 Client-Credentials); OnPremise nutzt die je Org
    // hinterlegte Basis-URL http(s)://host:port/api/eazybusiness.
    'cloud_base_url' => env('JTL_WAWI_CLOUD_BASE_URL', 'https://api.jtl-cloud.com/erp'),
    'cloud_token_url' => env('JTL_WAWI_CLOUD_TOKEN_URL', 'https://auth.jtl-cloud.com/oauth2/token'),

    // App-Identität für Registrierung und x-appid/x-appversion-Header.
    'app_id' => env('JTL_WAWI_APP_ID', 'org.schuppelius.workdiary'),
    'provider_name' => 'Schuppelius.org',
    'provider_website' => 'https://schuppelius.org',

    // Spiegelbestand: innerhalb der TTL antwortet der Provider aus dem
    // Snapshot, danach liest er live (Sekunden).
    'snapshot_ttl' => (int) env('JTL_WAWI_SNAPSHOT_TTL', 300),

    // Paginierung + Laufbudget je Sync-Lauf (Seiten, nicht Datensätze).
    'page_size' => 100,
    'sync_page_budget' => (int) env('JTL_WAWI_SYNC_PAGE_BUDGET', 20),

    // Reconciliation: Rückschau-Fenster vor occurred_at, in dem das
    // Änderungsjournal nach dem Quellmarker durchsucht wird (Minuten).
    'reconcile_lookback_minutes' => 10,
];
