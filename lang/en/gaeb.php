<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : gaeb.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Bills of quantities',
    'subtitle' => 'Import GAEB bills of quantities and track line items',
    'empty' => 'No bills of quantities imported yet.',
    'import_button' => 'Import GAEB file',

    'columns' => [
        'name' => 'Name',
        'project' => 'Project',
        'phase' => 'Phase',
        'version' => 'GAEB version',
        'items' => 'Items',
        'reference_no' => 'Ref.',
        'short_text' => 'Short text',
        'quantity' => 'Quantity',
        'unit' => 'Unit',
        'unit_price' => 'UP',
        'total_price' => 'Total',
        'type' => 'Type',
        'status' => 'Status',
        'executed' => 'Measured',
        'remaining' => 'Remaining',
    ],

    'import' => [
        'title' => 'Import GAEB file',
        'file' => 'GAEB DA XML file',
        'file_hint' => 'GAEB DA XML 3.x (e.g. .x81, .x83, .x86 or .xml).',
        'project' => 'Project (optional)',
        'project_none' => '— no project —',
        'name' => 'Name (optional)',
        'name_hint' => 'Overrides the project name from the file.',
        'submit' => 'Import',
        'status' => [
            'pending' => 'Checking',
            'preflight_failed' => 'Preflight failed',
            'imported' => 'Imported',
            'conflict' => 'Conflict',
        ],
        'change_order_status' => [
            'Recog' => 'Recognised',
            'Filed' => 'Filed',
            'Offered' => 'Offered',
            'Withdrawn' => 'Withdrawn',
            'Rejected' => 'Rejected',
            'ObjToRecj' => 'Objection to rejection',
            'FormAckn' => 'Formally acknowledged',
            'Approved' => 'Approved',
        ],
    ],

    'show' => [
        'positions' => 'Line items',
        'history' => 'Import history',
        'no_imports' => 'No import runs logged.',
        'imported_at' => 'Imported at',
        'back' => 'Back to overview',
    ],

    'phase' => [
        '31' => 'Quantity survey',
        '50' => 'Construction cost catalogue',
        '51' => 'Cost determination',
        '52' => 'Calculation data',
        '80' => 'Universal bill of quantity data',
        '81' => 'Bill of quantities',
        '82' => 'Cost assumption',
        '83' => 'Request for bid',
        '84' => 'Bid submission',
        '85' => 'Side bid',
        '86' => 'Award',
        '87' => 'Award confirmation',
        '89' => 'Invoice',
        '89B' => 'Invoice supporting document',
        '83Z' => 'Framework contract: request for bid',
        '84Z' => 'Framework contract: bid submission',
        '86ZE' => 'Framework contract: call-off order',
        '86ZR' => 'Framework contract: master order',
        '93' => 'Price inquiry',
        '94' => 'Price offer',
        '96' => 'Order',
        '97' => 'Order confirmation (trade)',
    ],

    'item' => [
        'type' => [
            'standard' => 'Standard item',
            'base' => 'Base item',
            'alternative' => 'Alternative item',
            'optional' => 'Provisional item',
            'lump_sum' => 'Lump-sum item',
            'markup' => 'Markup item',
            'note' => 'Note',
        ],
        'status' => [
            'draft' => 'Draft',
            'imported' => 'Imported',
            'quoted' => 'Quoted',
            'ordered' => 'Ordered',
            'in_progress' => 'In progress',
            'completed' => 'Completed',
            'replaced' => 'Replaced',
            'cancelled' => 'Cancelled',
        ],
    ],

    'preflight' => [
        'version_unknown' => 'GAEB version could not be detected.',
        'version_unsupported' => 'GAEB version :version is not supported (target line 3.3).',
        'phase_unknown' => 'Exchange phase ":code" is unknown.',
        'no_items' => 'The file contains no line items.',
        'item_missing_ref' => 'Item without reference number: :text',
        'duplicate_ref' => 'Reference number :ref appears more than once.',
        'missing_quantity' => 'Item :ref has no quantity.',
        'non_positive_quantity' => 'Item :ref has a quantity ≤ 0.',
        'missing_unit' => 'Item :ref has no unit.',
        'missing_price' => 'Item :ref has no unit price in a price-bearing phase.',
        'unpriced_item' => 'Item :ref carries neither a price nor a "not offered" mark in the bid.',
        'priced_but_not_offered' => 'Item :ref is marked as "not offered" but carries a unit price.',
        'up_components_mismatch' => 'Item :ref: the unit price components (:sum) do not add up to the unit price (:price).',
        'missing_text' => 'Item :ref has no short/long text.',
        'total_mismatch' => 'The stated total (:stated) differs from the computed total (:computed).',
        'complement_empty' => 'Item :ref: bidder text complement :mark has not been filled in.',
        'contractor_missing' => 'This phase requires the bidder address (name, street, postal code and city in the e-invoicing master data).',
    ],

    'flash' => [
        'imported' => 'Bill of quantities imported with :items line items.',
        'preflight_failed' => 'Import aborted: :count preflight errors. No line items were written.',
        'conflict' => 'Reimport aborted: items with execution reference (:refs) would be overwritten.',
    ],

    'progress' => [
        'from_takeoff' => 'Quantity recomputed from :lines survey lines of the X31.',
        'takeoff_skipped' => ':count lines with an unsupported formula were left out.',
        'title' => 'Measurement / progress',
        'record' => 'Record measurement',
        'quantity' => 'Quantity',
        'note' => 'Note',
        'source' => [
            'manual' => 'Manual',
            'measurement' => 'Measurement',
            'protocol' => 'Protocol',
            'material' => 'Material usage',
        ],
        'flash' => [
            'recorded' => 'Measurement recorded.',
        ],
    ],

    'mapping' => [
        'title' => 'Link',
        'add' => 'Link',
        'target_type' => 'Target type',
        'article' => 'Article',
        'material' => 'Material',
        'factor' => 'Factor',
        'flash' => [
            'linked' => 'Item linked.',
        ],
    ],

    'workflow' => [
        'status' => 'Set status',
        'add_addendum' => 'Add addendum',
        'remaining_title' => 'Remaining work',
        'no_remaining' => 'No open remaining work.',
        'flash' => [
            'item_updated' => 'Item status changed.',
            'bill_updated' => 'Bill status changed.',
            'addendum_added' => 'Addendum added.',
        ],
    ],

    'costing' => [
        'title' => 'Cost tracking',
        'planned' => 'Planned',
        'executed' => 'Actual (measured)',
        'remaining' => 'Remaining',
        'progress' => 'Progress',
    ],

    'export' => [
        'button' => 'Export GAEB',
        'title' => 'GAEB export',
        'phase' => 'Phase',
    ],
];
