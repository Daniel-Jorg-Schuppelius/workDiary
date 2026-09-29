<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : customer_search.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

return [
    'nav' => 'Search',
    'title' => 'Search',
    'subtitle' => 'Searches the areas shared with you: invoices, orders, documents and tickets.',
    'field' => 'Search term',
    'placeholder' => 'Number, title or keyword',
    'submit' => 'Search',
    'empty' => 'No results for “:q”.',
    'group' => [
        'invoices' => 'Invoices',
        'diary' => 'Orders',
        'documents' => 'Documents',
        'tickets' => 'Tickets',
    ],
];
