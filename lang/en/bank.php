<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : bank.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'menu' => 'Payment reconciliation',
        'index' => 'Bank statements',
        'statement' => 'Bank statement',
        'transactions' => 'Bank transactions',
        'suggestions' => 'Allocation suggestions',
        'allocations' => 'Confirmed allocations',
        'accounts' => 'Bank accounts',
        'account' => 'Bank account',
    ],
    'subtitle' => [
        'index' => 'Import bank statements (CAMT.053/MT940), review transactions and allocate them to open invoices or expenses.',
        'accounts' => 'The organisation’s own bank accounts for auto-matching incoming statements.',
    ],
    'field' => [
        'format' => 'Format',
        'imported_at' => 'Imported at',
        'imported_by' => 'Imported by',
        'account' => 'Bank account',
        'period' => 'Period',
        'opening_balance' => 'Opening balance',
        'closing_balance' => 'Closing balance',
        'balance_check' => 'Balance chain',
        'tx_count' => 'Transactions',
        'open' => 'Open',
        'matched' => 'Allocated',
        'booking_date' => 'Booking',
        'valuta_date' => 'Value date',
        'amount' => 'Amount',
        'direction' => 'Direction',
        'currency' => 'Currency',
        'counterparty' => 'Counterparty',
        'purpose' => 'Remittance info',
        'reference' => 'Reference',
        'status' => 'Status',
        'score' => 'Score',
        'kind' => 'Kind',
        'note' => 'Note',
        'label' => 'Label',
        'iban' => 'IBAN',
        'bic' => 'BIC',
        'account_holder' => 'Account holder',
        'datev_account_no' => 'DATEV account no.',
        'is_active' => 'Active',
    ],
    'reason' => [
        'reference' => 'Invoice number',
        'amount' => 'Amount matches',
        'skonto' => 'Cash discount',
        'iban' => 'IBAN match',
        'date' => 'Date proximity',
        'foreign_currency' => 'Foreign currency – review manually',
    ],
    'action' => [
        'import' => 'Import bank file',
        'upload' => 'Import',
        'show' => 'Show',
        'download' => 'Download original file',
        'confirm' => 'Confirm',
        'ignore' => 'Set aside',
        'unassignable' => 'Unassignable',
        'unmatch' => 'Undo allocation',
        'manual' => 'Allocate manually',
        'new_account' => 'Add bank account',
        'edit_account' => 'Edit bank account',
        'delete_account' => 'Delete bank account',
        'manage_accounts' => 'Manage bank accounts',
    ],
    'import' => [
        'dialog_title' => 'Import bank file',
        'dialog_hint' => 'CAMT.053 (XML) or MT940. The import only creates transactions in the review area and does not change any invoice or expense status.',
        'file' => 'File',
        'account_optional' => 'Bank account (optional, otherwise auto-matched via IBAN)',
        'flash' => [
            'imported' => ':count transactions imported.',
        ],
        'error' => [
            'empty' => 'The statement contains no transactions.',
            'empty_file' => 'The file is empty.',
            'duplicate_file' => 'This file has already been imported (duplicate).',
            'unavailable' => 'Bank import is an optional, paid add-on module and is not enabled in this installation. It can be unlocked on request at :contact.',
        ],
    ],
    'reconcile' => [
        'flash' => [
            'confirmed' => 'Allocation confirmed.',
            'ignored' => 'Transaction set aside.',
            'unassignable' => 'Transaction marked as unassignable.',
            'unmatched' => 'Allocation undone.',
        ],
        'error' => [
            'no_allocations' => 'No allocation was provided.',
            'target_not_found' => 'The allocation target was not found.',
        ],
    ],
    'account' => [
        'flash' => [
            'created' => 'Bank account created.',
            'updated' => 'Bank account updated.',
            'deleted' => 'Bank account deleted.',
        ],
        'error' => [
            'duplicate_iban' => 'A bank account already exists for this IBAN.',
        ],
    ],
    'empty' => [
        'statements' => 'No bank statements imported yet.',
        'transactions' => 'No transactions in this statement.',
        'suggestions' => 'No suggestions – allocate manually or set aside.',
        'accounts' => 'No bank accounts created yet.',
    ],
];
