<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : circular.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

return [
    'title' => 'Circulars',
    'subtitle' => 'Business notices to a filtered set of customers',
    'empty' => 'No circular created yet.',
    'empty_recipients' => 'No recipients recorded.',
    'created' => 'Circular created.',
    'sent' => 'Circular sent.',
    'already_sent' => 'This circular has already been sent.',
    'no_recipients' => 'The selected filter matches no customer.',
    'mandatory_short' => 'Mandatory notice',
    'portal_short' => 'Visible in portal',
    'no_email' => 'no e-mail address',
    'confirm_send' => 'Send the circular to :count recipients now?',
    'body_hint' => 'Placeholders: :firma, :kunde, :ansprechpartner',
    'mandatory_hint' => 'Mandatory notices also reach customers who opted out of bulk mail — for legally required information only.',
    'portal_hint' => 'The notice additionally appears in the customer portal.',

    'audience' => [
        'heading' => 'Recipients (:count)',
    ],

    'approved' => 'Circular approved.',
    'approved_by' => 'Approved by :name',
    'approval_pending' => 'Approval pending',

    'error' => [
        'approval_missing' => 'Sending requires approval by a second person.',
        'approval_self' => 'Whoever created the circular cannot approve it themselves.',
    ],

    'action' => [
        'approve' => 'Approve',
        'create' => 'Create circular',
        'save_draft' => 'Save as draft',
        'send' => 'Send',
        'show' => 'View',
    ],

    'column' => [
        'subject' => 'Subject',
        'status' => 'Status',
        'recipients' => 'Recipients',
        'skipped' => 'Not reached',
        'sent_at' => 'Sent on',
        'customer' => 'Customer',
        'email' => 'E-mail',
    ],

    'field' => [
        'body' => 'Text',
        'is_mandatory' => 'Mandatory notice',
        'portal_notice' => 'Show in customer portal',
    ],

    'filter' => [
        'search' => 'Search',
        'city' => 'City',
        'zip_prefix' => 'Postcode starts with',
        'zip_hint' => 'e.g. 30 for the Hanover area',
        'with_active_projects' => 'only customers with an active project',
    ],

    'status' => [
        'draft' => 'Draft',
        'sending' => 'sending',
        'sent' => 'sent',
    ],

    'recipient_status' => [
        'pending' => 'pending',
        'sent' => 'delivered',
        'skipped' => 'skipped',
        'failed' => 'failed',
    ],

    'reason' => [
        'no_email' => 'no e-mail address on file',
    ],
];
