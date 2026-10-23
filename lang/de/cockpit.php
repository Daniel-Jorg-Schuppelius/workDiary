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
    'more' => '… und :count weitere',
    'contracts' => ['title' => 'Verträge und Fristen'],
    'warranties' => ['title' => 'Gewährleistungsfristen'],
    'guarantees' => ['title' => 'Bürgschaften'],
    'tickets' => ['title' => 'Offene Tickets'],
    'surveys' => [
        'title' => 'Umfragen',
        'summary' => ':responded von :total Einladungen beantwortet',
        'summary_nps' => 'NPS :nps aus :answers Bewertungen · :responded von :total Einladungen beantwortet',
    ],
    'purchase_orders' => ['title' => 'Bestellungen'],
    'credentials' => [
        'title' => 'Pflichtnachweise',
        'summary' => 'Gesamtstatus: :status',
    ],
    'project' => [
        'budget' => 'Budget verbraucht',
        'budget_of' => 'von :budget',
    ],
];
