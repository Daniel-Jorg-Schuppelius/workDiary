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
    'supplier_catalogs' => [
        'title' => 'Cataloghi',
        'items' => '{1} 1 articolo|[0,*] :count articoli',
        'active' => 'attivo',
        'inactive' => 'inattivo',
    ],
    'supplier_scorecard' => [
        'title' => 'Prestazioni (scorecard)',
        'summary' => 'Punteggio complessivo :score su 100 · ultimi :months mesi',
        'summary_none' => 'Nessun punteggio complessivo · ultimi :months mesi',
    ],
    'asset_finance' => ['title' => 'Leasing e finanziamento'],
    'claims' => ['title' => 'Reclami aperti'],
    'appointments' => [
        'title' => 'Appuntamenti',
        'untitled' => 'Appuntamento',
    ],
    'credentials' => [
        'title' => 'Attestazioni obbligatorie',
        'summary' => 'Stato complessivo: :status',
    ],
    'project' => [
        'budget' => 'Budget utilizzato',
        'budget_of' => 'su :budget',
    ],
];
