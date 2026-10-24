<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : vikunja.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Acquisisce in sola lettura le attività di progetti Vikunja selezionati come attività nei progetti WorkDiary: titolo, descrizione, completata e scadenza. Conflitti e casi da chiarire finiscono nella inbox di integrazione.',
    ],
    'source' => 'Istanza',
    'task_link' => 'Aprire in Vikunja',
    'health' => [
        'not_configured' => 'Vikunja non è configurato (mancano l’indirizzo o l’accesso).',
        'not_connected' => 'La connessione Vikunja non è ancora stata verificata.',
    ],
    'settings' => [
        'base_url' => 'Indirizzo',
        'base_url_help' => 'Indirizzo dell’istanza Vikunja, ad es. https://todo.example.com (senza /api/v1).',
        'api_token' => 'Token API',
        'api_token_help' => 'Lo crei in Vikunja in Impostazioni → Token API, con permesso di lettura su progetti e attività. Richiede Vikunja 1.0 o successivo; con il diritto «other → user» la verifica mostra anche il nome dell’account.',
        'allow_private_network' => 'Consentire indirizzi privati',
        'allow_private_network_help' => 'Solo per un’istanza nella propria rete: consente un indirizzo privato o interno. Viene registrato e ha effetto solo se il gestore lo consente.',
    ],
];
