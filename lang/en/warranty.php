<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : warranty.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Gewährleistungsfristen (Feature 115, MVP-604).
return [
    'title' => 'Warranty periods',
    'subtitle' => 'Own liability and claimable subcontractor periods side by side',
    'empty' => 'No warranty period recorded yet.',
    'overridden' => '(adjusted)',
    'created' => 'Warranty period recorded.',
    'closed' => 'Period closed.',
    'dialog_hint' => 'Without an explicit end date it follows from the legal basis. The period starts on the acceptance date — not the invoice or completion date.',
    'override_reason' => 'Reason for a deviating end date',
    'override_reason_hint' => 'Required as soon as the end date deviates from the legal basis.',
    'custom_needs_end' => 'A freely agreed period needs an explicit end date.',
    'end_before_start' => 'The end must be after the start.',
    'override_needs_reason' => 'An end date deviating from the rule needs a reason.',
    'not_open' => 'This period is no longer open.',
    'updated' => 'Warranty period updated.',
    'edit_hint' => 'A moved end date carries the linked open retentions along; linked guarantees report their return after the new end. An end date that deviates from the standard term needs a reason.',
    'closed_locked' => 'A closed period can no longer be changed.',
    'claim' => [
        'title' => 'Record notice of defect',
        'submit' => 'Record as notified',
        'legend' => 'Defect notified within the period',
        'hint' => 'The period gets the status “Notified” and continues unchanged until :date. Suspension or restart of the period are legal questions — enter a new end date via “Edit” if needed. While the notice is open, a linked guarantee does not report its return.',
        'case' => 'Claim case',
        'case_hint' => 'Optional: the case in which the notice is handled.',
        'unknown_case' => 'The selected claim case was not found.',
        'recorded' => 'Notice of defect recorded.',
    ],
    'action' => [
        'create' => 'Record period',
        'close' => 'Close',
        'edit' => 'Edit period',
        'claim' => 'Record notice of defect',
    ],
    'kpi' => [
        'owed' => 'Own liability',
        'owed_hint' => 'Periods we owe the client.',
        'claimable' => 'Claimable',
        'claimable_hint' => 'Periods towards subcontractors.',
        'expiring' => 'Expiring within 6 months',
        'critical' => 'Sub period ends first',
        'critical_hint' => 'Afterwards you alone are liable for a defect somebody else caused.',
    ],
    'critical' => [
        'heading' => 'Subcontractor periods end before your own liability',
        'hint' => 'Check now and give notice if in doubt — afterwards the claim against the subcontractor is gone while your own liability continues.',
    ],
    'column' => [
        'side' => 'Side',
        'project' => 'Project',
        'party' => 'Counterparty',
        'trade' => 'Trade',
        'basis' => 'Basis',
        'starts_on' => 'Start',
        'ends_on' => 'End',
        'status' => 'Status',
        'protocol' => 'Acceptance protocol',
        'customer' => 'Customer',
        'supplier' => 'Subcontractor',
        'responsible' => 'Owner',
        'note' => 'Note',
    ],
    'filter' => [
        'side' => 'Side',
        'status' => 'Status',
    ],
];
