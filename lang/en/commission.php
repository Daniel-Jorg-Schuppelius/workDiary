<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : commission.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

return [
    'title' => 'Commissions',

    'page' => [
        'rules' => 'Commission rules',
        'runs' => 'Commission settlement runs',
    ],

    'subtitle' => [
        'index' => 'Commission entries per document. The basis is the paid invoice — never the issued one.',
        'rules' => 'Rate per lead source, product group or salesperson. Exactly one rule wins per document.',
        'runs' => 'Settle a period: the draft is a preview, closing freezes it. After that only reversals.',
    ],

    'section' => [
        'unassigned' => 'Paid invoices without a commission',
        'per_user' => 'Totals per salesperson',
        'run_rows' => 'Commission entries of this run',
    ],

    'group' => [
        'rule' => 'Rule',
        'validity' => 'Validity',
        'period' => 'Period',
    ],

    'action' => [
        'create_rule' => 'Add rule',
        'edit_rule' => 'Edit rule',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'save' => 'Save',
        'show' => 'View',
        'export' => 'CSV export',
        'close' => 'Close run',
        'back' => 'Back',
        'assign' => 'Assign salesperson',
        'create_run' => 'Add settlement run',
        'to_rules' => 'Rules',
        'to_runs' => 'Settlement runs',
        'to_commissions' => 'Commission entries',
    ],

    'field' => [
        'name' => 'Name',
        'scope' => 'Scope',
        'scope_value' => 'Scope value',
        'user' => 'Salesperson',
        'rate_percent' => 'Rate',
        'priority' => 'Priority',
        'valid_from' => 'Valid from',
        'valid_to' => 'Valid to',
        'validity' => 'Validity',
        'is_active' => 'Active',
        'note' => 'Note',
        'status' => 'Status',
        'invoice' => 'Document',
        'customer' => 'Customer',
        'earned_on' => 'Effective date',
        'base_amount' => 'Base amount',
        'commission_amount' => 'Commission',
        'run' => 'Run',
        'period' => 'Period',
        'period_start' => 'Period from',
        'period_end' => 'Period to',
        'currency' => 'Currency',
        'entry_count' => 'Entries',
        'total_base' => 'Total base',
        'total_commission' => 'Total commission',
        'closed_by' => 'Closed by',
        'paid_on' => 'Paid on',
    ],

    'scope' => [
        'all' => 'All documents',
        'lead_source' => 'Lead source',
        'product_group' => 'Product group',
        'user' => 'Salesperson',
    ],

    'status' => [
        'pending' => 'Open',
        'settled' => 'Settled',
        'reversed' => 'Reversed',
    ],

    'run_status' => [
        'draft' => 'Draft',
        'closed' => 'Closed',
    ],

    'assignment' => [
        'lead' => 'From the lead pipeline',
        'manual' => 'Assigned manually',
    ],

    'badge' => [
        'reversal' => 'Reversal',
    ],

    'empty' => [
        'rules' => 'No commission rule defined yet.',
        'commissions' => 'No commission entry yet.',
        'runs' => 'No settlement run created yet.',
        'run_rows' => 'No commission entries in this period.',
    ],

    'hint' => [
        'scope_value' => 'Only for scope lead source or product group; it must match the selected scope.',
        'user' => 'Only for scope salesperson.',
        'priority' => 'The higher number wins; on a tie the narrower scope decides.',
        'period' => 'Readable label, e.g. 2026-08. Empty = derived from the start date.',
        'currency' => 'A run settles exactly one currency — commissions are never converted.',
        'assign' => 'Leave empty to fall back to the origin from the lead pipeline.',
        'current_assignment' => 'Currently responsible: :user (:source).',
        'no_assignment' => 'Nobody is responsible right now — without an assignment no commission is created.',
        'unassigned' => 'These invoices are paid but assigned to nobody: neither manually nor via a converted lead.',
        'draft_preview' => 'Draft: entries are recomputed on every visit. Only closing freezes them.',
        'no_payout' => 'WorkDiary calculates and exports the commission — the payout happens in payroll.',
    ],

    'confirm' => [
        'delete_rule' => 'Delete commission rule? Commissions already calculated stay untouched.',
        'delete_run' => 'Delete the draft settlement run?',
        'close_run' => 'Close the run? It is frozen afterwards; corrections only run through a reversal.',
    ],

    'flash' => [
        'rule_created' => 'Commission rule created.',
        'rule_updated' => 'Commission rule saved.',
        'rule_deleted' => 'Commission rule deleted.',
        'assigned' => 'Assignment saved.',
        'run_created' => 'Settlement run created.',
        'run_closed' => 'Settlement run closed and frozen.',
        'run_deleted' => 'Settlement run deleted.',
    ],

    'error' => [
        'period_reversed' => 'The end of the period is before its start.',
        'period_overlap' => 'A settlement run already exists for this period.',
        'already_closed' => 'This settlement run is already closed.',
    ],

    'note' => [
        'credit_note' => 'Reversal due to credit note :number',
        'cancelled' => 'Reversal due to cancellation',
        'reassigned' => 'Reversal due to reassignment of the salesperson',
    ],

    'export' => [
        'period' => 'Period',
        'user' => 'Salesperson',
        'invoice' => 'Document',
        'customer' => 'Customer',
        'earned_on' => 'Effective date',
        'currency' => 'Currency',
        'base' => 'Base amount',
        'rate' => 'Rate in percent',
        'commission' => 'Commission',
        'kind' => 'Kind',
        'note' => 'Note',
        'reversal' => 'Reversal',
        'regular' => 'Commission',
    ],
];
