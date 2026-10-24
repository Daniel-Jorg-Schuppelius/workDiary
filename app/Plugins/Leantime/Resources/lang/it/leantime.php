<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : leantime.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Acquisisce in sola lettura le attività di progetti Leantime selezionati come attività nei progetti WorkDiary: titolo, descrizione, stato secondo il tipo di stato e scadenza; le milestone restano escluse. Conflitti e casi da chiarire finiscono nella inbox di integrazione.',
    ],
    'source' => 'Istanza',
    'task_link' => 'Aprire in Leantime',
    'health' => [
        'not_configured' => 'Leantime non è configurato (mancano l’indirizzo o l’accesso).',
        'not_connected' => 'La connessione Leantime non è ancora stata verificata.',
    ],
    'settings' => [
        'base_url' => 'Indirizzo',
        'base_url_help' => 'Indirizzo dell’istanza Leantime, ad es. https://leantime.example.com (senza /api/jsonrpc).',
        'api_key' => 'Chiave API',
        'api_key_help' => 'La crei in Leantime in Impostazioni azienda → API e la assegni ai progetti da acquisire; la chiave viene mostrata una sola volta. Richiede Leantime 3.8 o successivo.',
        'allow_private_network' => 'Consentire indirizzi privati',
        'allow_private_network_help' => 'Solo per un’istanza nella propria rete: consente un indirizzo privato o interno. Viene registrato e ha effetto solo se il gestore lo consente.',
    ],
];
