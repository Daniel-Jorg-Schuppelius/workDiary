<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : article.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Articles',
    'subtitle' => 'Canonical article master of the tenant (products, material, services).',
    'empty' => 'No articles created yet.',
    'variants' => 'Variants',
    'options' => 'Options',
    'units' => 'Units',
    'external_mappings' => 'External mappings',
    'supplies' => [
        'title' => 'Supply sources',
        'supplier' => 'Supplier',
        'sku' => 'Supplier item no.',
        'price' => 'Cost price',
        'lead_time' => 'Lead time',
        'moq' => 'Min. qty',
        'days' => 'days',
        'preferred' => 'Preferred',
        'recommended' => 'Recommended',
        'set_preferred' => 'Set as preferred',
        'flash' => ['preferred_set' => 'Preferred supply source set.', 'datanorm_empty' => 'No exportable articles (active and sellable) available.'],
    ],
    'no_options' => 'No options defined.',
    'no_variants' => 'No variants created.',
    'sku_auto_hint' => 'assigned automatically',

    'datanorm_oversized' => ':count article number exceeds 15 characters and is excluded from DATANORM exports.|:count article numbers exceed 15 characters and are excluded from DATANORM exports.',

    'discount_group' => [
        'title' => 'Sales discount groups',
        'hint' => 'Org-wide standard conditions for DATANORM exports with list prices: recipients calculate list − discount = net. Customer-specific prices go via the B2B DATPREIS.',
        'empty' => 'No discount groups yet.',
        'confirm_delete' => 'Delete this discount group? Article assignments will be removed.',
        'kind' => ['discount' => 'Discount (%)', 'factor' => 'Factor', 'surcharge' => 'Surcharge (%)'],
        'col' => ['code' => 'Code', 'kind' => 'Kind', 'value' => 'Value', 'label' => 'Label', 'articles' => 'Articles'],
        'action' => ['add' => 'Add', 'delete' => 'Delete'],
        'tiers' => [
            'title' => 'Scale prices',
            'hint' => 'From the given quantity the scale price replaces the standard sale price; travels as Z record in the DATANORM export.',
            'min_qty' => 'From quantity',
            'unit_price' => 'Unit price',
            'empty' => 'No scale prices maintained.',
            'action' => ['add' => 'Add tier'],
            'flash' => ['saved' => 'Scale price saved.', 'deleted' => 'Scale price deleted.'],
        ],
        'flash' => ['created' => 'Discount group created.', 'deleted' => 'Discount group deleted.', 'override_saved' => 'Customer override saved.', 'override_deleted' => 'Customer override deleted.'],
        'override' => [
            'title' => 'Customer overrides',
            'hint' => 'Customer-specific rates per discount group — applied in the customer-specific B2B DATPREIS; an article custom_price stays stronger.',
            'customer' => 'Customer',
            'empty' => 'No customer overrides yet.',
        ],
    ],

    'action' => [
        'create' => 'Create article',
        'export_datanorm' => 'DATANORM export',
        'export_datanorm_v5_list' => 'DATANORM 5 — sale price as list price',
        'export_datanorm_v5_net' => 'DATANORM 5 — sale price as net price',
        'export_datanorm_v4_list' => 'DATANORM 4 — sale price as list price',
        'export_datpreis_title' => 'Price file (DATPREIS)',
        'export_datpreis_v5' => 'DATPREIS 5 — current sale prices',
        'export_datpreis_v4' => 'DATPREIS 4 — current sale prices',
        'export_datpreis_since' => 'DATPREIS 5 — changes of the last 30 days',
        'export_datpreis_custom' => 'DATPREIS since date',
        'edit' => 'Edit article',
        'retire' => 'Retire',
        'add_option' => 'Add option',
        'add_value' => 'Value',
        'add_variant' => 'Create variant',
        'add_unit' => 'Add unit',
    ],

    'field' => [
        'sku' => 'Article number (SKU)',
        'type' => 'Article type',
        'status' => 'Status',
        'base_unit' => 'Base unit',
        'category' => 'Product group',
        'category_hint' => 'Used for reports and the DATANORM export (WRG file).',
        'subcategory' => 'Product subgroup',
        'sales_discount_group' => 'Sales discount group',
        'sales_discount_group_hint' => 'For DATANORM exports with list prices (RAB file).',
        'assembly_minutes' => 'Assembly time (minutes per unit)',
        'assembly_minutes_hint' => 'Calculated labour time; filled from ARBA records during DATANORM adoption.',
        'copper_weight' => 'Copper weight (kg per unit)',
        'copper_weight_hint' => 'Used for the day-price copper surcharge (DEL) and the Z records in the DATANORM export.',
        'copper_base_price' => 'Copper base in price (€ per 100 kg)',
        'copper_base_price_hint' => 'DEL base already included in the sale price (German method).',
        'gtin' => 'GTIN',
        'default_purchase_price' => 'Purchase price (default)',
        'default_sale_price' => 'Sale price (default)',
        'currency' => 'Currency',
        'code' => 'Code',
        'label' => 'Label',
        'option_name' => 'Option name',
        'combination' => 'Combination',
        'sale_price' => 'Sale price',
        'unit_kind' => 'Kind',
        'factor_to_base' => 'Factor to base unit',
        'external_id' => 'External ID',
        'sync_status' => 'Sync status',
    ],

    'group' => [
        'pricing' => 'Pricing',
        'flags' => 'Properties',
    ],

    'flag' => [
        'stockable' => 'Stockable',
        'purchasable' => 'Purchasable',
        'sellable' => 'Sellable',
        'manufacturable' => 'Manufacturable',
        'batch_required' => 'Batch-tracked',
        'serial_required' => 'Serial-tracked',
        'shelf_life_required' => 'Shelf life required',
    ],

    'type' => [
        'raw' => 'Raw material',
        'consumable' => 'Consumable',
        'merchandise' => 'Merchandise',
        'semifinished' => 'Semi-finished good',
        'finished' => 'Finished good',
        'service' => 'Service',
    ],

    'status' => [
        'draft' => 'Draft',
        'active' => 'Active',
        'retired' => 'Retired',
    ],

    'unit_kind' => [
        'base' => 'Base',
        'purchase' => 'Purchase',
        'sale' => 'Sale',
        'packaging' => 'Packaging',
    ],

    'confirm' => [
        'retire' => 'Really retire this article? Variants will be retired too.',
        'delete' => 'Permanently delete this article? Only unreferenced drafts can be deleted.',
    ],

    'flash' => [
        'created' => 'Article created.',
        'updated' => 'Article updated.',
        'deleted' => 'Article deleted.',
        'retired' => 'Article retired.',
        'delete_blocked' => 'Article cannot be deleted: only unreferenced drafts are deletable. Please retire it instead.',
        'option_added' => 'Option added.',
        'value_added' => 'Option value added.',
        'unit_added' => 'Unit added.',
        'variant_added' => 'Variant created.',
        'variant_retired' => 'Variant retired.',
    ],
];
