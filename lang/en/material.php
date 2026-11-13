<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : material.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    // Material ↔ Artikel (MVP-904).
    'article' => [
        'label' => 'Article',
        'none' => '— no article —',
        'hint' => 'Via the article, the supplier report assigns consumption to a supplier.',
        'link' => 'Assign articles',
        'link_hint' => 'Assign materials without an article via the same SKU or article number; ambiguous matches remain open.',
        'linked' => ':count materials assigned to an article.',
    ],
    'stock' => [
        'insufficient' => 'Only :available :unit are available in warehouse “:warehouse”. To issue into negative stock, select “Allow negative stock”.',
        'negative_forbidden' => 'Only users with the “Approve negative stock” permission may issue into negative stock.',
        'serial_required' => 'This item requires serial numbers. Issue it from the warehouse with a serial number, or record the usage without the material master.',
        'booking_failed' => 'The stock issue could not be posted: :reason',
        'return_failed' => 'The return to stock could not be posted: :reason',
        'usage_gone' => 'The material usage of this request no longer exists.',
        'allow_negative_hint' => 'Only needed if a stock-managed item has insufficient stock in the default warehouse.',
        'notice' => ':status · warehouse “:warehouse”',
        'current' => 'Stock: :notice',
    ],
];
