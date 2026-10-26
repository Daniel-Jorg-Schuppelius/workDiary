<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : work-queue.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Coda di lavoro',
    'subtitle' => 'Tutto ciò che dovrebbe elaborare ora — approvazioni, caselle in arrivo, attività e promemoria. Ogni elemento si elabora nella sua pagina.',
    'filter' => [
        'category' => 'Area',
        'all_categories' => 'Tutte le aree',
        'source' => 'Origine',
        'all_sources' => 'Tutte le origini',
        'mine' => 'Solo quelli assegnati a me',
    ],
    'column' => [
        'urgency' => 'Urgenza',
        'item' => 'Elemento',
        'source' => 'Origine',
        'due' => 'Scadenza',
        'count' => 'Numero',
        'assignment' => 'Responsabilità',
    ],
    'assignment' => [
        'personal' => 'Assegnato a Lei',
        'shared' => 'Tramite il Suo ruolo',
    ],
    'empty' => [
        'title' => 'Niente da fare',
        'message' => 'Al momento nessun elemento La attende.',
    ],
    'source' => [
        'resale_plan' => 'Abbonamenti e licenze: modelli ricorrenti',
        'approval' => 'Catene di approvazione',
        'vacation_approval' => 'Richieste di ferie',
        'expense_approval' => 'Note spese',
        'month_closure' => 'Chiusure mensili',
        'price_change' => 'Variazioni di prezzo',
        'learning_time' => 'Tempo di formazione',
        'integration_inbox' => 'Casella di assegnazione',
        'invoice_inquiry' => 'Richieste su fatture',
        'incoming_invoice' => 'Fatture in entrata',
        'open_issue' => 'Punti aperti',
        'assigned_entry' => 'Ordini',
        'task' => 'Attività di progetto',
        'service_ticket' => 'Ticket di servizio',
        'draft_reminder' => 'Le Sue bozze',
        'chat_reminder' => 'Promemoria chat',
    ],
    'item' => [
        'vacation' => 'Richiesta di ferie di :name',
        'expense' => 'Nota spese di :name',
        'month_closure' => 'Mese :period di :name',
        'price_change' => 'Variazione di prezzo: :article',
        'learning_time' => 'Tempo di formazione di :name',
        'integration_inbox' => 'Assegnazioni aperte: :source',
        'invoice_inquiry' => 'Richiesta sulla fattura :number',
        'incoming_invoice' => [
            'assign' => 'Assegnare fatture in entrata',
            'review' => 'Verificare fatture in entrata',
            'failed' => 'Trasmissioni non riuscite',
            'duplicates' => 'Possibili doppi arrivi',
        ],
        'entry_untitled' => 'Ordine senza titolo',
        'chat_reminder' => 'Promemoria chat',
    ],
    'detail' => [
        'days_open' => ':days giorni non chiusi',
    ],
    'widget' => [
        'label' => 'Coda di lavoro',
        'description' => 'Gli elementi aperti più urgenti di tutte le aree.',
        'all' => 'Mostra tutto',
        'empty' => 'Al momento nessun elemento La attende.',
    ],
];
