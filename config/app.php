<?php

/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : app.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'WorkDiary'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => 'UTC',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Available Locales
    |--------------------------------------------------------------------------
    |
    | List of locales the UI is allowed to switch to. `de` and `en` ship with
    | full translations; `fr` and `it` are structural placeholders that fall
    | back to English content until proper translations are provided.
    |
    */

    'available_locales' => array_filter(array_map('trim', explode(',', (string) env('APP_AVAILABLE_LOCALES', 'de,en,fr,it')))),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

    'legacy_write_enabled' => (bool) env('LEGACY_WRITE_ENABLED', false),

    'mail_notifications_enabled' => (bool) env('MAIL_NOTIFICATIONS_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Arbeitszeiten
    |--------------------------------------------------------------------------
    |
    | Kern- und erweiterte Arbeitszeit für die Wochenansicht (Hintergrund-Bänder).
    | Wochentage als ISO-Tagesnummern (1=Mo … 7=So). Zeiten als "HH:MM".
    |
    */

    'work_hours' => [
        'core' => [
            'start' => env('WORK_HOURS_CORE_START', '08:00'),
            'end' => env('WORK_HOURS_CORE_END', '16:00'),
            'days' => array_filter(array_map('intval', explode(',', (string) env('WORK_HOURS_CORE_DAYS', '1,2,3,4,5')))),
        ],
        'extended' => [
            'start' => env('WORK_HOURS_EXTENDED_START', '06:00'),
            'end' => env('WORK_HOURS_EXTENDED_END', '19:00'),
            'days' => array_filter(array_map('intval', explode(',', (string) env('WORK_HOURS_EXTENDED_DAYS', '1,2,3,4,5')))),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Feiertage
    |--------------------------------------------------------------------------
    |
    | Region (Yasumi-Provider): "Germany" (bundesweit) oder eines der Bundesländer
    | wie "Germany\\Berlin", "Germany\\Bavaria", "Germany\\NorthRhineWestphalia" usw.
    | Locale: "de_DE" für deutsche Feiertagsnamen.
    |
    */

    'holidays' => [
        'provider' => env('HOLIDAYS_PROVIDER', 'Germany\\Berlin'),
        'locale' => env('HOLIDAYS_LOCALE', 'de_DE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant Self-Registration
    |--------------------------------------------------------------------------
    | Set REGISTRATION_ENABLED=true in .env to allow new tenants to sign up
    | via /register. Disabled by default for on-premise installations.
    */
    'registration_enabled' => env('REGISTRATION_ENABLED', false),

];
