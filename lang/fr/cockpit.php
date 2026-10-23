<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : cockpit.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Panels der Entitäts-Cockpits (Feature 182).
return [
    'more' => '… et :count de plus',
    'contracts' => ['title' => 'Contrats et échéances'],
    'warranties' => ['title' => 'Délais de garantie'],
    'guarantees' => ['title' => 'Cautions'],
    'tickets' => ['title' => 'Tickets ouverts'],
    'surveys' => [
        'title' => 'Enquêtes',
        'summary' => ':responded invitations sur :total ont répondu',
        'summary_nps' => 'NPS :nps sur :answers évaluations · :responded invitations sur :total ont répondu',
    ],
    'purchase_orders' => ['title' => 'Commandes fournisseurs'],
    'credentials' => [
        'title' => 'Justificatifs obligatoires',
        'summary' => 'Statut global : :status',
    ],
    'project' => [
        'budget' => 'Budget consommé',
        'budget_of' => 'sur :budget',
    ],
];
