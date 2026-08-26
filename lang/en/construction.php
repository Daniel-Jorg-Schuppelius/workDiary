<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : construction.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'VOB/B notices',
    'subtitle' => 'Obstruction notices and notices of concern with proof of delivery.',
    'empty' => 'No notices recorded.',
    'dialog_hint' => 'The facts are the core of the notice: concise, verifiable and dated. Legal references are text — WorkDiary does not provide legal advice.',
    'disclaimer' => 'Legal references are text blocks, not legal advice. Whether a deadline runs or the construction period is extended is for the contracting parties to decide.',

    'kind' => [
        'obstruction' => 'Obstruction notice',
        'concern' => 'Notice of concern',
    ],

    'legal' => [
        'obstruction' => 'Section 6(1) VOB/B',
        'concern' => 'Section 4(3) VOB/B',
    ],

    'status' => [
        'draft' => 'Draft',
        'sent' => 'Sent',
        'acknowledged' => 'Receipt confirmed',
    ],

    'column' => [
        'number' => 'Number',
        'kind' => 'Type',
        'subject' => 'Subject',
        'project' => 'Construction project',
        'occurred_on' => 'Date',
        'status' => 'Status',
    ],

    'filter' => [
        'kind' => 'Type',
        'status' => 'Status',
    ],

    'field' => [
        'site' => 'Site',
        'customer' => 'Client',
        'diary_entry' => 'Trigger (diary entry)',
        'recipient_name' => 'Recipient',
        'recipient_email' => 'Recipient e-mail',
        'facts' => 'Facts',
        'facts_hint' => 'What exactly obstructs the work or justifies the concern? Cause, affected service, point in time.',
        'impact_schedule' => 'Impact on the construction period',
        'impact_cost' => 'Impact on costs',
        'claims_time_extension' => 'Extension of time requested',
        'claims_time_extension_hint' => 'A note on the letter only — WorkDiary does not move any deadline because of it.',
        'legal_reference' => 'Legal reference',
        'legal_reference_hint' => 'Appears as text in the letter.',
        'acknowledged_note' => 'Note on receipt',
    ],

    'section' => [
        'context' => 'Assignment',
        'weather' => 'Weather on the day in question',
        'delivery' => 'Proof of delivery',
        'acknowledge' => 'Confirmation of receipt',
    ],

    'action' => [
        'edit' => 'Edit',
        'pdf' => 'PDF',
        'send' => 'Send',
        'acknowledge' => 'Confirm receipt',
    ],

    'badge' => [
        'time_extension' => 'Extension of time requested',
    ],

    'note' => [
        'time_extension' => 'Note: an extension of time has been requested. Deadlines in WorkDiary remain unchanged — an extension only takes effect once the contracting parties agree on it and it is maintained here.',
        'time_extension_short' => 'A requested extension of time is a note; WorkDiary does not move deadlines automatically.',
    ],

    'delivery' => [
        'none' => 'No proof of delivery recorded yet.',
        'method' => 'Delivery channel',
        'method_registered_mail' => 'Registered mail',
        'method_courier' => 'Courier',
        'method_handover' => 'Personal handover',
        'method_fax' => 'Fax',
        'method_portal' => 'Tender/construction portal',
        'delivered_at' => 'Delivered on',
        'recipient' => 'Recipient',
        'reference' => 'Receipt/tracking number',
        'record' => 'Record delivery',
    ],

    'mail' => [
        'title' => 'Send :label :nr by e-mail',
    ],

    'pdf' => [
        'number' => 'Number',
        'subject' => 'Subject',
        'occurred_on' => 'Date',
        'project' => 'Construction project',
        'site' => 'Site',
        'legal_reference' => 'Legal reference',
        'facts' => 'Facts',
        'impact_schedule' => 'Impact on the construction period',
        'impact_cost' => 'Impact on costs',
        'weather' => 'Weather on the day in question',
        'weather_values' => 'Readings',
        'weather_source' => 'Source',
        'time_extension' => 'Extension of time requested',
        'time_extension_text' => 'We request an extension of the execution period corresponding to the duration of the obstruction.',
        'disclaimer' => 'This letter cites the relevant provisions as a text block. It does not replace a legal review.',
    ],

    'error' => [
        'frozen' => 'A sent notice is fixed and can no longer be changed.',
    ],

    'created' => 'Notice created.',
    'updated' => 'Notice saved.',
    'deleted' => 'Draft deleted.',
    'delivery_recorded' => 'Proof of delivery recorded.',
    'acknowledged' => 'Receipt confirmed.',
];
