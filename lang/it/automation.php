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
        'user_created' => 'Collaboratore creato',
        'asset_checked_out' => 'Attrezzatura consegnata',
        'expense_submitted' => 'Nota spese inviata',
        'open_issue_created' => 'Punto aperto creato',
        'procedure_deviation_recorded' => 'Deviazione di procedura registrata',
        'incoming_invoice_received' => 'Fattura in entrata ricevuta',
    ],
    'action' => [
        'learning_enroll' => 'Iscrivere al corso',
        'expense_approve' => 'Approvare le spese',
        'diary_follow_up' => 'Creare ordine successivo',
        'incoming_invoice_discard' => 'Scartare la fattura in entrata',
        'incoming_invoice_reject' => 'Archiviare la fattura in entrata come «non è una fattura»',
        'incoming_invoice_assign' => 'Assegnare la fattura in entrata',
    ],
    'form' => [
        'target' => 'Destinazione',
        'target_hint' => 'Solo per azioni con destinazione, ad esempio il corso di un’iscrizione.',
        'target_course' => 'Corso',
        'title' => 'Nuova regola di automazione',
        'trigger' => 'Attivatore',
        'action' => 'Azione',
        'action_hint' => 'L’azione deve corrispondere all’attivatore.',
        'conditions' => 'Condizioni (JSON)',
        'conditions_hint' => 'Una condizione vuota {"all":[]} vale sempre. Esempi:',
    ],
    'error' => [
        'target_required' => 'Scelga una destinazione dall’elenco.',
        'invalid_json' => 'Le condizioni non sono un oggetto JSON valido.',
        'action_trigger_mismatch' => 'Questa azione non corrisponde all’attivatore scelto.',
    ],
];
