<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : shopware.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Legge gli ordini di un negozio Shopware 6 tramite un’integrazione, li rispecchia in WorkDiary e assegna gli acquirenti a clienti esistenti; gli acquirenti incerti diventano casi da chiarire. Nel negozio non viene scritto nulla.',
    ],
    'settings' => [
        'base_url' => 'Indirizzo del negozio',
        'base_url_help' => 'Indirizzo del negozio, ad es. https://shop.example.com (senza /api o /admin).',
        'client_id' => 'ID chiave di accesso',
        'client_id_help' => 'In Shopware crei un’integrazione in Impostazioni → Sistema → Integrazioni. Serve il permesso di lettura su ordini, clienti dell’ordine, clienti, righe, transazioni di pagamento con catture e rimborsi, valute e stati, oppure l’opzione «Amministratore».',
        'client_secret' => 'Chiave di accesso segreta',
    ],
    'search' => [
        'domain' => 'Ordini Shopware',
    ],
    'cockpit' => [
        'title' => 'Ordini Shopware',
    ],
    'health' => [
        'not_configured' => 'Shopware non è configurato (mancano l’indirizzo del negozio o l’integrazione).',
        'not_connected' => 'La connessione Shopware non è ancora stata verificata.',
    ],
];
