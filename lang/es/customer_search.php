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
    'nav' => 'Búsqueda',
    'title' => 'Búsqueda',
    'subtitle' => 'Busca en las áreas compartidas con usted: facturas, pedidos, documentos y tickets.',
    'field' => 'Término de búsqueda',
    'placeholder' => 'Número, título o palabra clave',
    'submit' => 'Buscar',
    'empty' => 'No hay resultados para «:q».',
    'group' => [
        'invoices' => 'Facturas',
        'diary' => 'Pedidos',
        'documents' => 'Documentos',
        'tickets' => 'Tickets',
    ],
];
