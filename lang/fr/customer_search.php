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
    'nav' => 'Recherche',
    'title' => 'Recherche',
    'subtitle' => 'Recherche dans les domaines partagés avec vous : factures, commandes, documents et tickets.',
    'field' => 'Terme de recherche',
    'placeholder' => 'Numéro, titre ou mot-clé',
    'submit' => 'Rechercher',
    'empty' => 'Aucun résultat pour « :q ».',
    'group' => [
        'invoices' => 'Factures',
        'diary' => 'Commandes',
        'documents' => 'Documents',
        'tickets' => 'Tickets',
    ],
];
