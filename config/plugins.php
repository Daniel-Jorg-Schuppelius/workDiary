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
    | App\Plugins\Contracts\Plugin. The PluginServiceProvider resolves them
    | from the container, so they may declare constructor dependencies.
    */
    'classes' => array_filter([
        env('LEXOFFICE_ENABLED', false) ? LexofficePlugin::class : null,
    ]),

    /*
    |--------------------------------------------------------------------------
    | Per-plugin configuration
    |--------------------------------------------------------------------------
    */
    'lexoffice' => [
        'enabled' => env('LEXOFFICE_ENABLED', false),
        'api_key' => env('LEXOFFICE_API_KEY'),
        // Default values applied to vouchers/contacts when not set on the model
        'default_currency' => env('LEXOFFICE_DEFAULT_CURRENCY', 'EUR'),
        'default_tax_type' => env('LEXOFFICE_DEFAULT_TAX_TYPE', 'net'), // net|gross
        'default_vat_rate' => (float) env('LEXOFFICE_DEFAULT_VAT_RATE', 19.0),
    ],
];
