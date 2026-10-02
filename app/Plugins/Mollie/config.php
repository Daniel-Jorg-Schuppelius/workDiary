<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : config.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'enabled' => env('MOLLIE_ENABLED', false),
    'api_key' => env('MOLLIE_API_KEY', ''),
    'api_base' => env('MOLLIE_API_BASE', 'https://api.mollie.com'),
];
