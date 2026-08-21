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
];
