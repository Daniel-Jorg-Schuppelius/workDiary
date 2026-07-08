<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : cti.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Telephony / CTI',
    'intro' => 'Incoming calls from known customers are logged as a communication entry (metadata only: direction, number, time, duration — never call content). The provider (sipgate etc.) reports calls to the webhook URL generated below. WorkDiary is not a phone system.',

    'note' => [
        'subject_inbound' => 'Incoming call from :number',
        'subject_outbound' => 'Outgoing call to :number',
    ],

    // Caller pop-up (MVP-118) — in-app notification to the employee whose
    // opt-in extension was dialled.
    'popup' => [
        'title_customer' => 'Call from :name',
        'title_unknown' => 'Call from :number',
        'message' => 'Incoming call (:number).',
        'unknown_number' => 'unknown number',
    ],

    'profile' => [
        'heading' => 'Caller pop-up',
        'extension_label' => 'My extension',
        'extension_help' => 'When someone calls this number you receive a pop-up with the caller and — if known — a link to the customer record. Leave empty for no pop-up.',
        'extension_placeholder' => 'e.g. +49 30 1234-56',
        'invalid' => 'Please enter a valid phone number.',
    ],

    'new_heading' => 'New webhook URL',
    'new_hint' => 'Enter it into the phone system/provider now — the token is shown only this once.',

    'issue_heading' => 'Issue a connection',
    'connections_heading' => 'Connections',
    'no_connections' => 'No connection issued yet.',

    'field' => [
        'name' => 'Label',
        'name_placeholder' => 'e.g. Reception sipgate',
        'provider' => 'Provider',
    ],

    'action' => [
        'issue' => 'Issue',
        'disconnect' => 'Deactivate',
    ],

    'col' => [
        'status' => 'Status',
        'last_event' => 'Last event',
    ],

    'status' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
    ],

    'flash' => [
        'issued' => 'CTI connection issued.',
        'disconnected' => 'CTI connection deactivated.',
    ],
];
