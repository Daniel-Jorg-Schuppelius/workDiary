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
    'more' => '… and :count more',
    'contracts' => ['title' => 'Contracts and deadlines'],
    'warranties' => ['title' => 'Warranty periods'],
    'guarantees' => ['title' => 'Guarantees'],
    'tickets' => ['title' => 'Open tickets'],
    'surveys' => [
        'title' => 'Surveys',
        'summary' => ':responded of :total invitations answered',
        'summary_nps' => 'NPS :nps from :answers ratings · :responded of :total invitations answered',
    ],
    'purchase_orders' => ['title' => 'Purchase orders'],
    'supplier_catalogs' => [
        'title' => 'Catalogues',
        'items' => '{1} 1 item|[0,*] :count items',
        'active' => 'active',
        'inactive' => 'inactive',
    ],
    'supplier_scorecard' => [
        'title' => 'Performance (scorecard)',
        'summary' => 'Overall score :score of 100 · last :months months',
        'summary_none' => 'No overall score · last :months months',
    ],
    'asset_finance' => ['title' => 'Leasing and financing'],
    'claims' => ['title' => 'Open complaints'],
    'appointments' => [
        'title' => 'Appointments',
        'untitled' => 'Appointment',
    ],
    'credentials' => [
        'title' => 'Mandatory credentials',
        'summary' => 'Overall status: :status',
    ],
    'project' => [
        'budget' => 'Budget used',
        'budget_of' => 'of :budget',
    ],
];
