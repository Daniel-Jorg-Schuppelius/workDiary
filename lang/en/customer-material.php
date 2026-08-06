<?php
/*
 * Created on   : Wed Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : customer-material.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Material cost allocation & profit (revenue − material costs) on the customer file.
return [
    'panel_title' => 'Material costs & profit',
    'add_title' => 'Allocate material costs',
    'source' => 'Cost source',
    'source_hint' => 'Pick a Lexoffice purchase document or enter a free amount.',
    'voucher' => 'Purchase document',
    'voucher_hint' => 'Optional — a partial amount is possible; one document can be split across customers.',
    'manual_amount' => '— Free amount —',
    'description' => 'Description',
    'description_hint' => 'Required without a document — labels the material cost.',
    'allocation' => 'Allocation',
    'amount' => 'Amount',
    'amount_hint' => 'Amount allocated to the customer.',
    'date' => 'Date',
    'project' => 'Project',
    'project_hint' => 'Optional — for finer allocation.',
    'no_project' => '— No project —',
    'source_lexoffice' => 'Lexoffice document',
    'revenue' => 'Revenue (invoiced)',
    'material_cost' => 'Material costs',
    'profit' => 'Profit (calc.)',
    'margin' => 'margin',
    'range_hint' => 'Values in the selected period (:range).',
    'double_count_hint' => 'Management view (excl. overhead). Allocate material either via a purchase document OR a stock issue — not both for the same goods.',
    'empty_hint' => 'No material costs allocated yet. Use “Allocate material costs” to assign documents or free amounts to show profit.',
    'confirm_delete' => 'Really remove this material cost allocation?',
    'delete' => 'Remove',
    'flash_saved' => 'Material costs allocated.',
    'flash_deleted' => 'Material cost allocation removed.',
    'error_description_required' => 'Please provide a description when no document is selected.',
    'error_voucher_not_purchase' => 'The selected document is not a purchase document.',
    'error_amount_over_voucher' => 'The amount exceeds the document total.',
    'error_project_foreign' => 'The project does not belong to this customer.',
    // Stock issue -> material cost
    'stock_title' => 'Issue from stock',
    'stock_issue' => 'Issue & book',
    'stock_source' => 'Stock issue',
    'stock_hint' => 'Valued at moving average; the issue reduces stock and is booked as material cost.',
    'article' => 'Article',
    'warehouse' => 'Warehouse',
    'qty' => 'Quantity',
    'qty_hint' => 'In base unit.',
    'choose' => '— Please choose —',
    'source_stock' => 'Stock',
    'stock_item' => 'Stock item',
    'flash_stock_issued' => 'Stock issued and booked as material cost.',
    'book_to_customer' => 'Material cost customer',
    'no_customer' => '— No customer —',
];
