<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : finance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'module' => 'Finance interface',
        'transfers' => 'Transfer receipts',
        'transfer' => 'Transfer receipt',
        'menu' => 'Invoicing handover',
        'positions' => 'Resulting positions',
        'sources' => 'Individual sources (snapshot)',
        'events' => 'Event log',
    ],

    'subtitle' => [
        'transfers' => 'Hand over billable time and materials to the leading invoicing system in separate channels.',
    ],

    'field' => [
        'billing_mode' => 'Billing channel',
        'billing_mode_inherit' => '— Inherit organisation default —',
        'billing_mode_default' => '— WorkDiary (default) —',
        'billing_mode_hint' => 'Overrides the organisation default for this customer. With Lexoffice/DATEV, local invoicing is locked.',
        'billing_mode_org_hint' => 'Default billing channel of the organisation. Customers can override it individually.',
        'channel' => 'Transfer channel',
        'target' => 'Transfer target',
        'status' => 'Status',
        'period' => 'Service period',
        'position_count' => 'Positions',
        'total_amount' => 'Total amount (net)',
        'total_quantity' => 'Total quantity',
        'payload_hash' => 'Payload hash',
        'transferred_at' => 'Transferred at',
        'failure_reason' => 'Failure reason',
        'customer' => 'Customer',
        'source' => 'Source',
        'source_deleted' => 'Source no longer available',
    ],

    'action' => [
        'create_draft' => 'Prepare transfer',
        'confirm' => 'Confirm transfer',
        'mark_transferred' => 'Mark as transferred',
        'mark_failed' => 'Mark as failed',
        'void' => 'Void transfer',
        'show' => 'Show',
        'execute' => 'Transfer now',
        'retry' => 'Retry',
        'download' => 'Download handover package',
        'open_external' => 'Open externally',
    ],

    'filter' => [
        'all' => 'All',
    ],

    'hint' => [
        'channels_separate' => 'Time and material are confirmed as separate handover packages.',
        'datev_desktop_api' => 'DATEV leads: handover as a file package (CSV) — the DATEV desktop API will follow as a separate adapter.',
        'target_by_mode' => 'The target is preset from the customer\'s billing channel.',
        'period_sources' => 'Only billable sources that have not yet been invoiced/handed over within the period are collected.',
        'lexoffice_draft_created' => 'Invoice draft created in Lexoffice:',
    ],

    'confirm_execute' => 'Transfer to the target now? On success the sources will be marked as handed over.',
    'confirm_void' => 'Void this transfer? The sources will be released again.',

    'empty_title' => 'No transfer receipts',
    'empty_message' => 'No transfers have been prepared yet.',
    'empty_filtered' => 'No transfers match the selected filters.',
    'empty_positions_title' => 'No positions',
    'empty_positions' => 'The sources do not produce any positions (e.g. sources deleted).',

    'csv' => [
        'package_title' => 'WorkDiary handover package (CSV) — not a DATEV format',
        'position' => 'Position',
        'date' => 'Date',
        'employee' => 'Employee',
        'project' => 'Project/Order',
        'activity' => 'Activity',
        'hours' => 'Hours',
        'rate' => 'Rate',
        'amount' => 'Amount',
        'comment' => 'Comment',
        'product' => 'Product',
        'quantity' => 'Quantity',
        'unit' => 'Unit',
        'unit_price_net' => 'Unit price (net)',
        'total' => 'Total',
    ],

    'lexoffice' => [
        'introduction' => 'Handover from WorkDiary — :channel, period :from – :to.',
    ],

    'flash' => [
        'created' => 'Transfer receipt draft created.',
        'confirmed' => 'Transfer confirmed.',
        'transferred' => 'Transfer completed — sources have been marked as transferred.',
        'failed' => 'Transfer marked as failed.',
        'voided' => 'Transfer voided — sources have been released again.',
    ],

    'error' => [
        'local_invoicing_locked' => 'Invoicing is led by :program; local invoice creation is locked.',
        'no_sources' => 'No transferable sources found in the selected period.',
        'illegal_transition' => 'Status transition from ":from" to ":to" is not allowed.',
        'void_after_transfer' => 'A transfer that has already been delivered cannot be voided — please use a cancellation/difference transfer.',
        'entry_already_transferred' => 'The time entry has already been handed over to invoicing and can no longer be corrected.',
        'target_not_allowed' => 'This target is not allowed for the billing channel ":mode".',
        'lexoffice_not_configured' => 'Lexoffice is not configured for this organisation (API key missing).',
        'sources_missing' => 'The sources of this transfer receipt are no longer fully available.',
    ],
];
