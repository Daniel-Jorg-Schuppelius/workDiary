<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : contract.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'template' => [
        'title' => 'Contract templates',
        'subtitle' => 'Templates are created from a contract via “Save as template” or from an industry profile.',
        'name' => 'Name',
        'obligations' => 'Obligations',
        'active' => 'Active',
        'edit' => 'Edit template',
        'delete' => 'Delete template',
        'confirm_delete' => 'Delete template “:name”? Existing contracts remain unchanged.',
        'empty_title' => 'No contract templates',
        'empty' => 'Open a contract and choose “Save as template”.',
        'use' => 'Template:',
        'save_title' => 'Save as template',
        'save' => 'Save template',
        'save_hint' => 'The contract type, title, term, notice, renewal, value basis, adjustment rule and obligations (due relative to the contract start) are copied. Partner, amounts and dates are not.',
        'flash' => [
            'created' => 'Template “:name” saved.',
            'updated' => 'Template saved.',
            'deleted' => 'Template deleted.',
        ],
    ],
    'cost_center' => [
        'title' => 'Contract values by cost centre',
        'subtitle' => 'Running contracts (active or terminated but not yet ended); recurring values converted to year and month, one-off values separate. Planning view, no posting.',
        'field' => 'Cost centre',
        'count' => 'Contracts',
        'yearly' => 'Yearly',
        'monthly' => 'Monthly',
        'once' => 'One-off',
        'none' => 'No cost centre',
        'empty' => 'No running contracts.',
    ],
    'extraction' => [
        'title' => 'Suggestions from “:document”',
        'check' => 'Detected details are prefilled. Please compare them with the document; they are only adopted when you save.',
        'none' => 'No contract details were detected in the document (or the text was not readable).',
        'create' => 'Contract from document',
        'leasing_create' => 'Leasing record from document',
        'field' => [
            'rate_amount' => 'Instalment',
            'payment_rhythm' => 'Payment frequency',
            'special_payment' => 'Special payment',
            'residual_value' => 'Residual value',
            'purchase_option_amount' => 'Purchase option',
            'starts_on' => 'Start',
            'ends_on' => 'End',
            'min_term_months' => 'Minimum term',
            'notice_period_days' => 'Notice period (converted to days)',
            'renew_period_months' => 'Renewal',
            'auto_renew' => 'Automatic renewal',
            'value_amount' => 'Contract value',
            'value_period' => 'Value basis',
        ],
    ],
];
