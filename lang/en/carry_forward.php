<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : carry_forward.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Merker für die nächste Periode (Feature 201, MVP-1645): Komponente <x-carry-forward-notes>.
return [
    'title' => 'Notes for this time',
    'empty' => [
        'title' => 'No open notes',
        'message' => 'Nothing was left for :period in earlier periods.',
    ],
    'open_heading' => 'Open notes from earlier periods',
    'left_by' => 'Left by :name in :period, applies from :from.',
    'resolution_label' => 'Remark when ticking off',
    'resolution_placeholder' => 'Short remark (optional), e.g. “certificate requested”',
    'resolve' => 'Tick off',
    'resolved_heading' => 'Notes ticked off here',
    'resolved_by' => 'Ticked off by :name on :date',
    'left_heading' => 'Left for later',
    'from_period' => 'from :period',
    'withdraw' => 'Withdraw note',
    'withdraw_confirm' => 'Really withdraw this note? It will then not appear next time.',
    'new_label' => 'Note for next time',
    'new_hint' => 'Appears at the top of the next occurrence of this series until someone ticks it off. Other notes of this item do not carry over.',
    'target_label' => 'Applies',
    'create' => 'Leave note',
    'help' => 'How do notes for next time work?',
    // Cockpit von Kunde, Lieferant, Projekt und Asset (MVP-1646).
    'cockpit' => [
        'summary' => 'Open notes have not been ticked off; they appear at the next occurrence of the series from their target period.',
        'contracts' => 'Notes on contract obligations',
        'inspections' => 'Notes on inspection duties',
        'recurring_orders' => 'Notes on recurring orders',
        'period_orders' => 'Notes on orders with a period',
    ],
    'flash' => [
        'created' => 'Note for next time saved.',
        'resolved' => 'Note ticked off.',
        'withdrawn' => 'Note withdrawn.',
    ],
    'error' => [
        'no_series' => 'This item does not belong to a series with a period; a note is not possible here.',
        'target_not_after' => 'A note applies from the next period at the earliest.',
        'empty' => 'Please enter a text for the note.',
        'foreign_series' => 'This note does not belong to this series.',
        'already_resolved' => 'This note has already been ticked off.',
        'not_due' => 'This note only applies from a later period.',
    ],
];
