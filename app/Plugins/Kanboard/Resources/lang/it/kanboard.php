<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : kanboard.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Acquisisce in sola lettura le attività di progetti Kanboard selezionati come attività nei progetti WorkDiary: titolo, descrizione, aperta o chiusa e scadenza. Conflitti e casi da chiarire finiscono nella inbox di integrazione.',
    ],
    'source' => 'Istanza',
    'task_link' => 'Aprire in Kanboard',
    'health' => [
        'not_configured' => 'Kanboard non è configurato (mancano l’indirizzo o l’accesso).',
        'not_connected' => 'La connessione Kanboard non è ancora stata verificata.',
    ],
    'settings' => [
        'base_url' => 'Indirizzo',
        'base_url_help' => 'Indirizzo dell’istanza Kanboard, ad es. https://kanban.example.com (senza /jsonrpc.php).',
        'username' => 'Nome utente',
        'username_help' => 'Lasci vuoto per l’accesso applicativo (utente «jsonrpc» con il token di Impostazioni → API). Con un nome utente vale il token di accesso personale di quell’utente e solo i suoi progetti.',
        'api_token' => 'Token API',
        'api_token_help' => 'Token applicativo da Impostazioni → API o token di accesso personale dell’utente.',
        'allow_private_network' => 'Consentire indirizzi privati',
        'allow_private_network_help' => 'Solo per un’istanza nella propria rete: consente un indirizzo privato o interno. Viene registrato e ha effetto solo se il gestore lo consente.',
    ],
];
