<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : inventory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Magazzino',

    'mode' => [
        'local' => 'Locale (WorkDiary gestisce la giacenza)',
        'external' => 'Esterno (gestito dal gestionale)',
        'read_only' => 'Sola lettura (gestito esternamente)',
    ],

    'state' => [
        'physical' => 'Fisico',
        'reserved' => 'Riservato',
        'blocked' => 'Bloccato',
        'quality' => 'Controllo qualità',
        'damaged' => 'Danneggiato',
        'scrap' => 'Scarto',
    ],

    'ownership' => [
        'own' => 'Giacenza propria',
        'customer' => 'Materiale cliente',
        'consignment' => 'Conto deposito',
        'supplier' => 'Materiale fornitore',
        'project' => 'Vincolato al progetto',
    ],

    'movement' => [
        'receipt' => 'Entrata merce',
        'issue' => 'Prelievo',
        'return' => 'Reso',
        'transfer_out' => 'Trasferimento (uscita)',
        'transfer_in' => 'Trasferimento (entrata)',
        'reserve' => 'Prenotazione',
        'release_reservation' => 'Prenotazione rilasciata',
        'scrap' => 'Scarto',
        'correction' => 'Correzione/differenza inventario',
        'finished_good_receipt' => 'Entrata prodotto finito',
    ],

    'warehouses' => 'Magazzini',
    'stock' => 'Giacenza',
    'subtitle' => [
        'warehouses' => 'Gestisci i magazzini del tenant.',
        'stock' => 'Disponibilità e movimenti per magazzino.',
    ],
    'action' => [
        'create_warehouse' => 'Crea magazzino',
        'edit_warehouse' => 'Modifica magazzino',
        'book' => 'Registra movimento',
    ],
    'field' => [
        'code' => 'Codice',
        'default' => 'Predefinito',
        'available' => 'Disponibile',
        'physical' => 'Fisico',
        'reserved' => 'Riservato',
        'location_note' => 'Nota ubicazione',
        'warehouse' => 'Magazzino',
        'variant' => 'Variante',
        'quantity' => 'Quantità',
        'movement' => 'Movimento',
        'ownership' => 'Tipo di proprietà',
        'allow_negative' => 'Consenti giacenza negativa',
    ],
    'empty' => [
        'warehouses' => 'Nessun magazzino creato finora.',
        'stock' => 'Nessun movimento in questo magazzino.',
        'no_selection' => 'Nessun magazzino selezionato.',
    ],
    'confirm' => [
        'delete_warehouse' => 'Eliminare davvero questo magazzino? Possibile solo senza movimenti.',
    ],
    'flash' => [
        'warehouse_created' => 'Magazzino creato.',
        'warehouse_updated' => 'Magazzino aggiornato.',
        'warehouse_deleted' => 'Magazzino eliminato.',
        'warehouse_delete_blocked' => 'Impossibile eliminare il magazzino: esistono movimenti.',
        'movement_posted' => 'Movimento registrato.',
    ],
    'reservation_status' => [
        'active' => 'Attiva',
        'fulfilled' => 'Evasa',
        'released' => 'Rilasciata',
        'cancelled' => 'Annullata',
    ],
    'count_status' => [
        'counting' => 'Conteggio',
        'review' => 'Verifica',
        'completed' => 'Completata',
        'cancelled' => 'Annullata',
    ],
    'count_ui' => [
        'title' => 'Inventario',
        'open' => 'Apri inventario',
        'save' => 'Salva conteggio',
        'apply' => 'Registra differenze',
        'book' => 'Teorico',
        'counted' => 'Contato',
        'difference' => 'Differenza',
        'counted_at' => 'Data conteggio',
        'no_counts' => 'Nessun inventario per questo magazzino.',
        'no_selection' => 'Nessun magazzino selezionato.',
        'opened' => 'Inventario aperto, giacenza teorica congelata.',
        'saved' => 'Quantità contate salvate.',
        'applied' => 'Differenze registrate come correzioni.',
        'cycle' => 'Ciclo (ABC)',
        'cycle_open' => 'Conta ciclo',
        'cycle_empty' => 'Nessun articolo dovuto in questa classe.',
    ],
    'overview' => [
        'avg' => 'Costo medio',
        'value' => 'Valore',
        'priority' => 'Priorità',
        'min_stock' => 'Scorta minima',
        'reorder_point' => 'Punto di riordino',
        'release' => 'Rilascia',
        'set_levels' => 'Imposta soglie',
        'reservations' => 'Prenotazioni',
        'below_reorder' => 'Fabbisogno di approvvigionamento',
        'shortfall' => 'Mancante',
        'no_reservations' => 'Nessuna prenotazione attiva.',
        'reservation_released' => 'Prenotazione rilasciata.',
        'levels_saved' => 'Soglie min./riordino salvate.',
    ],

    'serial' => [
        'title' => 'Numeri di serie',
        'subtitle' => 'Ciclo di vita per singolo pezzo, prova di spedizione e verifica di autenticità.',
        'empty' => 'Nessun numero di serie.',
        'blocked_default' => 'Bloccato',
        'status' => [
            'created' => 'Creato',
            'in_stock' => 'In magazzino',
            'reserved' => 'Riservato',
            'shipped' => 'Spedito',
            'returned' => 'Reso',
            'blocked' => 'Bloccato',
            'scrapped' => 'Rottamato',
        ],
        'source' => [
            'manufactured' => 'Produzione propria',
            'purchased' => 'Acquisto',
        ],
        'field' => [
            'serial_no' => 'Numero di serie',
            'status' => 'Stato',
            'source' => 'Origine',
            'article' => 'Articolo',
            'variant' => 'Variante',
            'warehouse' => 'Magazzino',
            'customer' => 'Cliente',
            'order' => 'Ordine di produzione',
            'delivery' => 'Consegna',
            'shipped_at' => 'Spedito il',
            'reason' => 'Motivo',
        ],
        'action' => [
            'block' => 'Blocca',
            'unblock' => 'Sblocca',
            'scrap' => 'Rottama',
            'verify' => 'Passaporto dispositivo',
            'search' => 'Cerca',
        ],
        'flash' => [
            'blocked' => 'Numero di serie bloccato.',
            'unblocked' => 'Numero di serie sbloccato.',
            'scrapped' => 'Numero di serie rottamato.',
        ],
        'verify' => [
            'title' => 'Passaporto dispositivo / verifica di autenticità',
            'subtitle' => 'Inserisci un numero di serie per verificarne stato e origine.',
            'placeholder' => 'Numero di serie …',
            'not_found' => 'Nessun numero di serie trovato – autenticità non confermata.',
            'found' => 'Numero di serie trovato.',
        ],
    ],

    'conflict' => [
        'title' => 'Conflitti di giacenza (esterno)',
        'empty' => 'Nessun conflitto di giacenza aperto.',
        'filter' => ['open' => 'Aperti', 'all' => 'Tutti'],
        'col' => [
            'id' => 'Movimento',
            'operation' => 'Operazione',
            'qty' => 'Quantità',
            'status' => 'Stato',
            'actions' => 'Azioni',
        ],
        'status' => [
            'open' => 'Aperto',
            'resolved_local' => 'Mantenuto locale',
            'resolved_remote' => 'Preso esterno',
            'compensated' => 'Compensato',
            'dismissed' => 'Ignorato',
        ],
        'action' => [
            'compensate' => 'Contromovimento',
            'keep_local' => 'Mantieni locale',
        ],
        'flash' => [
            'kept_local' => 'Conflitto chiuso — giacenza locale mantenuta.',
            'compensated' => 'Conflitto compensato — contromovimento registrato.',
        ],
    ],

    'outbox' => [
        'status' => [
            'pending' => 'In attesa',
            'processing' => 'In consegna',
            'confirmed' => 'Confermato',
            'failed' => 'Fallito',
            'compensation_required' => 'Compensazione necessaria',
        ],
    ],

    'valuation' => [
        'method' => [
            'moving_average' => 'Costo medio ponderato',
            'fifo' => 'FIFO',
            'fefo' => 'FEFO (prima scadenza)',
        ],
    ],

    'scan' => [
        'action' => [
            'receipt' => 'Entrata merci',
            'issue' => 'Uscita',
            'transfer' => 'Trasferimento',
        ],
        'title' => 'Scansiona',
        'subtitle' => 'Scansiona un codice e registra',
        'code' => 'Codice',
        'qty' => 'Quantità',
        'book' => 'Registra',
        'action_label' => 'Azione',
        'target' => 'Magazzino destinazione',
        'invalid' => 'Input non valido.',
        'booked' => 'Movimento registrato.',
    ],

    'lot' => [
        'title' => 'Lotti',
        'subtitle' => 'Giacenza per lotto, split e merge',
        'empty' => 'Nessun lotto.',
        'lot_no' => 'Lotto',
        'article' => 'Articolo',
        'best_before' => 'TMC',
        'on_hand' => 'Giacenza',
        'split' => 'Dividi',
        'merge' => 'Unisci',
        'new_lot_no' => 'Nuovo lotto',
        'qty' => 'Quantità',
        'from' => 'Da',
        'into' => 'In',
        'flash' => [
            'split' => 'Lotto diviso.',
            'merged' => 'Lotti uniti.',
            'unknown' => 'Lotto sconosciuto.',
        ],
    ],

    'label_template' => [
        'title' => 'Modelli di etichetta',
        'subtitle' => 'Layout, formato, QR e campi per modello',
        'add' => 'Nuovo modello',
        'empty' => 'Nessun modello di etichetta.',
        'name' => 'Nome',
        'paper_size' => 'Formato',
        'orientation' => 'Orientamento',
        'orientation_landscape' => 'Orizzontale',
        'orientation_portrait' => 'Verticale',
        'with_qr' => 'Codice QR',
        'is_default' => 'Modello predefinito',
        'default' => 'Predefinito',
        'fields' => 'Campi',
        'delete' => 'Elimina modello',
        'field' => [
            'title' => 'Titolo',
            'subtitle' => 'Sottotitolo',
            'code' => 'Codice',
            'code_type' => 'Tipo di codice',
            'lines' => 'Righe',
        ],
        'flash' => [
            'saved' => 'Modello salvato.',
            'deleted' => 'Modello eliminato.',
        ],
    ],
];
