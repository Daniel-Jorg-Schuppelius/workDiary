<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : quotes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Angebote (Feature 112, MVP-601: Nachfassen).
return [
    'follow_up' => [
        'title' => 'Quote follow-ups',
        'subtitle' => 'Due follow-ups, expiring quotes and sent quotes without a date',
        'action' => 'Record follow-up',
        'submit' => 'Record',
        'recorded' => 'Follow-up recorded.',
        'scheduled' => 'Follow-up date set.',
        'schedule_action' => 'Set follow-up date',
        'schedule_title' => 'Follow-up date for quote :number',
        'schedule_hint' => 'Sets or moves the date without recording a conversation result. A follow-up that was already finished becomes open again.',
        'schedule_submit' => 'Set date',
        'empty' => 'Nothing to follow up.',
        'dialog_title' => 'Follow up on quote :number',
        'dialog_hint' => 'The result is kept as a communication note in the customer file.',
        'result' => 'Result of the conversation',
        'result_hint' => 'What did the customer say? This note is the basis for the next quote.',
        'next_at' => 'Follow up again on',
        'next_at_hint' => 'Leave empty when the follow-up is finished.',
        'note_subject' => 'Follow-up on quote :number',
        'next_action' => 'Follow up on quote :number again',
        'wrong_status' => 'Only sent or approved quotes can be followed up.',
        'no_customer' => 'The quote has no customer — without one there is no file for the note.',
        'kpi' => [
            'due' => 'Due',
            'upcoming' => 'Upcoming',
            'expiring' => 'Expiring (:days days)',
            'expiring_hint' => 'No reaction — afterwards the quote must be re-issued or extended.',
            'untracked' => 'Without a date',
            'untracked_hint' => 'Sent, but nobody set a follow-up date.',
        ],
        'section' => [
            'due' => 'Due',
            'upcoming' => 'Upcoming',
            'expiring' => 'Expiring without reaction',
            'untracked' => 'Sent without a follow-up date',
        ],
        'column' => [
            'number' => 'Quote',
            'customer' => 'Customer',
            'owner' => 'Owner',
            'follow_up_at' => 'Follow up on',
            'valid_until' => 'Valid until',
            'total' => 'Total',
        ],
        'filter' => ['mine' => 'Mine only'],
    ],
    // Trefferquote (Feature 112, MVP-998).
    'win_rate' => [
        'title' => 'Quote win rate',
        'subtitle' => 'Won, lost and expired quotes by decision date or validity end',
        'group' => 'Group by',
        'group_customer' => 'Customer',
        'group_owner' => 'Responsible',
        'rate' => 'Win rate (count)',
        'volume_rate' => 'Win rate (volume)',
        'won' => 'Won',
        'lost' => 'Lost',
        'expired' => 'Expired',
        'won_volume' => 'Volume won',
        'decided_volume' => 'Volume decided',
        'open' => 'Open quotes',
        'open_hint' => 'Not decided and not expired — not part of the rate.',
        'empty' => 'No quote was decided in this period.',
        'note' => 'Only the latest version per quote number counts; volume is net, for partial acceptance the accepted part.',
        'no_owner' => 'No one responsible',
        'link' => 'Win rate',
    ],
    'external_invoice' => [
        'action' => 'Invoiced in the accounting system',
        'title' => 'Quote :number invoiced in the accounting system',
        'hint' => 'The quote was invoiced in the accounting system. It then leaves the list “Accepted quotes without invoice”. The invoice number is optional.',
        'number' => 'Invoice number in the accounting system',
        'submit' => 'Mark as invoiced',
        'marked' => 'Quote marked as invoiced in the accounting system.',
        'unmarked' => 'Mark removed.',
        'unmark' => 'Remove “invoiced” mark',
        'unmark_confirm' => 'Remove the mark? The quote will appear again under “Accepted quotes without invoice”.',
        'marked_on' => 'Invoiced in the accounting system (marked on :date)',
        'marked_with_number' => 'Invoiced in the accounting system: :number (marked on :date)',
    ],
    // Auftragsbestätigung als eigener Beleg (Phase 145, MVP-1162).
    'order_confirmation' => [
        'open' => 'Order confirmation :number',
        'create' => 'Create order confirmation',
        'confirm' => 'Confirm',
        'cancel' => 'Cancel',
        'cancel_confirm' => 'Cancel the order confirmation? It remains in the accounting program.',
        'to_quote' => 'Quote :number',
        'confirmed_on' => 'Confirmed on :date',
        'number_pending' => ':program assigns the number on confirmation; until then the order confirmation carries a draft number.',
        'pdf' => [
            'number' => 'No. :number',
        ],
        'flash' => [
            'confirmed' => 'Order confirmation :number confirmed.',
            'cancelled' => 'Order confirmation cancelled.',
            'create_first' => 'Please create the order confirmation first.',
        ],
        'error' => [
            'not_won' => 'Only accepted quotes are confirmed.',
            'not_draft' => 'Only drafts can be confirmed.',
            'empty' => 'The order confirmation has no items.',
            'not_cancellable' => 'This order confirmation cannot be cancelled.',
            'not_confirmed' => 'The order confirmation has not been confirmed yet.',
        ],
    ],
];
