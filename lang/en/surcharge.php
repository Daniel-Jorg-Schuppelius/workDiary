<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : surcharge.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'rules' => 'Surcharge rules',
        'rules_subtitle' => 'Night, weekend and public-holiday surcharges per organization: time window, percentage and wage type for payroll handover.',
        'rules_help' => 'How do surcharge rules work?',
        'rules_help_text' => 'Each rule describes surcharge-eligible time (night window, Saturday, Sunday, public holiday or a custom window) with a percentage and wage type. During time export, attendances are split accordingly and reported as additional per-day export lines. When rules overlap, the highest percentage wins — surcharges are not added together.',
        'create_rule' => 'Create surcharge rule',
        'edit_rule' => 'Edit surcharge rule',
        'empty' => 'No surcharge rules yet',
        'export_summary' => 'Surcharges per employee and wage type',
    ],

    'field' => [
        'basics' => 'Basics',
        'code' => 'Code',
        'code_help' => 'Short unique key (lowercase letters, digits, ._-), e.g. "night".',
        'label' => 'Label',
        'label_placeholder' => 'e.g. Night surcharge',
        'kind' => 'Kind',
        'kind_help' => 'Night/Custom use the time window; Saturday, Sunday and public holiday apply to the whole day.',
        'window' => 'Time window',
        'window_help' => 'Only for Night/Custom. Windows across midnight (e.g. 23:00–06:00) are allowed and split correctly.',
        'window_start' => 'Window from',
        'window_end' => 'Window to',
        'whole_day' => 'whole day',
        'percentage' => 'Surcharge (%)',
        'payroll' => 'Payroll handover',
        'wage_type_code' => 'Wage type',
        'wage_type_code_help' => 'Wage type number for DATEV/Lexware (e.g. 2010). Empty = export without wage type.',
        'tax_free_limit_pct' => 'Tax-free up to (%)',
        'tax_free_limit_pct_help' => "Configurable § 3b EStG limits (e.g. night 25/40, Sunday 50, public holiday 125/150). Empty = no split. Anything above is exported as a taxable share with its own wage type.",
        'taxable_wage_type_code' => 'Wage type for taxable share',
        'taxable_wage_type_code_help' => "Required as soon as the tax-free limit is below the surcharge. The base-wage cap in € remains up to the external payroll.",
        'priority' => 'Priority',
        'priority_help' => 'Tie-breaker for equal percentages: higher priority wins.',
        'validity' => 'Validity',
        'conditions' => 'Conditions',
        'condition_teams' => 'Teams',
        'condition_sites' => 'Sites',
        'condition_shift_types' => 'Shift types',
        'conditions_help' => 'Empty = applies to everyone. Multiple conditions combine with AND; within one list a single match suffices. Sites are detected via terminal clock-ins — without determinable context a conditional rule does not apply.',
        'valid_from' => 'Valid from',
        'valid_until' => 'Valid until',
        'unlimited' => 'unlimited',
        'active' => 'Active',
        'rule_active' => 'Rule is active',
        'hours' => 'Hours',
        'yes' => 'Yes',
        'no' => 'No',
    ],

    'action' => [
        'create' => 'Create',
        'edit' => 'Edit',
        'save' => 'Save',
        'delete' => 'Delete',
        'delete_confirm' => 'Really delete this surcharge rule? Existing exports remain unchanged.',
    ],

    'flash' => [
        'created' => 'Surcharge rule created.',
        'updated' => 'Surcharge rule updated.',
        'deleted' => 'Surcharge rule deleted.',
    ],

    'validation' => [
        'taxable_wage_type_required' => "The taxable share requires its own wage type.",
    ],
];
