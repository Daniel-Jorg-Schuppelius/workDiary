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
    'enabled' => env('DATEV_ONLINE_ENABLED', false),
    'client_id' => env('DATEV_ONLINE_CLIENT_ID', ''),
    'client_secret' => env('DATEV_ONLINE_CLIENT_SECRET', ''),
    // Livegang erst mit DATEV-Vertrag und freigeschalteter Produktivumgebung.
    'sandbox' => env('DATEV_ONLINE_SANDBOX', true),
];
