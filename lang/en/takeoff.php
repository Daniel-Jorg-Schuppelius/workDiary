<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : takeoff.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Aufmaßblatt (MVP-1058).
return [
    'title' => 'Quantity survey',
    'back' => 'Back',
    'default_title' => 'Quantity survey :carrier',
    'lines' => 'Survey lines',
    'totals' => 'Quantities per item',
    'photos' => 'Photos and sketches',
    'values' => 'Values',
    'empty' => 'No lines yet — add a formula via “Add line”.',
    'no_target' => '— not assigned —',
    'pdf_note' => 'Calculated with the formulas of REB-VB 23.003. Values in metres, angles in gon (full circle = 400).',
    'formula' => [
        'Sum' => 'Count / sum',
        'Triangle' => 'Triangle',
        'Rectangle' => 'Rectangle / cuboid',
        'Trapezoid' => 'Trapezoid',
        'Circle' => 'Circle / sector',
        'Mean' => 'Mean',
        'Free' => 'Free formula',
    ],
    'value' => [
        'amount' => 'Value',
        'base' => 'Base',
        'height' => 'Height',
        'depth' => 'Depth / height (solid, optional)',
        'length' => 'Length',
        'width' => 'Width',
        'side_a' => 'Side a',
        'side_c' => 'Side c',
        'radius' => 'Radius',
        'angle' => 'Angle in gon (400 = full circle)',
        'expression' => 'Expression',
    ],
    'hint' => [
        'Sum' => 'Values are added; a negative value subtracts.',
        'Triangle' => 'Base × height ÷ 2; with depth as a solid.',
        'Rectangle' => 'Length × width; with depth/height as a solid.',
        'Trapezoid' => '(a + c) ÷ 2 × height; with depth as a solid.',
        'Circle' => 'Radius² × π × angle ÷ 400; 400 gon is the full circle.',
        'Mean' => 'Arithmetic mean of the values.',
        'Free' => 'Expression with + − × ÷ and brackets, decimal comma or point.',
        'factor' => 'Number of identical parts; negative subtracts (e.g. −1 for a door).',
        'label' => 'Room, component or axis.',
        'unit' => 'Empty = unit of the bill item or article.',
    ],
    'col' => [
        'label' => 'Room / component',
        'formula' => 'Formula',
        'values' => 'Values',
        'factor' => 'Factor',
        'quantity' => 'Quantity',
        'target' => 'Item',
    ],
    'field' => [
        'title' => 'Name',
        'measured_on' => 'Measured on',
        'note' => 'Remark',
        'boq_item' => 'Bill item',
        'article' => 'Article / service',
        'description' => 'Description (without article)',
        'unit' => 'Unit',
    ],
    'action' => [
        'create' => 'New quantity survey',
        'edit' => 'Edit',
        'pdf' => 'PDF',
        'delete' => 'Delete',
        'add_line' => 'Add line',
    ],
    'transition' => [
        'completed' => 'Complete',
        'draft' => 'Reopen',
    ],
    'confirm' => [
        'completed' => 'Complete the survey? Lines are then locked and the quantities can be transferred.',
        'draft' => 'Reopen the survey? Quantities already transferred stay unchanged.',
        'delete' => 'Delete the survey with all lines?',
        'delete_line' => 'Really remove this line?',
    ],
    'flash' => [
        'created' => 'Quantity survey created.',
        'saved' => 'Quantity survey saved.',
        'deleted' => 'Quantity survey deleted.',
        'status' => 'Status changed.',
        'line_saved' => 'Line saved.',
        'line_deleted' => 'Line removed.',
    ],
    'error' => [
        'locked' => 'The survey is completed and can no longer be changed.',
        'not_computable' => 'The formula cannot be calculated with these values — check the required values or the expression.',
        'not_found' => 'Quantity survey not found.',
    ],
    'carrier' => [
        'section' => 'Quantity surveys',
        'lines' => ':count line|:count lines',
        'none' => 'No quantity survey yet.',
    ],
    'transfer' => [
        'title' => 'Transfer quantities',
        'action' => 'Transfer',
        'confirm' => [
            'quote' => 'Transfer the quantities into a new quote?',
            'invoice' => 'Transfer the quantities into a draft invoice? The takeoff PDF is attached as a document.',
            'progress' => 'Report the quantities as progress of the bill of quantities items?',
        ],
        'targets' => 'Transferred to',
        'kind' => [
            'quote' => 'As quote',
            'invoice' => 'As draft invoice',
            'progress' => 'As bill progress',
        ],
        'hint' => 'Each kind once per survey; items without a price get 0 € and are completed in the document.',
        'done' => 'Transferred',
        'based_on' => 'Quantities according to survey “:title”.',
        'document_title' => 'Quantity survey :title',
        'progress_note' => 'From survey “:title”',
        'flash' => [
            'quote' => 'Quote created from the survey.',
            'invoice' => 'Draft invoice created from the survey; the survey PDF is attached as a document.',
            'progress' => ':count bill items reported.',
        ],
        'error' => [
            'not_completed' => 'Complete the survey first.',
            'already' => 'Already transferred: :kind.',
            'no_customer' => 'No customer is linked to the order or project.',
            'empty' => 'The survey has no quantities.',
            'no_boq' => 'No line is assigned to a bill item.',
        ],
    ],
    'chain' => [
        'label' => 'Takeoffs without a document',
        'measured_on' => 'Measured on :date',
    ],
    'presets' => [
        'label' => 'Templates',
    ],
    'quick' => [
        'title' => 'Quick entry',
        'hint' => 'Works offline too — the line is sent with the next connection.',
        'photo' => 'Photo',
        'add' => 'Record line',
    ],
];
