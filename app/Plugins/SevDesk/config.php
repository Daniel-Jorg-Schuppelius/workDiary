<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : config.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * sevDesk-Plugin (MVP-125, Bauturbo A4). Eine REST-API für alle Accounts
 * (https://my.sevdesk.de/api/v1); „Update 2.0" ist Buchhaltungslogik je
 * Account — pro Mandant wird GET /Tools/bookkeepingSystemVersion gecacht.
 * Produktiv kommt der API-Token pro Organisation aus plugin_settings
 * (verschlüsselt); ENV ist nur Fallback für Tests/Konsole.
 */
return [
    'enabled' => env('SEVDESK_ENABLED', false),

    'base_url' => env('SEVDESK_BASE_URL', 'https://my.sevdesk.de/api/v1'),

    // Nur Fallback (Tests/Konsole) — produktiv je Org verschlüsselt in plugin_settings.
    'api_key' => env('SEVDESK_API_KEY'),

    // Standard-USt-Satz der Positionen (netto), analog Lexoffice-Defaults.
    'default_vat_rate' => (float) env('SEVDESK_DEFAULT_VAT_RATE', 19.0),

    // Buchhaltung 2.0: Steuerregel statt taxType. 1 = Umsatzsteuerpflichtige
    // Umsätze (sevDesk-Standardkatalog); je Installation übersteuerbar.
    'tax_rule_id' => (int) env('SEVDESK_TAX_RULE_ID', 1),

    // sevDesk-Kontaktkategorie für projizierte Kunden (3 = „Kunde" im
    // sevDesk-Standardkatalog).
    'contact_category_id' => (int) env('SEVDESK_CONTACT_CATEGORY_ID', 3),

    // sevDesk-Mengeneinheiten (Unity-Katalog): 1 = Stück, 9 = Stunde(n).
    // Katalog-IDs sind Standardwerte — Verifikation am Pilot-Account.
    'unity_piece_id' => (int) env('SEVDESK_UNITY_PIECE_ID', 1),
    'unity_hour_id' => (int) env('SEVDESK_UNITY_HOUR_ID', 9),

    // Seitengröße paginierter Reads: sevDesk erlaubt limit 1–1000 (sonst 400).
    'page_size' => (int) env('SEVDESK_PAGE_SIZE', 100),

    // Reconciliation-Fenster: wie viele jüngste Rechnungen nach dem
    // workdiary:-Quellmarker durchsucht werden, bevor neu angelegt wird.
    'reconcile_scan_limit' => (int) env('SEVDESK_RECONCILE_SCAN_LIMIT', 200),

    // Cache-Laufzeit der bookkeepingSystemVersion je Mandant (Sekunden).
    // Der Healthcheck erneuert den Wert bei jedem Lauf.
    'version_cache_ttl' => (int) env('SEVDESK_VERSION_CACHE_TTL', 21600),
];
