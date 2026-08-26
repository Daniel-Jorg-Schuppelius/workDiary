<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : peppol.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
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
    'health' => [
        'not_configured' => 'No access point provider credentials stored.',
        'sender_invalid' => 'The own Peppol participant ID is missing or not in the form <ICD>:<identifier>.',
        'unreachable' => 'The access point provider does not respond or rejects the access key.',
        'ok' => 'Connected to :url.',
    ],
    'field' => [
        'participant_id' => 'Peppol participant ID',
        'participant_id_hint' => 'Format <ICD>:<identifier>, e.g. 9930:DE123456789 (VAT ID) or 0204:991-12345-67 (Leitweg-ID). Empty = no Peppol delivery to this customer.',
    ],
    'action' => [
        'send' => 'Send via Peppol',
        'send_title' => 'Deliver the invoice through the access point provider — the proof of receipt is the transport receipt.',
        'check' => 'Check Peppol registration',
    ],
    'validator' => [
        'scope' => 'A subset of the Peppol BIS Billing 3.0 rules was checked (:scenario) — explicitly not a full conformance statement. The complete Schematron check is done by the KoSIT validator and the access point.',
    ],
    'error' => [
        'not_configured' => 'No Peppol access point is configured for this organisation (plugin "Peppol Access Point").',
        'sender_invalid' => 'The own Peppol participant ID is missing or invalid — it lives in the plugin settings.',
        'no_participant' => 'No Peppol participant ID is stored for :customer.',
        'invalid_participant' => 'The Peppol participant ID of :customer is invalid: :value',
        'not_registered' => 'The recipient :participant is not registered in Peppol.',
        'unsupported_document' => 'The recipient :participant does not accept the format :document over Peppol.',
        'lookup_failed' => 'The Peppol participant lookup failed: :message',
        'validation' => 'The invoice does not satisfy the checked Peppol rules: :messages',
        'transport' => 'The access point did not accept the transmission: :message',
        'not_issued' => 'Only issued invoices can be delivered over Peppol.',
        'external_billing' => 'Invoicing is owned by an external system — WorkDiary does not deliver invoices for this customer.',
        'proforma' => 'Pro forma invoices are not e-invoices and are not sent over Peppol.',
    ],
    'status' => [
        'registered' => 'Registered in Peppol (SMP :smp, :count document formats).',
        'not_registered' => 'Not registered in Peppol.',
        'checked_at' => 'Last checked: :at',
        'never_checked' => 'Not checked yet.',
    ],
    'flash' => [
        'sent' => 'Invoice handed over to :participant (message :message, transport status :status).',
        'checked' => 'Peppol check for :customer: :result',
    ],
    'inbound' => [
        'summary' => 'Peppol inbox: :fetched fetched, :imported imported, :duplicates duplicates, :unreadable unreadable.',
        'document_name' => 'peppol-:id.xml',
    ],
];
