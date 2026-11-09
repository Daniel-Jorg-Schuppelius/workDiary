<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : incoming.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Rechnungseingang → sevDesk (Feature 163, MVP-1507).

return [
    'label' => 'sevDesk',
    'amount_differs' => 'sevDesk already has the document :number with a different amount. Please check it there and then trigger the transfer again.',
    'candidate' => 'sevDesk probably already holds the same document (:voucher), but without an invoice number this is not certain. Please link it or have a new document created.',
    'no_party' => 'The receipt has not been assigned to a party yet.',
    'contact_missing' => 'The contact :party could neither be found unambiguously nor created in sevDesk. Please link the contact in sevDesk and then trigger the transfer again.',
    'no_account' => 'No booking account is set for this direction in the sevDesk settings.',
    'currency' => 'sevDesk only receives documents in euros from invoice intake. Please record the document in sevDesk; the next transfer links it via the invoice number.',
    'rates' => 'The tax rates do not fit sevDesk (0, 7 or 19 %) or the totals are incomplete. Please record the document in sevDesk; the next transfer links it via the invoice number.',
    'tax_case' => 'Reverse charge, intra-community cases and exports are booked by accounting in sevDesk itself. Please record the document there; the next transfer links it via the invoice number.',
    'xml_requires_update' => 'sevDesk only accepts XRechnung files from bookkeeping version 2.0, and no PDF is attached to the receipt.',
    'settings' => [
        'transfer' => 'Transfer invoice intake to sevDesk',
        'transfer_help' => 'Assigned receipts go to sevDesk as drafts with the original. If a document with the same number already exists there, it is only linked.',
        'incoming_account' => 'Booking account for incoming documents (ID)',
        'outgoing_account' => 'Booking account for outgoing documents (ID)',
        'account_help' => 'ID of the account in the sevDesk chart of accounts: AccountDatev for bookkeeping 2.0, AccountingType for 1.0. Without an account the transfer waits.',
    ],
];
