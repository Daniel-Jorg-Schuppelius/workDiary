<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : products.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Produktstamm (Typ-Ebene Hersteller-Modell, MVP-370).
return [
    'title' => [
        'index' => 'Products',
        'subtitle' => 'Type level manufacturer + model: bundles articles and assets of the same product.',
        'create' => 'Create product',
        'edit' => 'Edit product',
        'empty' => 'No products yet.',
        'empty_search' => 'No products found for ":q".',
    ],
    'field' => [
        'basics' => 'Master data',
        'manufacturer' => 'Manufacturer',
        'model' => 'Model',
        'name' => 'Display name',
        'name_placeholder' => 'Manufacturer model',
        'name_help' => 'Leave empty for "manufacturer model".',
        'product_group' => 'Product group',
        'no_group' => '— none —',
        'articles' => 'Articles',
        'assets' => 'Assets',
        'status' => 'Status',
        'notes' => 'Notes',
        'product' => 'Product',
        'no_product' => '— no product —',
        'product_help' => 'Type assignment (manufacturer model); prefills manufacturer/model.',
    ],
    'action' => [
        'create' => 'Create product',
        'save' => 'Save',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'delete_confirm' => 'Really delete this product? Articles and assets remain and only lose their type assignment.',
    ],
    'flash' => [
        'created' => 'Product created.',
        'updated' => 'Product updated.',
        'deleted' => 'Product deleted.',
    ],
];
