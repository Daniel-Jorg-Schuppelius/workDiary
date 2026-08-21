<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : sepa.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

return [
    'title' => 'Payment runs',
    'subtitle' => 'Bulk credit transfers and direct debits as a SEPA file',
    'empty' => 'No payment run created yet.',
    'no_items' => 'No positions in this run.',
    'run_created' => 'Payment run created.',
    'run_released' => 'Payment run released.',
    'run_cancelled' => 'Payment run cancelled.',
    'item_removed' => 'Position removed.',
    'item_adjusted' => 'Payment amount adjusted.',
    'confirm_release' => 'Release the payment run with :count positions?',
    'confirm_cancel' => 'Cancel the payment run? The invoices become payable again.',
    'released_by' => 'Released by',
    'file_hash' => 'File hash (SHA-256)',
    'execution_hint' => 'Proposed date; the bank executes on this day at the earliest.',
    'discount_used' => 'Discount :percent %',
    'adjust_hint' => 'Invoice amount: :gross. A lower payment amount requires a reason.',
    'reference' => 'Invoice :number',
    'reference_unknown' => 'Invoice without a number',
    'document_description' => 'SEPA file of payment run :id',

    'proposal' => [
        'title' => 'Payment proposal',
        'subtitle' => 'Released incoming invoices with the most economical execution date',
        'empty' => 'No open invoices released for payment.',
    ],

    'action' => [
        'proposal' => 'Payment proposal',
        'create_run' => 'Create payment run',
        'show' => 'View',
        'release' => 'Release',
        'export' => 'SEPA file',
        'cancel' => 'Cancel',
        'adjust' => 'Adjust amount',
        'remove_item' => 'Remove position',
    ],

    'column' => [
        'label' => 'Label',
        'kind' => 'Kind',
        'account' => 'Bank account',
        'execution_date' => 'Execution',
        'positions' => 'Positions',
        'total' => 'Total',
        'status' => 'Status',
        'creditor' => 'Recipient',
        'invoice_number' => 'Invoice',
        'due_date' => 'Due',
        'execute_on' => 'Pay on',
        'gross' => 'Invoice amount',
        'amount' => 'Payment amount',
        'note' => 'Note',
        'reference' => 'Remittance information',
        'deduction' => 'Deduction',
    ],

    'status' => [
        'draft' => 'Draft',
        'released' => 'released',
        'exported' => 'exported',
        'cancelled' => 'cancelled',
    ],

    'blocked' => [
        'missing_iban' => 'IBAN missing',
        'zero_amount' => 'Amount is 0',
    ],

    'error' => [
        'no_positions' => 'The payment run contains no positions.',
        'not_draft' => 'The payment run is no longer a draft.',
        'not_released' => 'The payment run has not been released.',
        'exported_final' => 'An exported payment run is no longer cancelled.',
        'invalid_amount' => 'The payment amount must be above 0 and must not exceed the invoice amount.',
        'reason_required' => 'A reduced payment amount requires a reason.',
        'zero_amount' => 'The amount must be above 0.',
        'account_without_iban' => 'No IBAN is stored for the selected bank account.',
        'missing_creditor_id' => 'No creditor identifier is stored (setting finance.sepa_creditor_id).',
        'mandate_unusable' => 'The mandate is revoked or has been unused for more than 36 months.',
        'item_without_mandate' => 'A collection position without a mandate cannot be exported.',
        'unavailable' => 'The SEPA export is not enabled in this installation. Enable it via :contact.',
    ],

    'mandate' => [
        'title' => 'SEPA mandates',
        'subtitle' => 'Customers’ direct debit mandates',
        'empty' => 'No mandate recorded yet.',
        'created' => 'Mandate created.',
        'revoked' => 'Mandate revoked.',
        'confirm_revoke' => 'Revoke the mandate? Collection is no longer permitted afterwards.',
        'not_usable' => 'not collectable',
        'reference_hint' => 'Unique per creditor; appears on the customer’s bank statement.',

        'action' => [
            'create' => 'Record mandate',
            'revoke' => 'Revoke',
        ],

        'column' => [
            'reference' => 'Mandate reference',
            'customer' => 'Customer',
            'kind' => 'Kind',
            'signed_on' => 'Signed on',
            'last_collected_on' => 'Last collection',
            'status' => 'Status',
            'iban' => 'IBAN',
            'bic' => 'BIC',
            'account_holder' => 'Account holder',
            'note' => 'Note',
        ],
    ],
];
