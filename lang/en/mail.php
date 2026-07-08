<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : mail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Email intake',
    'intro' => 'Connected IMAP mailboxes are polled by the scheduler; new mails arrive as suggestions in the integration inbox and are matched to a customer — never created blindly. Processed mails are only flagged/moved, never deleted. WorkDiary is not a mail client.',
    'to_inbox' => 'Go to matching inbox',

    'mailboxes_heading' => 'Mailboxes',
    'no_connections' => 'No mailbox connected yet.',
    'add_heading' => 'Add mailbox',

    'inbox' => [
        'no_subject' => '(no subject)',
        'book_action' => 'Book as communication note',
        'book_customer_placeholder' => '… customer (blank = detected sender)',
    ],

    'dms' => [
        'action' => 'Import into the document store',
        'origin' => 'Imported from email: :subject (Message-ID :message_id)',
        'imported' => 'Imported :count attachment(s) into the document store.',
        'none' => 'No importable attachments available.',
    ],

    'encryption' => [
        'none' => 'None',
    ],

    'field' => [
        'name' => 'Label',
        'host' => 'IMAP server',
        'port' => 'Port',
        'encryption' => 'Encryption',
        'username' => 'Username',
        'password' => 'Password',
        'folder' => 'Folder',
        'processed_folder' => 'Target folder (processed)',
        'processed_folder_placeholder' => 'optional, e.g. Processed',
        'active' => 'Active',
    ],

    'action' => [
        'poll' => 'Poll now',
        'disconnect' => 'Disconnect',
        'save' => 'Save',
    ],

    'col' => [
        'host' => 'Account',
        'status' => 'Status',
        'last_polled' => 'Last polled',
    ],

    'status' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
    ],

    'flash' => [
        'saved' => 'Mailbox saved.',
        'disconnected' => 'Mailbox disconnected.',
        'polled' => 'Poll started.',
        'booked' => 'Mail recorded as a communication entry.',
        'book_failed' => 'Recording failed.',
        'password_required' => 'A new mailbox requires a password.',
        'customer_required' => 'No customer assigned.',
    ],
    'reference' => [
        'customer_number' => 'Customer number in text: :number',
        'invoice_number' => 'Invoice number in text: :number',
        'project_number' => 'Project number in text: :number',
    ],
];
