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
    'enabled' => env('STRIPE_ENABLED', false),
    'secret_key' => env('STRIPE_SECRET_KEY', ''),
    'api_base' => env('STRIPE_API_BASE', 'https://api.stripe.com'),
];
