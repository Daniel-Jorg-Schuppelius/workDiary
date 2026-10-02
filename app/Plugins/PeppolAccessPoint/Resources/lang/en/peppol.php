<?php
/*
 * Created on   : Wed Sep 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : peppol.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'health' => [
        'not_configured' => 'No access point provider credentials stored.',
        'sender_invalid' => 'The own Peppol participant ID is missing or not in the form <ICD>:<identifier>.',
        'unreachable' => 'The access point provider does not respond or rejects the access key.',
        'ok' => 'Connected to :url.',
    ],
    'plugin' => [
        'description' => 'Sends and receives documents through a certified Peppol access point provider. WorkDiary does not operate an access point itself — the provider endpoints and field names are configured here.',
    ],
    'settings' => [
        'base_url' => 'Provider base URL',
        'base_url_help' => 'Root of the provider API, e.g. https://api.example-ap.eu/v1 — without a trailing slash.',
        'api_key' => 'Access key',
        'api_key_help' => 'Stored encrypted and redacted in logs.',
        'auth_header' => 'Auth header',
        'auth_header_help' => 'Header carrying the key (default: Authorization).',
        'auth_scheme' => 'Auth prefix',
        'auth_scheme_help' => 'Prefix such as Bearer. Leave empty if the provider expects the bare key.',
        'send_path' => 'Send endpoint (path)',
        'receive_path' => 'Inbox endpoint (path)',
        'ack_path' => 'Acknowledge endpoint (path)',
        'ack_path_help' => 'The {messageId} placeholder is replaced with the message identifier; without it the identifier travels in the body.',
        'health_path' => 'Status endpoint (path)',
        'payload_field' => 'Envelope field name',
        'payload_field_help' => 'JSON field holding the SBDH envelope. Leave empty if the provider expects raw XML as the body.',
        'message_id_field' => 'Message identifier field name',
        'status_field' => 'Transport status field name',
        'items_field' => 'Inbox list field name',
        'sender_participant_id' => 'Own Peppol participant ID',
        'sender_participant_id_help' => 'Format <ICD>:<identifier>, e.g. 9930:DE123456789. Must be registered with the provider for this organisation.',
        'sender_country' => 'Sender country',
        'sender_country_help' => 'Two letters (ISO 3166-1), written into the envelope as COUNTRY_C1.',
        'sml_zone' => 'SML zone',
        'sml_zone_help' => 'Production or test. The NAPTR zones are the current scheme; the CNAME zones only remain from the migration.',
        'lookup_ttl_hours' => 'Participant check validity (hours)',
        'lookup_ttl_hours_help' => 'How long an SMP result stays valid before it is resolved again. 0 = resolve every time.',
    ],
];
