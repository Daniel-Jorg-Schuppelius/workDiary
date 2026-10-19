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
        'expense_submitted' => 'Spesenabrechnung eingereicht',
        'open_issue_created' => 'Offener Punkt angelegt',
        'procedure_deviation_recorded' => 'Prozedur-Abweichung erfasst',
        'incoming_invoice_received' => 'Rechnungseingang eingegangen',
    ],
    'action' => [
        'expense_approve' => 'Spesen freigeben',
        'diary_follow_up' => 'Folgeauftrag anlegen',
        'incoming_invoice_discard' => 'Rechnungseingang verwerfen',
        'incoming_invoice_reject' => 'Rechnungseingang als keine Rechnung ablegen',
        'incoming_invoice_assign' => 'Rechnungseingang zuordnen',
    ],
    'form' => [
        'title' => 'Neue Automationsregel',
        'trigger' => 'Auslöser',
        'action' => 'Aktion',
        'action_hint' => 'Die Aktion muss zum Auslöser passen.',
        'conditions' => 'Bedingungen (JSON)',
        'conditions_hint' => 'Leere Bedingung {"all":[]} gilt immer. Beispiele:',
    ],
    'error' => [
        'invalid_json' => 'Die Bedingungen sind kein gültiges JSON-Objekt.',
        'action_trigger_mismatch' => 'Diese Aktion passt nicht zum gewählten Auslöser.',
    ],
];
