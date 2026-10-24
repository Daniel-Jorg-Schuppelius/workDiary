<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : prestashop.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Legge gli ordini di un negozio PrestaShop tramite il webservice, li rispecchia in WorkDiary e assegna gli acquirenti a clienti esistenti; gli acquirenti incerti diventano casi da chiarire. Nel negozio non viene scritto nulla.',
    ],
    'settings' => [
        'base_url' => 'Indirizzo del negozio',
        'base_url_help' => 'Indirizzo del negozio, ad es. https://shop.example.com (senza /api).',
        'api_key' => 'Chiave del webservice',
        'api_key_help' => 'In PrestaShop attivi il webservice in Parametri avanzati → Webservice e crei una chiave con permesso di lettura (GET) su orders, customers, currencies e order_states; per i rimborsi anche order_slip, per il fuso orario configurations.',
        'shop_timezone' => 'Fuso orario del negozio',
        'shop_timezone_help' => 'PrestaShop fornisce gli orari senza fuso orario. Lasci vuoto per usare il fuso letto durante la verifica della connessione (permesso configurations), altrimenti quello della Sua organizzazione; oppure inserisca ad es. Europe/Vienna.',
    ],
    'search' => [
        'domain' => 'Ordini PrestaShop',
    ],
    'cockpit' => [
        'title' => 'Ordini PrestaShop',
    ],
    'health' => [
        'not_configured' => 'PrestaShop non è configurato (mancano l’indirizzo del negozio o la chiave).',
        'not_connected' => 'La connessione PrestaShop non è ancora stata verificata.',
    ],
];
