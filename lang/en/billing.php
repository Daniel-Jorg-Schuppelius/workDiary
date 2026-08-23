<?php
/*
 * Created on   : Sun Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : billing.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'feed' => [
        'title' => 'Document flow',
        'subtitle' => 'Quotes, invoices, vouchers and expenses for :range — adjustable via the date filter in the header.',
        'empty' => 'No documents in the selected period',
        'search_placeholder' => 'Number, customer, supplier …',
        'days_short' => 'd',
        'dunning_level' => 'Dunning level :level',
        'action' => [
            'dun' => 'Send reminder',
            'dun_confirm' => 'Create a dunning notice in the accounting system?',
        ],
        'tab' => [
            'all' => 'All',
            'quotes' => 'Quotes',
            'outgoing' => 'Sales invoices',
            'incoming' => 'Purchase invoices',
            'credits' => 'Credit notes',
            'expenses' => 'Expenses',
            'other' => 'Other',
        ],
        'kpi' => [
            'revenue' => 'Revenue',
            'expense' => 'Cost (external)',
            'balance' => 'Balance',
            'internal_mine' => 'My expenses',
            'internal_all' => 'Expenses (all)',
            'internal_pending' => 'of which pending: :amount',
            'open' => 'Open',
            'overdue' => 'of which overdue',
            'open_count' => '{0} no open document|{1} :count open document|[2,*] :count open documents',
            'overdue_count' => ':count of :total documents',
            'neutral' => 'No monetary effect',
            'neutral_hint' => 'Quotes, order confirmations and delivery notes are counted only.',
        ],
        'filter' => [
            'direction' => 'Direction',
            'origin' => 'Origin',
            'only_overdue' => 'Overdue only',
            'only_unlinked' => 'Unlinked only',
            'with_archived' => 'Include archived',
        ],
        'state' => [
            'draft' => 'Draft',
            'open' => 'Open',
            'paid' => 'Closed',
            'cancelled' => 'Cancelled',
        ],
        'scope' => [
            'mine' => 'Mine',
            'all' => 'All',
        ],
        'column' => [
            'kind' => 'Type',
            'origin' => 'Origin',
            'due' => 'Due',
            'open' => 'Open',
        ],
    ],
];
