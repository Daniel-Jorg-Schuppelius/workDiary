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
    'more' => '… e altri :count',
    'contracts' => ['title' => 'Contratti e scadenze'],
    'warranties' => ['title' => 'Termini di garanzia'],
    'guarantees' => ['title' => 'Fideiussioni'],
    'tickets' => ['title' => 'Ticket aperti'],
    'surveys' => [
        'title' => 'Sondaggi',
        'summary' => ':responded inviti su :total con risposta',
        'summary_nps' => 'NPS :nps da :answers valutazioni · :responded inviti su :total con risposta',
    ],
    'purchase_orders' => ['title' => 'Ordini di acquisto'],
    'credentials' => [
        'title' => 'Attestazioni obbligatorie',
        'summary' => 'Stato complessivo: :status',
    ],
    'project' => [
        'budget' => 'Budget utilizzato',
        'budget_of' => 'su :budget',
    ],
];
