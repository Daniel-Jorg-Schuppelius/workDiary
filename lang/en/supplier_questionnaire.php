<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : supplier_questionnaire.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Lieferanten-Selbstauskunft (MVP-937).
return [
    'title' => 'Supplier self-assessment',
    'subtitle' => 'Questionnaires for suppliers (e.g. sustainability, supply chain, quality) with one-time link, review and validity.',
    'card' => 'Self-assessment',
    'questionnaires' => 'Questionnaires',
    'recent' => 'Recent requests',
    'create' => 'Create questionnaire',
    'edit' => 'Edit questionnaire',
    'save' => 'Save',
    'send' => 'Request self-assessment',
    'send_hint' => 'The supplier receives a link by email that is valid for :days days. An open request for the same questionnaire is withdrawn.',
    'open' => 'Open',
    'none' => 'No self-assessment requested yet.',
    'empty' => 'No questionnaires yet.',
    'no_requests' => 'No requests yet.',
    'inactive' => 'inactive',
    'valid_until' => 'valid until :date',
    'submitted_at' => 'submitted on :date',
    'waiting' => 'Waiting for a reply from :email (link valid until :date).',
    'accept' => 'Accept',
    'reject' => 'Return for rework',
    'public_title' => 'Self-assessment for :org',
    'public_submit' => 'Submit answers',
    'public_thanks' => 'Thank you, your answers have been received.',
    'public_rework' => 'Please complete your answers: :note',
    'field' => [
        'name' => 'Questionnaire',
        'description' => 'Note for the supplier',
        'questions' => 'Questions',
        'validity_months' => 'Validity (months)',
        'is_active' => 'Active',
        'requests' => 'Requests',
        'recipient_email' => 'Supplier email',
        'sent_at' => 'Requested',
        'status' => 'Status',
        'valid_until' => 'Valid until',
        'note' => 'Note',
    ],
    'status' => [
        'sent' => 'Requested',
        'submitted' => 'Submitted',
        'accepted' => 'Accepted',
        'rejected' => 'Returned for rework',
        'withdrawn' => 'Withdrawn',
    ],
    'flash' => [
        'saved' => 'Questionnaire saved.',
        'sent' => 'Request sent to :email.',
        'reviewed' => 'Review saved.',
    ],
    'error' => [
        'transition' => 'The self-assessment cannot change from “:from” to “:to”.',
    ],
    'mail' => [
        'subject' => 'Self-assessment for :org',
        'body' => "Hello,\n\n:org asks you to complete the self-assessment “:name”. Please fill in the questionnaire by :until:\n:url\n\nThank you.",
    ],
];
