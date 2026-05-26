<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : plugins.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Plugins\Lexoffice\LexofficePlugin;

return [
    /*
    |--------------------------------------------------------------------------
    | Registered plugin classes
    |--------------------------------------------------------------------------
    |
    | Each entry must be a fully qualified class name implementing
    | App\Plugins\Contracts\Plugin. Wird IMMER geladen — Aktivierung pro
    | Organisation erfolgt über die plugin_settings-Tabelle (s. /admin/plugins).
    | ENV-Variablen wie LEXOFFICE_API_KEY dienen nur noch als Fallback für
    | Tests/Konsolen-Kontexte ohne UI-Konfiguration.
    */
    'classes' => [
        LexofficePlugin::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Disable
    |--------------------------------------------------------------------------
    |
    | Anzahl aufeinanderfolgender Boot-/Runtime-/Healthcheck-Fehler eines
    | Plugins, ab der das Plugin global stillgelegt wird (Setzen von
    | plugin_states.disabled_reason). 0 = nie automatisch deaktivieren.
    */
    'auto_disable_threshold' => (int) env('PLUGINS_AUTO_DISABLE_THRESHOLD', 5),

    /*
    |--------------------------------------------------------------------------
    | Per-plugin configuration
    |--------------------------------------------------------------------------
    */
    'lexoffice' => [
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
    ],
];
