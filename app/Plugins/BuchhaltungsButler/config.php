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
 * BuchhaltungsButler-Plugin (MVP-432, Phase 40). REST-API, Basic Auth
 * `<Api Client>:<Api Secret>` + Pflicht-Formfeld `api_key`; Limit
 * 100 req/Kunde/min. Die API-Doku (app.buchhaltungsbutler.de/docs/api/v1/)
 * ist bot-gesperrt — Base-URL und Pfade sind deshalb konfigurierbar und
 * werden am Pilot-Konto verifiziert (Feature 093, W2.0).
 */
return [
    'enabled' => env('BHB_ENABLED', false),

    'base_url' => env('BHB_BASE_URL', 'https://app.buchhaltungsbutler.de/api/v1'),

    // Nur Fallback (Tests/Konsole) — produktiv je Org verschlüsselt in plugin_settings.
    'api_client' => env('BHB_API_CLIENT'),
    'api_secret' => env('BHB_API_SECRET'),
    'api_key' => env('BHB_API_KEY'),

    // Dokumentierte Annahmen (Pilot-Verifikation): Beleg-Upload und billigste
    // Lese-Probe. Die BHB-v1-API ist POST-basiert.
    'receipt_upload_path' => env('BHB_RECEIPT_UPLOAD_PATH', '/receipts/add'),
    'probe_path' => env('BHB_PROBE_PATH', '/receipts/get'),

    // 100 req/Kunde/min (dokumentiert) → 0,6 s Request-Intervall.
    'rate_limit_per_minute' => (int) env('BHB_RATE_LIMIT_PER_MINUTE', 100),
];
