<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : manufacturing.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Produzione',

    'capacity' => [
        'title' => 'Capacità',
        'subtitle' => 'Centri di lavoro e carico (incl. attrezzaggio) nel periodo selezionato',
        'day' => 'Giorno',
        'period_note' => 'Carico sul periodo di intestazione :from – :to (capacità = capacità giornaliera × giorni).',
        'add' => 'Nuovo centro di lavoro',
        'empty' => 'Nessun centro di lavoro.',
        'work_center' => 'Centro di lavoro',
        'code' => 'Codice',
        'capacity' => 'Capacità',
        'planned' => 'Pianificato',
        'free' => 'Libero',
        'utilization' => 'Utilizzo',
        'setup' => 'Tempo di attrezzaggio',
        'assign' => 'Assegna centro di lavoro',
        'minutes' => 'Minuti',
        'flash' => [
            'created' => 'Centro di lavoro creato.',
            'assigned' => 'Centro di lavoro assegnato.',
            'assign_failed' => 'Assegnazione non possibile.',
        ],
    ],

    'planning' => [
        'title' => 'Pianificazione produzione',
        'subtitle' => 'Fabbisogno materiali multilivello (MRP) e indicatori qualità',
        'explode' => 'Calcola fabbisogno',
        'requirements' => 'Fabbisogno materiali',
        'no_bom' => 'Nessuna distinta base.',
        'level' => 'Livello',
        'source' => 'Origine',
        'make' => 'Produzione',
        'buy' => 'Acquisto',
        'gross' => 'Lordo',
        'net' => 'Netto',
        'quality' => 'Indicatori qualità',
        'yield' => 'Resa',
        'scrap_rate' => 'Tasso di scarto',
        'rework_rate' => 'Tasso di rilavorazione',
        'spc' => 'SPC (passi di misura)',
        'measurement' => 'Misura',
        'out_of_spec' => 'Fuori tolleranza',
    ],

    'procurement_mode' => [
        'in_house' => 'Produzione interna',
        'purchase' => 'Acquisto',
        'subcontract' => 'Conto lavoro',
    ],

    'quantity_kind' => [
        'fixed' => 'Quantità fissa',
        'per_unit' => 'Quantità per unità',
        'ratio' => 'Proporzione (ricetta)',
    ],
    'delivery_note' => [
        'title' => 'Bolla di consegna',
        'date' => 'Data di consegna',
        'order' => 'Ordine',
        'recipient' => 'Destinatario',
        'warehouse' => 'Magazzino',
        'no_customer' => 'Nessun cliente impostato',
        'footer_note' => 'Solo prova di consegna — non è una fattura. Si prega di confermare la ricezione.',
        'col' => [
            'sku' => 'N. articolo',
            'name' => 'Descrizione',
            'qty' => 'Quantità',
            'unit' => 'Unità',
        ],
    ],
    'parameter_type' => [
        'number' => 'Numero',
        'measure' => 'Misura (con unità)',
        'choice' => 'Scelta',
        'text' => 'Testo',
        'date' => 'Data',
        'bool' => 'Sì/No',
    ],
    'parameter' => [
        'error' => [
            'required' => 'Parametro obbligatorio ":param" mancante.',
            'invalid' => 'Il parametro ":param" ha un valore non valido.',
        ],
    ],

    'status' => [
        'draft' => 'Bozza',
        'released' => 'Rilasciato',
        'in_progress' => 'In lavorazione',
        'waiting' => 'In attesa',
        'blocked' => 'Bloccato',
        'completed' => 'Completato',
        'cancelled' => 'Annullato',
    ],

    'facturation_status' => [
        'pending' => 'In sospeso',
        'handed_over' => 'Trasmesso',
        'invoiced' => 'Fatturato',
        'failed' => 'Fallito',
        'not_required' => 'Non richiesto',
    ],

    'bom_override' => [
        'disable' => 'Disattiva',
        'override_qty' => 'Sostituisci quantità',
        'add' => 'Aggiungi',
    ],

    'substitute_status' => [
        'requested' => 'Richiesto',
        'approved' => 'Approvato',
        'rejected' => 'Rifiutato',
    ],

    'procurement_status' => [
        'open' => 'Aperto',
        'ordered' => 'Ordinato',
        'closed' => 'Chiuso',
    ],

    'order' => [
        'title' => 'Ordini di produzione',
        'subtitle' => 'Pianifica, rilascia e rendiconta gli ordini di produzione/montaggio.',
        'empty' => 'Nessun ordine di produzione.',
        'action' => [
            'create' => 'Crea ordine',
            'release' => 'Rilascia',
            'start' => 'Avvia',
            'reserve' => 'Riserva materiale',
            'report' => 'Rendiconta',
            'deliver' => 'Consegna',
            'push_lexoffice' => 'Invia a Lexoffice',
            'subcontract' => 'Conto lavoro',
            'cancel' => 'Annulla',
        ],
        'field' => [
            'target_qty' => 'Quantità obiettivo',
            'good' => 'Quantità buona',
            'scrap' => 'Scarto',
            'rework' => 'Rilavorazione',
            'produced' => 'Prodotto',
            'quantity' => 'Quantità',
            'materials' => 'Materiale',
            'reports' => 'Rendicontazioni',
            'article' => 'Articolo',
            'deliveries' => 'Consegne',
            'facturation_status' => 'Stato fatturazione',
        ],
        'flash' => [
            'created' => 'Ordine creato.',
            'released' => 'Ordine rilasciato.',
            'started' => 'Ordine avviato.',
            'reserved' => 'Materiale riservato.',
            'reported' => 'Rendicontazione registrata.',
            'delivered' => 'Consegnato.',
            'lexoffice_pushed' => 'Documento di trasporto inviato a Lexoffice.',
            'subcontracted' => 'Affidato al fornitore (ordine creato).',
            'subcontract_failed' => 'Conto lavoro non possibile.',
            'cancelled' => 'Ordine annullato.',
            'deliver_needs_variant_warehouse' => 'La consegna richiede una variante e un magazzino.',
        ],
    ],
];
