<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : damage.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Schadensfälle (MVP-919/920).
return [
    'title' => 'Damage cases',
    'subtitle' => 'Insurance and damage cases for rentals, leasing, claims and vehicles — with settlement, deductible and history.',
    'case' => 'Damage case',
    'empty' => 'No damage cases — they are created on the record (rental, leasing, claim, vehicle).',
    'nav' => [
        'section' => 'Damage & recalls',
        'cases' => 'Damage cases',
    ],
    'kpi' => [
        'open' => 'Open cases',
        'open_estimate' => 'Estimated (open)',
        'settled' => 'Settled',
    ],
    'filter' => [
        'all_status' => 'All statuses',
        'all_subjects' => 'All records',
        'all_kinds' => 'All kinds',
    ],
    'field' => [
        'number' => 'Number',
        'title' => 'Title',
        'subject' => 'Related record',
        'kind' => 'Kind of damage',
        'status' => 'Status',
        'estimated_amount' => 'Estimated damage',
        'settled_amount' => 'Settled amount',
        'deductible_amount' => 'Deductible',
        'net_recovery' => 'Recovery after deductible',
        'currency' => 'Currency',
        'occurred_at' => 'Time of damage',
        'reported_at' => 'Reported on',
        'insurer_name' => 'Insurer',
        'policy_number' => 'Policy number',
        'claim_number' => 'Claim number',
        'responsible_user_id' => 'Responsible',
        'description' => 'Description of events',
    ],
    'section' => [
        'case' => 'Damage case',
        'insurance' => 'Insurance',
        'amounts' => 'Amounts',
        'status' => 'Change status',
        'journal' => 'History',
    ],
    'action' => [
        'show' => 'Show',
        'edit' => 'Edit',
        'save' => 'Save',
        'open' => 'Create damage case',
        'report' => 'Report damage',
    ],
    'dialog' => [
        'create' => 'Report damage',
        'edit' => 'Edit damage case',
    ],
    'card' => [
        'title' => 'Damage cases',
        'none' => 'No damage cases.',
    ],
    'status' => [
        'reported' => 'Reported',
        'submitted' => 'Submitted to insurer',
        'in_review' => 'Under review',
        'settled' => 'Settled',
        'rejected' => 'Rejected',
        'closed' => 'Closed',
    ],
    'transition' => [
        'submitted' => 'Submit to insurer',
        'in_review' => 'Mark as under review',
        'settled' => 'Record settlement',
        'rejected' => 'Record rejection',
        'closed' => 'Close',
    ],
    'kind' => [
        'property' => 'Property damage',
        'liability' => 'Liability',
        'theft' => 'Theft/loss',
        'vehicle' => 'Vehicle damage',
        'transport' => 'Transport damage',
        'other' => 'Other',
    ],
    'error' => [
        'settled_amount_required' => 'The settled amount is required for “settled”.',
    ],
    'flash' => [
        'opened' => 'Damage case :number created.',
        'saved' => 'Damage case saved.',
        'status' => 'Status: :status.',
    ],
    'subject_type' => [
        'rental_cases' => 'Rental',
        'asset_finance_contracts' => 'Leasing/financing contract',
        'claim_cases' => 'Claim',
        'vehicles' => 'Vehicle',
    ],
];
