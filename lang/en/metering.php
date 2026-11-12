<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : metering.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Zählerstands-Faktura (Feature 116, MVP-605).
return [
    'title' => 'Meter billing',
    'subtitle' => 'Usage billing per customer and device from the recorded readings',
    'empty' => 'No agreement recorded yet.',
    'created' => 'Agreement recorded.',
    'updated' => 'Agreement updated.',
    'draft_notice' => 'The run only creates invoice drafts — review and issuing stay manual.',
    'blocked_external' => 'An external system runs this customer’s invoicing — no document is created.',
    'run_done' => 'Billed: :created draft(s), :skipped skipped.',
    'form_hint' => 'Without an end reading in the period no draft is created, only a notice — nothing is estimated.',
    'not_runnable' => 'This agreement is not billed (status: :status). Set the status to “Active” to bill it.',
    'project_hint' => 'The draft invoices of the run are assigned to this project.',
    'status_hint' => 'The run skips paused and ended agreements. After a pause it catches up from “Next billing” — move the date forward if needed.',
    'tiers' => [
        'legend' => 'Volume tiers',
        'hint' => 'Optional. With tiers, each tier’s price applies instead of the unit price; the calculation is progressive on the consumption after deducting the free units. The first tier starts at 0, empty rows are ignored.',
        'from' => 'Tier :n from quantity',
        'price' => 'Tier :n price per unit',
        'badge' => 'Tiered (:count tiers)',
        'badge_title' => 'Volume tiers apply instead of the unit price.',
        'first_from_zero' => 'The first tier must start at 0, otherwise consumption would remain unbilled.',
        'duplicate_from' => 'Each tier needs its own “from” quantity.',
    ],
    'unit_default' => 'units',
    'action' => [
        'create' => 'Record agreement',
        'edit' => 'Edit agreement',
        'run' => 'Bill now',
    ],
    'column' => [
        'title' => 'Title',
        'customer' => 'Customer',
        'asset' => 'Device',
        'base_price' => 'Base price',
        'unit_price' => 'Unit price',
        'free_units' => 'Free units',
        'unit' => 'Unit',
        'interval' => 'Interval',
        'interval_count' => 'Factor',
        'next_run_on' => 'Next billing',
        'end_on' => 'End',
        'project' => 'Project',
        'status' => 'Status',
    ],
    'interval' => [
        'monthly' => 'monthly',
        'quarterly' => 'quarterly',
        'yearly' => 'yearly',
    ],
    'status' => [
        'active' => 'Active',
        'paused' => 'Paused',
        'ended' => 'Ended',
    ],
    'skipped' => [
        'heading' => 'Skipped billings',
        'hint' => 'Without a reading there is no invoice. Add the reading and bill again.',
        'reason' => [
            'missing_start_reading' => 'No starting reading before the period',
            'missing_end_reading' => 'No reading within the period',
            'negative_consumption' => 'Negative consumption (meter replaced?)',
            'nothing_to_bill' => 'No consumption and no base price',
        ],
    ],
    'line' => [
        'base' => ':title — base price :from to :to',
        'usage' => ':title — usage :consumption :unit, of which :free free',
        'estimated' => '(estimated reading)',
    ],
];
