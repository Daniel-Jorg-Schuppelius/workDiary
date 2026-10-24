<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : asana.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Importa le attività di progetti Asana selezionati in progetti WorkDiary, in sola lettura: titolo, note, completamento e scadenza. Conflitti e casi da verificare finiscono nella posta in arrivo delle integrazioni.',
    ],
    'source' => 'Workspace',
    'task_link' => 'Apri in Asana',
    'health' => [
        'not_configured' => 'Asana non è configurato per questa installazione (manca la registrazione dell’app dell’editore).',
        'not_connected' => 'Nessuna connessione Asana con un workspace selezionato.',
    ],
];
