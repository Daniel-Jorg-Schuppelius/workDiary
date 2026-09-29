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
    'nav' => 'Ricerca',
    'title' => 'Ricerca',
    'subtitle' => 'Cerca nelle aree condivise con Lei: fatture, ordini, documenti e ticket.',
    'field' => 'Termine di ricerca',
    'placeholder' => 'Numero, titolo o parola chiave',
    'submit' => 'Cerca',
    'empty' => 'Nessun risultato per «:q».',
    'group' => [
        'invoices' => 'Fatture',
        'diary' => 'Ordini',
        'documents' => 'Documenti',
        'tickets' => 'Ticket',
    ],
];
