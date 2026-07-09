<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : problemreport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'create' => 'Report a problem',
        'eyebrow' => 'Technical problem',
        'index' => 'My problem reports',
        'index_subtitle' => 'Your reported technical problems with reference number and status.',
        'inbox' => 'Problem reports',
        'inbox_subtitle' => 'Incoming technical problem reports — review, answer, convert to ticket.',
    ],
    'section' => [
        'what' => 'What happened?',
        'context' => 'Transmitted details',
    ],
    'field' => [
        'summary' => 'Summary',
        'description' => 'Description',
        'expected' => 'Expected behaviour',
        'actual' => 'Actual behaviour',
        'severity' => 'Severity',
        'screenshots' => 'Screenshots/attachments (max. 3)',
        'contact_ok' => 'Support may contact me about this report.',
        'contact_ok_short' => 'Contact ok',
        'include_diagnostics' => 'Include redacted diagnostic excerpt (recommended)',
        'reference' => 'Reference',
        'status' => 'Status',
        'created_at' => 'Reported at',
        'reporter' => 'Reporter',
        'diagnostics' => 'Diagnostic excerpt (redacted)',
        'delivery_error' => 'Delivery error',
        'ticket' => 'Ticket',
    ],
    'severity' => [
        'low' => 'Low',
        'normal' => 'Normal',
        'high' => 'High',
        'blocking' => 'Blocking',
    ],
    'status' => [
        'new' => 'New',
        'in_review' => 'In review',
        'answered' => 'Answered',
        'closed' => 'Closed',
    ],
    'delivery' => [
        'saas_inbox' => 'Support inbox (this system)',
        'mail' => 'Support email',
        'webhook' => 'Webhook',
        'local_export' => 'Local export',
    ],
    'action' => [
        'submit' => 'Send report',
        'open' => 'Open',
        'set_status' => 'Set status',
        'download' => 'Download as JSON',
        'convert' => 'Convert to ticket',
    ],
    'hint' => [
        'context' => 'These technical details are transmitted with your report — no order or customer data.',
        'diagnostics_always' => 'Per organisation policy a redacted diagnostic excerpt is included.',
        'diagnostics_preview' => 'View diagnostic excerpt (transmitted exactly like this)',
        'no_diagnostics' => 'No diagnostic excerpt attached (reporter decision or organisation policy).',
    ],
    'context' => [
        'route' => 'Page',
        'topic' => 'Help topic',
        'version' => 'App version',
    ],
    'empty' => [
        'title' => 'No reports',
        'message' => 'You have not reported a technical problem yet.',
        'inbox_title' => 'No problem reports',
        'inbox_message' => 'There are currently no technical problem reports.',
    ],
    'filter' => [
        'all_statuses' => 'All statuses',
    ],
    'flash' => [
        'created' => 'Thank you! Your report was filed as :reference.',
        'status_updated' => 'Status updated.',
        'converted' => 'Converted to ticket :reference.',
        'already_converted' => 'Already converted to ticket :reference.',
    ],
    'mail' => [
        'heading' => 'Problem report :reference',
        'contact_ok' => ':name agrees to follow-up questions.',
        'attachment_hint' => 'The full redacted record is attached as JSON.',
    ],
];
