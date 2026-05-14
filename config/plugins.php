<?php

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
        env('LEXOFFICE_ENABLED', false) ? \App\Plugins\Lexoffice\LexofficePlugin::class : null,
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
