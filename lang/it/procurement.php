<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : procurement.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Ordini di acquisto',
    'subtitle' => 'Ordini, entrata merci e proposte di riordino',
    'empty' => 'Nessun ordine di acquisto.',

    'action' => [
        'create' => 'Nuovo ordine',
        'add_line' => 'Aggiungi riga',
        'submit' => 'Ordina',
        'receive' => 'Entrata merci',
        'cancel' => 'Annulla',
        'suggestions' => 'Proposte di ordine',
        'apply' => 'Crea ordini',
        'incoming' => 'Entrate previste',
    ],

    'field' => [
        'number' => 'N.',
        'supplier' => 'Fornitore',
        'warehouse' => 'Magazzino',
        'ordered_qty' => 'Ordinato',
        'received_qty' => 'Ricevuto',
        'unit_price' => 'Prezzo unitario',
        'article' => 'Articolo',
        'qty' => 'Quantità',
        'expected_at' => 'Data di consegna',
        'note' => 'Nota',
    ],

    'flash' => [
        'created' => 'Ordine creato.',
        'line_added' => 'Riga aggiunta.',
        'ordered' => 'Ordine inviato.',
        'received' => 'Entrata merci registrata.',
        'cancelled' => 'Ordine annullato.',
        'suggestions_applied' => ':count ordine/i creato/i.',
        'unknown_article' => 'Articolo sconosciuto.',
        'unknown_line' => 'Riga sconosciuta.',
        'no_warehouse' => 'Nessun magazzino selezionato.',
    ],

    'ui' => [
        'suggestions_title' => 'Proposte di ordine',
        'needed' => 'Fabbisogno',
        'suggested' => 'Proposta',
        'none' => 'Nessuna proposta.',
        'select_warehouse' => 'Seleziona magazzino',
        'incoming_title' => 'Entrate previste',
        'incoming_subtitle' => 'Righe aperte degli ordini inviati',
        'incoming_none' => 'Nessuna entrata prevista.',
        'open' => 'Aperto',
    ],

    'status' => [
        'draft' => 'Bozza',
        'ordered' => 'Ordinato',
        'partially_received' => 'Parzialmente ricevuto',
        'received' => 'Ricevuto',
        'cancelled' => 'Annullato',
    ],

    'advice_status' => [
        'announced' => 'Annunciato',
        'received' => 'Ricevuto',
        'cancelled' => 'Annullato',
    ],

    'advice' => [
        'title' => 'Avvisi di spedizione',
        'announce' => 'Inserisci avviso di spedizione',
        'reference' => 'N. avviso / DDT',
        'announced_qty' => 'Annunciato',
        'receive' => 'Registra entrata merci',
        'flash' => [
            'announced' => 'Avviso di spedizione inserito.',
            'received' => 'Entrata merci registrata dall’avviso.',
            'cancelled' => 'Avviso di spedizione annullato.',
        ],
    ],
];
