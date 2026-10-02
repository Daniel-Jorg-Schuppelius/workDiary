<?php
/*
 * Created on   : Sat Oct 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ebics.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// EBICS-Bankzugang (MVP-124).
return [
    'title' => 'EBICS bank access',
    'section' => [
        'access' => 'Bank access details',
        'steps' => 'Setup',
        'journal' => 'History',
    ],
    'field' => [
        'host_url' => 'Bank EBICS URL',
        'ebics_host' => 'Host ID',
        'ebics_partner' => 'Customer ID (partner ID)',
        'ebics_user' => 'Subscriber ID (user ID)',
    ],
    'hint' => [
        'host_url' => 'Stated together with host, customer and subscriber ID in the bank’s EBICS access letter (EBICS 3.0).',
        'active' => 'The nightly run fetches statements; fetched up to :date.',
    ],
    'step' => [
        'keys' => 'Create keys (signature, authentication, encryption).',
        'initialize' => 'Send public keys to the bank (INI and HIA).',
        'letter' => 'Print the initialisation letter, sign it and send it to the bank.',
        'activate' => 'After activation by the bank: fetch the bank keys.',
    ],
    'action' => [
        'save' => 'Save',
        'keys' => 'Create keys',
        'initialize' => 'Send to bank',
        'letter' => 'Download letter',
        'activate' => 'Fetch bank keys',
        'fetch' => 'Fetch statements now',
        'suspend' => 'Suspend access',
        'submit' => 'Submit via EBICS',
    ],
    'confirm' => [
        'suspend' => 'Suspend the access at the bank? New keys and a new letter will be needed afterwards.',
        'submit' => 'Send this payment run to the bank via EBICS now? You authorise it at the bank afterwards.',
    ],
    'last_error' => 'Last error: :error',
    'flash' => [
        'saved' => 'Access details saved.',
        'keys_created' => 'Keys created.',
        'initialized' => 'Keys sent to the bank. Please sign and submit the initialisation letter.',
        'activated' => 'Bank keys fetched — the access is activated.',
        'suspended' => 'Access suspended.',
        'fetched' => ':statements statements imported, :skipped already present.',
        'submitted' => 'Payment run submitted (order :order). Please authorise it at the bank.',
    ],
    'error' => [
        'host_not_allowed' => 'This address is not allowed as bank access.',
        'locked_after_keys' => 'Once keys are created the access details can no longer be changed — suspend the access first.',
        'invalid_step' => 'This step does not match the current setup state.',
        'not_initialized' => 'The letter is available only after the keys have been sent to the bank.',
        'not_active' => 'The EBICS access is not activated.',
        'no_keys' => 'No keys exist for this access.',
        'no_data' => 'The bank has no new data available.',
        'bank_rejected' => 'The bank rejected the order.',
        'failed' => 'The connection to the bank failed.',
        'already_submitted' => 'This payment run has already been submitted via EBICS.',
    ],
    'letter' => [
        'title' => 'EBICS initialisation letter (INI/HIA)',
        'sent_at' => 'Sent on',
        'key' => [
            'A' => 'Bank-technical key (signature)',
            'X' => 'Authentication key',
            'E' => 'Encryption key',
        ],
        'hash' => 'Hash value (SHA-256):',
        'certificate' => 'Certificate issued on :date',
        'confirmation' => 'I hereby confirm that the keys above have been transmitted to the bank.',
        'place_date' => 'Place, date',
        'signature' => 'Signature of the subscriber',
        'printed_at' => 'Created on :date',
    ],
    'run' => [
        'submitted' => 'Submitted via EBICS on :date (order :order).',
    ],
];
