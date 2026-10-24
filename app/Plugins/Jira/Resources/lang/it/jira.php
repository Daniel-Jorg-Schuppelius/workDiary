<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : jira.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Importa i ticket di progetti Jira Cloud selezionati come attività in progetti WorkDiary, in sola lettura: titolo, descrizione, stato tramite la categoria di stato e scadenza. Conflitti e casi da verificare finiscono nella posta in arrivo delle integrazioni.',
    ],
    'source' => 'Sito',
    'task_link' => 'Apri in Jira',
    'health' => [
        'not_configured' => 'Jira non è configurato per questa installazione (manca la registrazione dell’app dell’editore).',
        'not_connected' => 'Nessuna connessione Jira con un sito selezionato.',
    ],
];
