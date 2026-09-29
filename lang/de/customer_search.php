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
    'nav' => 'Suche',
    'title' => 'Suche',
    'subtitle' => 'Durchsucht die für Sie freigegebenen Bereiche: Rechnungen, Aufträge, Dokumente und Tickets.',
    'field' => 'Suchbegriff',
    'placeholder' => 'Nummer, Titel oder Stichwort',
    'submit' => 'Suchen',
    'empty' => 'Keine Treffer für „:q“.',
    'group' => [
        'invoices' => 'Rechnungen',
        'diary' => 'Aufträge',
        'documents' => 'Dokumente',
        'tickets' => 'Tickets',
    ],
];
