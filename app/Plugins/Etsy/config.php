<?php
/*
 * Created on   : Tue Aug 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : config.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Etsy-Plugin (Feature 101, MVP-494–498, Phase 66). Open API v3
 * (https://developers.etsy.com), OAuth2 Authorization Code MIT PKCE-Pflicht;
 * Etsy ist ein Public Client — der Token-Tausch läuft ohne client_secret
 * (leeres Secret, das api-toolkit lässt den Parameter dann weg; W0-Preflight
 * 2026-08-04 gegen Spec+Doku). Zusätzlich trägt JEDER API-Request den Header
 * `x-api-key: <keystring>:<shared_secret>` (Spec securitySchemes.api_key).
 * Eine Token-Revocation existiert nicht (W0: 0 Spec-Treffer).
 *
 * Jede Organisation registriert ihre EIGENE Etsy-Seller-App (Freigabe in
 * Minuten) und hinterlegt den Keystring in den Plugin-Settings — so bleibt
 * workDiary mandantenfähig ohne Etsys „Commercial Access"-Review. ENV ist
 * nur Fallback für Tests/Konsole.
 *
 * Endpoint-URLs sind bewusst CONFIG-ONLY (Allowlist-Regel wie
 * DomainReselling) — nie aus plugin_settings.
 *
 * Rate Limits: 10 req/s + 10.000 req/Tag (429 + Retry-After macht das
 * Toolkit) → Request-Intervall 0,2 s + Seiten-Budget je Sync-Lauf.
 */
return [
    'enabled' => env('ETSY_ENABLED', false),

    // Endpoint-URLs: config-only (SSRF-/Allowlist-Disziplin).
    'base_url' => env('ETSY_BASE_URL', 'https://api.etsy.com'),
    'authorize_url' => env('ETSY_AUTHORIZE_URL', 'https://www.etsy.com/oauth/connect'),
    'token_url' => env('ETSY_TOKEN_URL', 'https://api.etsy.com/v3/public/oauth/token'),

    // Nur Fallback (Tests/Konsole) — produktiv je Org verschlüsselt in plugin_settings.
    'keystring' => env('ETSY_KEYSTRING'),
    'shared_secret' => env('ETSY_SHARED_SECRET'),

    // Voller Scope-Satz schon ab W1 (Nachfordern = Re-Connect; Policy §18.5).
    'scopes' => env('ETSY_SCOPES', 'transactions_r transactions_w shops_r listings_r billing_r'),

    // 10 req/s dokumentiert → 0,2 s Intervall (zugleich ClientAbstract-Minimum).
    'request_interval' => (float) env('ETSY_REQUEST_INTERVAL', 0.2),

    // Seitengröße der Receipt-/Ledger-Abfragen (Etsy-Maximum: 100).
    'page_size' => (int) env('ETSY_PAGE_SIZE', 100),

    // Seiten je Sync-Lauf (Tagesbudget 10.000 req — Rest holt der nächste Lauf).
    'sync_page_budget' => (int) env('ETSY_SYNC_PAGE_BUDGET', 10),

    // Erstlauf-Fenster (Tage) ohne vorhandenen Aufholpunkt.
    'initial_window_days' => (int) env('ETSY_INITIAL_WINDOW_DAYS', 30),

    // Überlappung (Minuten) auf den min_last_modified-Aufholpunkt.
    'overlap_minutes' => (int) env('ETSY_OVERLAP_MINUTES', 5),
];
