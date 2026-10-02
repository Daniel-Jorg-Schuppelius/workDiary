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
