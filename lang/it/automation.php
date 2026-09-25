<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : automation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'trigger' => [
        'expense_submitted' => 'Nota spese inviata',
        'open_issue_created' => 'Punto aperto creato',
        'procedure_deviation_recorded' => 'Deviazione di procedura registrata',
    ],
    'action' => [
        'expense_approve' => 'Approvare le spese',
        'diary_follow_up' => 'Creare ordine successivo',
    ],
    'form' => [
        'title' => 'Nuova regola di automazione',
        'trigger' => 'Attivatore',
        'action' => 'Azione',
        'action_hint' => 'L’azione deve corrispondere all’attivatore.',
        'conditions' => 'Condizioni (JSON)',
        'conditions_hint' => 'Una condizione vuota {"all":[]} vale sempre. Esempi:',
    ],
    'error' => [
        'invalid_json' => 'Le condizioni non sono un oggetto JSON valido.',
        'action_trigger_mismatch' => 'Questa azione non corrisponde all’attivatore scelto.',
    ],
];
