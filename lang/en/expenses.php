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
