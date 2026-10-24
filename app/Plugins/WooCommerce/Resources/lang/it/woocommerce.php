<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : woocommerce.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Legge gli ordini di un negozio WooCommerce, li rispecchia in WorkDiary e assegna gli acquirenti a clienti esistenti; gli acquirenti incerti diventano casi da chiarire. Nel negozio non viene scritto nulla.',
    ],
    'settings' => [
        'base_url' => 'Indirizzo del negozio',
        'base_url_help' => 'Indirizzo del negozio WordPress con https, ad es. https://shop.example.com (senza /wp-json).',
        'consumer_key' => 'Chiave client (consumer key)',
        'consumer_key_help' => 'La crei in WooCommerce in Impostazioni → Avanzate → API REST, con permesso di lettura.',
        'consumer_secret' => 'Segreto client (consumer secret)',
    ],
    'search' => [
        'domain' => 'Ordini WooCommerce',
    ],
    'cockpit' => [
        'title' => 'Ordini WooCommerce',
    ],
    'health' => [
        'not_configured' => 'WooCommerce non è configurato (mancano l’indirizzo del negozio o le chiavi).',
        'not_connected' => 'La connessione WooCommerce non è ancora stata verificata.',
    ],
];
