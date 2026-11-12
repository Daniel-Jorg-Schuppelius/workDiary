<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : asset_compliance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Prüfwesen: Bearbeiten, Deaktivieren, Storno, Roh-Messwerte (MVP-1575).
return [
    'action' => [
        'deactivate' => 'Deactivate',
        'activate' => 'Activate',
    ],
    'state' => [
        'inactive' => 'inactive',
    ],
    'profile' => [
        'edit' => 'Edit inspection profile',
        'updated' => 'Inspection profile saved.',
        'activated' => 'Inspection profile activated.',
        'deactivated' => 'Inspection profile deactivated.',
        'deactivate_confirm' => 'Deactivate inspection profile? It is then no longer available for new assignments; existing duties and reports remain.',
        'inactive_title' => 'Deactivated organisation profiles',
        'interval_hint' => 'Applies to all duties without their own interval, from the next inspection on.',
    ],
    'requirement' => [
        'edit' => 'Edit requirement',
        'edit_hint' => 'Changed limits apply to new inspections; earlier reports keep the limits valid at the time.',
        'updated' => 'Requirement saved.',
        'activated' => 'Requirement activated.',
        'deactivated' => 'Requirement deactivated.',
    ],
    'assignment' => [
        'edit' => 'Edit inspection duty',
        'updated' => 'Inspection duty saved.',
        'activated' => 'Inspection duty resumed.',
        'deactivated' => 'Inspection duty deactivated.',
        'deactivate_confirm' => 'Deactivate inspection duty? Open inspection dates are cancelled and the overdue block of this duty ends; reports remain.',
        'interval_override' => 'Interval for this asset (months)',
        'interval_override_hint' => 'Empty = interval of the inspection profile (:months months).',
        'next_due_on' => 'Next due date',
        'next_due_hint' => 'Empty = recalculate from the last inspection when the interval changes.',
        'inactive_title' => 'Deactivated inspection duties',
        'show_active' => 'Show active',
        'show_inactive' => 'Deactivated (:count)',
        'block_released' => 'Inspection duty changed or deactivated.',
    ],
    'schedule' => [
        'cancel_title' => 'Cancel inspection date',
        'cancel_action' => 'Cancel date',
        'cancel_hint' => 'The date stays in the list with its reason (filter “Cancelled”). The inspection duty itself continues.',
        'cancelled' => 'Inspection date cancelled.',
        'none_for_status' => 'No inspection dates with this status.',
        'cancelled_by_deactivation' => 'Inspection duty deactivated.',
        'cancelled_by_decommission' => 'Asset decommissioned.',
        'error' => [
            'not_cancellable' => 'This inspection date has already been carried out or cancelled.',
        ],
    ],
    'measurement' => [
        'title' => 'Raw measurements',
        'hint' => 'Free measurement series without limit check; rows without a value are not stored.',
        'label' => 'Measuring point',
        'value' => 'Measured value',
    ],
    'protocol' => [
        'title' => 'Inspection reports (latest 20)',
        'performed_at' => 'Performed',
        'values' => 'Results and measurements',
        'out_of_limit' => 'out of limit',
        'empty' => 'No inspection documented yet.',
    ],
];
