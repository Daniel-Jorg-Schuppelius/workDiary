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
            'open_original' => 'Aprire l’originale',
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
            'project' => 'Progetto',
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
    'relations' => [
        'title' => 'Documenti collegati (:count)',
        'column' => [
            'document' => 'Documento',
            'number' => 'Numero',
            'date' => 'Data',
            'state' => 'Stato',
            'system' => 'Sistema',
            'relation' => 'Relazione',
        ],
        'via' => 'tramite :document :number',
        'schedule' => 'Piano di fatturazione',
        'quote_version' => 'Offerta (versione :version)',
        'manufacturing_order' => 'Ordine di produzione',
        'transfer' => 'Trasmissione (:channel)',
        'draft' => 'Bozza',
    ],
    // Bolla di consegna come documento proprio (fase 145, MVP-1163).
    'delivery_note' => [
        'create' => 'Crea bolla di consegna',
        'edit' => 'Modifica quantità',
        'edit_hint' => 'Per una consegna parziale, modifichi la quantità; 0 toglie la voce da questa bolla di consegna.',
        'details' => 'Dati di consegna',
        'deliver' => 'Consegna',
        'cancel' => 'Annulla',
        'cancel_confirm' => 'Annullare la bolla di consegna? Nel programma di contabilità rimane.',
        'delivered_on' => 'Consegnato il :date',
        'to_order' => 'Ordine di produzione :number',
        'number_pending' => ':program assegna il numero alla consegna; fino ad allora la bolla di consegna porta un numero provvisorio.',
        'flash' => [
            'updated' => 'Bolla di consegna salvata.',
            'delivered' => 'Bolla di consegna :number consegnata.',
            'cancelled' => 'Bolla di consegna annullata.',
            'issue_failed' => 'Consegnato, ma la bolla di consegna :number resta una bozza: :error',
        ],
        'error' => [
            'not_confirmed' => 'Una bolla di consegna nasce solo da una conferma d\'ordine confermata.',
            'fully_delivered' => 'Tutte le voci della conferma d\'ordine sono già state consegnate.',
            'not_editable' => 'Si possono modificare solo bozze senza uscita di magazzino.',
            'not_draft' => 'Si possono consegnare solo bozze.',
            'empty' => 'La bolla di consegna non ha voci.',
            'not_cancellable' => 'Questa bolla di consegna non può essere annullata. Corregga un\'uscita di magazzino tramite il movimento di magazzino.',
            'not_delivered' => 'La bolla di consegna non è ancora stata consegnata.',
        ],
    ],
];
