<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : inspection_order.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Prüfaufträge an Dienstleister (MVP-938).
return [
    'title' => 'Inspection orders',
    'subtitle' => 'Assign due inspections to an inspection provider: offer, acceptance, results per item and takeover as inspection record.',
    'create' => 'Create inspection order',
    'send' => 'Send order',
    'open' => 'Open',
    'empty' => 'No inspection orders yet.',
    'no_schedules' => 'No open inspection dates.',
    'due' => 'due :date',
    'accept' => 'Accept offer',
    'reject' => 'Reject offer',
    'take_over' => 'Take over results',
    'taken_over' => 'taken over',
    'cancel' => 'Cancel order',
    'confirm_cancel' => 'Cancel the order? The inspection dates are released again.',
    'public_title' => 'Inspection order from :org',
    'public_offer' => 'Submit an offer',
    'public_submit_offer' => 'Send offer',
    'public_report' => 'Report results',
    'public_submit_report' => 'Send results',
    'public_reported' => 'The results have been submitted. Thank you.',
    'field' => [
        'title' => 'Title',
        'supplier' => 'Inspection provider',
        'recipient_email' => 'Provider email',
        'items' => 'Items',
        'status' => 'Status',
        'offer_amount' => 'Offer price',
        'offer_planned_on' => 'Planned date',
        'offer_note' => 'Note on the offer',
        'asset' => 'Item',
        'result' => 'Result',
        'performed_on' => 'Inspected on',
        'valid_until' => 'Valid until',
        'certificate_no' => 'Certificate number',
        'certificate_file' => 'Certificate (PDF)',
        'event' => 'Inspection record',
    ],
    'status' => [
        'requested' => 'Requested',
        'offered' => 'Offer received',
        'accepted' => 'Commissioned',
        'reported' => 'Results reported',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ],
    'flash' => [
        'sent' => 'Inspection order sent to :email.',
        'decided' => 'Decision saved.',
        'taken_over' => ':count inspection records taken over.',
        'cancelled' => 'Order cancelled.',
        'offered' => 'Thank you, your offer has been received.',
        'reported' => 'Thank you, the results have been received.',
    ],
    'error' => [
        'no_schedules' => 'Please select at least one open inspection date.',
        'nothing_reported' => 'Please enter at least one result.',
        'transition' => 'The order cannot change from “:from” to “:to”.',
    ],
    'mail' => [
        'subject' => 'Inspection order from :org: :title',
        'body' => "Hello,\n\n:org asks you for an offer for the inspection “:title” (:count items). Use the following link to submit your offer and, once commissioned, report the results:\n:url\n\nThe link is valid until :until.",
    ],
];
