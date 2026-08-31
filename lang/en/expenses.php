<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : expenses.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'error' => [
        'self_approval' => 'You cannot approve your own expense.',
        'invalid_transition' => 'The action :action is not possible in status :status.',
    ],
    'receipt' => [
        'no_vendor' => 'No vendor',
        'link_title' => 'Accounting voucher',
        'link' => 'Link',
        'unlink' => 'Remove link',
        'unlink_confirm' => 'Really remove the link to the accounting voucher? The expense will count as its own cost again.',
        'suggestions_hint' => 'Vouchers with the same amount within the time window. Linking confirms it is the same transaction — the expense then stops counting twice.',
        'no_suggestions' => 'No matching voucher found',
        'no_suggestions_hint' => 'Without a link the expense is reported separately as an internal expense.',
        'no_provider' => 'No accounting system connected',
        'no_provider_hint' => 'Without a connected accounting system there are neither voucher suggestions nor a hand-over — the expense is reported separately as an internal expense.',
        'linked' => 'Voucher :number linked.',
        'unlinked' => 'Link removed.',
        'title' => 'Receipt file',
        'hint' => 'Attach the receipt to the expense — without it the expense can neither be verified on its own nor handed to accounting later.',
    ],
    'title' => [
        'index'           => 'Expenses',
        'create'          => 'Record expense',
        'edit'            => 'Edit expense',
        'inbox'           => 'Expense approval',
        'category_index'  => 'Expense categories',
        'category_create' => 'Create expense category',
        'category_edit'   => 'Edit expense category',
    ],

    'intro' => [
        'category' => 'Expense categories group receipts (e.g. meals, lodging, hospitality) and control defaults such as tax rate, required receipt upload and whether the expense is billable to customers by default. Icon and color define the appearance in lists and reports.',
    ],

    'field' => [
        'label'             => 'Label',
        'slug'              => 'Slug',
        'icon'              => 'Icon (material symbol)',
        'color'             => 'Color',
        'description'       => 'Description',
        'sort'              => 'Order',
        'is_active'         => 'Active',
        'default_tax_rate'  => 'Tax rate (default, %)',
        'requires_receipt'  => 'Receipt upload required',
        'default_billable'  => 'Billable to customer by default',
        'date'              => 'Receipt date',
        'category'          => 'Category',
        'vendor'            => 'Vendor',
        'amount_gross'      => 'Gross amount',
        'amount_net'        => 'Net amount',
        'tax_rate'          => 'Tax rate (%)',
        'tax_amount'        => 'Tax amount',
        'currency'          => 'Currency',
        'payment_method'    => 'Payment method',
        'project'           => 'Project',
        'customer'          => 'Customer',
        'task'              => 'Task',
        'billable'          => 'Billable to customer',
        'notes'             => 'Notes',
        'status'            => 'Status',
        'attachments'       => 'Receipts',
        'reimbursement_reference' => 'Reimbursement reference',
        'reject_reason'     => 'Rejection reason',
        'decided_at'        => 'Decided at',
        'reimbursed_at'     => 'Reimbursed at',
    ],

    'action' => [
        'create_category' => 'Create category',
        'create'   => 'Record expense',
        'submit'   => 'Submit for approval',
        'approve'  => 'Approve',
        'reject'   => 'Reject',
        'cancel'   => 'Cancel',
        'reimburse' => 'Mark as reimbursed',
        'export'   => 'Export CSV',
    ],

    'help' => [
        'color'          => 'Defines the accent color for icon, badge and highlights in lists.',
        'gross_first'    => 'Enter the gross amount from the receipt. Net and tax amount are calculated automatically.',
        'requires_receipt' => 'When active, at least one receipt attachment (photo/PDF) is required when recording.',
    ],

    'empty' => [
        'categories' => 'No expense categories yet.',
        'expenses'   => 'No expenses recorded yet.',
    ],

    'confirm' => [
        'delete_category' => 'Really delete this expense category?',
        'delete_expense'  => 'Really delete this expense?',
    ],
];
