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
    'intro' => 'Mailboxes are polled regularly from the last position reached, including mails already read. New mails arrive as suggestions in the integration inbox, invoices in incoming invoices; processed mails are only flagged or moved, never deleted.',
    'to_inbox' => 'Go to matching inbox',
    'title_edit' => 'Edit mailbox',
    'to_inbox_count' => 'Go to matching inbox (:count)',

    'mailboxes_heading' => 'Mailboxes',
    'no_connections' => 'No mailbox connected yet.',
    'add_heading' => 'Add mailbox',

    'inbox' => [
        'invoice_action' => 'Take over as incoming invoice',
        'no_subject' => '(no subject)',
        'invoice_mailbox' => 'Invoice mailbox · :from',
        'book_action' => 'Book as communication note',
        'book_ticket_action' => 'Book as service ticket',
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
        'mailbox_address' => 'Mailbox address',
        'mailbox_address_hint' => 'Leave empty for the mailbox of the connected account. For a shared mailbox (e.g. invoices@…) the account needs full access to it.',
        'password_keep' => 'Leave empty to keep the stored password.',
        'fetch_since' => 'Fetch from',
        'fetch_since_hint' => 'Mails received before this day are not fetched. An earlier date fetches older mails.',
        'folder_hint_list' => 'Suggestions come from the mailbox; separate subfolders with “/”, e.g. Inbox/Invoices.',
        'folder_hint_error' => 'The folder list could not be loaded (:error). Enter the folder manually and separate subfolders with “/”.',
        'folder_hint_free' => 'After the first save, the dialog suggests the mailbox folders. Separate subfolders with “/”, e.g. INBOX/Invoices.',
        'einvoice_intake' => 'Invoice mailbox: take invoices from the attachments into incoming invoices',
        'einvoice_intake_hint' => 'Every PDF, XML or image file of a mail becomes an incoming invoice. Ideally choose a dedicated folder into which a rule sorts the invoices.',
        'callreport_intake' => 'Call report mailbox: take FRITZ!Box call lists (CSV) into the call list import',
        'name' => 'Label',
        'transport' => 'Transport',
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
        'create' => 'New mailbox',
        'edit' => 'Edit',
        'backfill' => 'Fetch older mails',
        'activate' => 'Activate',
        'poll' => 'Poll now',
        'disconnect' => 'Disconnect',
        'save' => 'Save',
    ],

    'col' => [
        'purpose' => 'Usage',
        'fetch_since' => 'from :date',
        'host' => 'Account',
        'status' => 'Status',
        'last_polled' => 'Last polled',
    ],

    'status' => [
        'error' => 'Error',
        'backfill_running' => 'Fetching older mails · :count processed',
        'active' => 'Active',
        'inactive' => 'Inactive',
    ],

    'flash' => [
        'invoice_imported' => 'Taken over into incoming invoices: :count.',
        'invoice_duplicate' => 'This invoice is already in incoming invoices.',
        'invoice_none' => 'None of the attachments is a PDF, XML or image file.',
        'invoice_failed' => 'Taking over into incoming invoices failed.',
        'backfill_started' => 'Backfill started. The next polls process the older mails in batches.',
        'activated' => 'Mailbox activated.',
        'chained_folders' => 'Note: a folder of this mailbox is also the fetch or processed folder of “:name”. Mails would be passed on between the entries.',
        'saved' => 'Mailbox saved.',
        'disconnected' => 'Mailbox disconnected.',
        'polled' => 'Poll started.',
        'booked' => 'Mail recorded as a communication entry.',
        'book_failed' => 'Recording failed.',
        'ticket_booked' => 'Mail booked as a service ticket.',
        'ticket_failed' => 'Ticket booking failed.',
        'dms_failed' => 'DMS import failed.',
        'already_resolved' => 'This entry has already been resolved.',
        'password_required' => 'A new mailbox requires a password.',
        'customer_required' => 'No customer assigned.',
    ],
    'transport' => [
        'imap' => 'IMAP mailbox',
    ],
    'access' => [
        'own_mailbox' => 'Mailbox of the connected account',
    ],
    'group' => [
        'mailbox' => 'Mailbox',
        'folders' => 'Folders and start date',
        'purpose' => 'Usage',
    ],
    'purpose' => [
        'invoices' => 'Invoices',
        'callreports' => 'Call lists',
        'inbox' => 'Inbox',
    ],
    'validation' => [
        'duplicate_folder' => 'There is already an entry with this folder for this account.',
    ],
    'backfill' => [
        'title' => 'Fetch older mails',
        'submit' => 'Fetch',
        'intro' => 'The next polls process all mails in the folder “:folder” from the chosen day on, including mails already read.',
        'note_folder' => 'Only this folder is fetched. For a general mailbox, create a second entry with the invoice folder.',
        'note_inbox' => 'Mails without an invoice arrive in the integration inbox as usual.',
        'note_duplicates' => 'Mails and files already taken over are recognised and not created twice.',
        'note_graph_limit' => 'Microsoft 365 returns at most 5,000 mails per pass.',
    ],
    'error' => [
        'folder_missing' => 'The folder “:folder” was not found in the mailbox.',
    ],
    'reference' => [
        'customer_number' => 'Customer number in text: :number',
        'invoice_number' => 'Invoice number in text: :number',
        'project_number' => 'Project number in text: :number',
    ],
];
