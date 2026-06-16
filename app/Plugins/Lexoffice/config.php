<?php
/*
 * Created on   : Mon Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : config.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 | Lexoffice. Kontakt-/Beleg-Sync. Eingehängt vom LexofficeServiceProvider
 | unter `plugins.lexoffice`. ENV dient nur als Fallback für Tests/Konsolen-
 | Kontexte ohne UI-Konfiguration; produktiv kommt die Config pro Organisation
 | aus plugin_settings.
 */
return [
    'enabled' => env('LEXOFFICE_ENABLED', false),
    'api_key' => env('LEXOFFICE_API_KEY'),
    'base_url' => env('LEXOFFICE_BASE_URL', 'https://api.lexoffice.io/v1'),
    // Default values applied to vouchers/contacts when not set on the model
    'default_currency' => env('LEXOFFICE_DEFAULT_CURRENCY', 'EUR'),
    'default_tax_type' => env('LEXOFFICE_DEFAULT_TAX_TYPE', 'net'), // net|gross
    'default_vat_rate' => (float) env('LEXOFFICE_DEFAULT_VAT_RATE', 19.0),
    // Strategie bei Konflikten zwischen Remote- und Local-Stand beim Pull-Sync.
    // Werte: lexoffice_wins | local_wins | manual_review (siehe LexofficeMatchPolicy).
    'match_policy' => env('LEXOFFICE_MATCH_POLICY', 'manual_review'),
    // Soll der Pull-Sync remote Kontakte ohne lokales Pendant lokal neu anlegen?
    'create_missing_local' => (bool) env('LEXOFFICE_CREATE_MISSING_LOCAL', false),
];
