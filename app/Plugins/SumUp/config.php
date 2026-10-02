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
    'enabled' => env('SUMUP_ENABLED', false),
    'api_key' => env('SUMUP_API_KEY', ''),
    'merchant_code' => env('SUMUP_MERCHANT_CODE', ''),
    'api_base' => env('SUMUP_API_BASE', 'https://api.sumup.com'),
];
