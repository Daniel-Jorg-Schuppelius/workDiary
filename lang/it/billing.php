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
        'title' => 'Flusso documenti',
        'subtitle' => 'Preventivi, fatture, documenti e note spese nel periodo :range — modificabile dal filtro data in testata.',
        'empty' => 'Nessun documento nel periodo selezionato',
        'search_placeholder' => 'Numero, cliente, fornitore …',
        'days_short' => 'gg',
        'dunning_level' => 'Livello sollecito :level',
        'action' => [
            'dun' => 'Sollecita',
            'dun_confirm' => 'Creare un sollecito in contabilità?',
        ],
        'tab' => [
            'all' => 'Tutti',
            'quotes' => 'Preventivi',
            'outgoing' => 'Fatture di vendita',
            'incoming' => 'Fatture di acquisto',
            'credits' => 'Note di credito',
            'expenses' => 'Note spese',
            'other' => 'Altri',
        ],
        'kpi' => [
            'revenue' => 'Ricavi',
            'expense' => 'Costi (esterni)',
            'balance' => 'Saldo',
            'internal_mine' => 'Le mie note spese',
            'internal_all' => 'Note spese (tutte)',
            'internal_pending' => 'di cui in verifica: :amount',
            'open' => 'Aperto',
            'overdue' => 'di cui scaduti',
            'open_count' => '{0} nessun documento aperto|{1} :count documento aperto|[2,*] :count documenti aperti',
            'overdue_count' => ':count su :total documenti',
            'neutral' => 'Senza effetto monetario',
            'neutral_hint' => 'Preventivi, conferme d\'ordine e bolle sono solo conteggiati.',
        ],
        'filter' => [
            'direction' => 'Direzione',
            'origin' => 'Origine',
            'only_overdue' => 'Solo scaduti',
            'only_unlinked' => 'Solo senza documento contabile',
            'with_archived' => 'Includi archiviati',
        ],
        'state' => [
            'draft' => 'Bozza',
            'open' => 'Aperto',
            'paid' => 'Chiuso',
            'cancelled' => 'Annullato',
        ],
        'scope' => [
            'mine' => 'Le mie',
            'all' => 'Tutte',
        ],
        'column' => [
            'kind' => 'Tipo',
            'origin' => 'Origine',
            'due' => 'Scadenza',
            'open' => 'Residuo',
        ],
    ],
];
