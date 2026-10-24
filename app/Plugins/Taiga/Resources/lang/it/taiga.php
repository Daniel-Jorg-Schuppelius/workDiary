<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : taiga.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Acquisisce in sola lettura le user story di progetti Taiga selezionati come attività nei progetti WorkDiary: titolo, descrizione, aperta o chiusa e scadenza. Conflitti e casi da chiarire finiscono nella inbox di integrazione.',
    ],
    'source' => 'Istanza',
    'task_link' => 'Aprire in Taiga',
    'health' => [
        'not_configured' => 'Taiga non è configurato (mancano l’indirizzo o l’accesso).',
        'not_connected' => 'La connessione Taiga non è ancora stata verificata.',
    ],
    'settings' => [
        'base_url' => 'Indirizzo',
        'base_url_help' => 'Indirizzo dell’API: https://api.taiga.io per Taiga Cloud, altrimenti l’indirizzo della propria istanza (senza /api/v1).',
        'username' => 'Nome utente',
        'username_help' => 'Nome utente o indirizzo e-mail di un account con accesso in lettura ai progetti; idealmente un account dedicato all’integrazione. L’account necessita nei progetti del diritto di vedere le user story.',
        'password' => 'Password',
        'password_help' => 'Taiga non rilascia token API permanenti; l’integrazione accede di nuovo a ogni esecuzione. Gli account che accedono tramite GitHub o GitLab necessitano di una password propria.',
        'allow_private_network' => 'Consentire indirizzi privati',
        'allow_private_network_help' => 'Solo per un’istanza nella propria rete: consente un indirizzo privato o interno. Viene registrato e ha effetto solo se il gestore lo consente.',
    ],
];
