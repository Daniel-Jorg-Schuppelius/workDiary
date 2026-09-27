<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : recall.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Rückrufaktionen (MVP-921/922).
return [
    'title' => 'Richiami di prodotti',
    'nav' => 'Richiami',
    'subtitle' => 'Richiamo per variante di articolo: individuare consegne e clienti interessati, bloccare le giacenze, seguire lo stato per cliente.',
    'empty' => 'Nessun richiamo.',
    'items_none' => 'Nessuna consegna interessata.',
    'yes' => 'sì',
    'no' => 'no',
    'kpi' => [
        'active' => 'Richiami attivi',
    ],
    'filter' => [
        'all_status' => 'Tutti gli stati',
    ],
    'field' => [
        'number' => 'Numero',
        'title' => 'Denominazione',
        'variant' => 'Variante di articolo',
        'kind' => 'Motivo',
        'open_items' => 'Aperti / interessati',
        'status' => 'Stato',
        'reason' => 'Motivo e misura',
        'customer_message' => 'Messaggio ai clienti',
        'manufacturing_orders' => 'Ordini di produzione',
        'delivered_from' => 'Consegnato dal',
        'delivered_until' => 'Consegnato fino al',
        'serial_numbers' => 'Numeri di serie',
        'is_blocking_stock' => 'Bloccare le giacenze dell\'ambito',
        'activated_at' => 'Attivato il',
        'delivered_at' => 'Consegnato il',
        'customer' => 'Cliente',
        'quantity' => 'Quantità',
        'serial' => 'Numero di serie',
        'actions' => 'Azioni',
        'claim' => 'Reclamo',
        'sent_at' => 'Inviato il',
        'recipient' => 'Destinatario',
    ],
    'hint' => [
        'customer_message' => 'Utilizzato nel portale clienti e nella lettera.',
        'scope' => 'I campi vuoti non restringono; tutti i campi compilati valgono insieme.',
        'list' => 'Separati da virgole o uno per riga.',
        'claim' => 'Aprire un reclamo con RMA per il reso.',
    ],
    'section' => [
        'recall' => 'Richiamo',
        'scope' => 'Ambito',
        'preview' => 'Anteprima delle consegne interessate',
        'items' => 'Consegne interessate',
        'dispatches' => 'Prove di invio',
    ],
    'preview' => [
        'summary' => ':deliveries consegne interessate, :stock numeri di serie a magazzino',
        'none' => 'Nessuna consegna in questo ambito.',
    ],
    'action' => [
        'create' => 'Crea richiamo',
        'show' => 'Mostra',
        'edit' => 'Modifica',
        'save' => 'Salva',
        'notify' => 'Informare i clienti',
        'claim' => 'Reclamo',
    ],
    'dialog' => [
        'create' => 'Crea richiamo',
        'edit' => 'Modifica richiamo',
    ],
    'transition' => [
        'active' => 'Attiva',
        'completed' => 'Concludi',
        'cancelled' => 'Annulla',
    ],
    'confirm' => [
        'active' => 'Attivare il richiamo? Le consegne interessate vengono fissate e le giacenze dell\'ambito bloccate.',
        'completed' => 'Concludere il richiamo?',
        'cancelled' => 'Annullare il richiamo? I blocchi di questo richiamo vengono rimossi.',
        'notify' => 'Inviare un\'e-mail a tutti i clienti con posizioni aperte?',
    ],
    'item_transition' => [
        'notified' => 'Informato',
        'returned' => 'Reso',
        'resolved' => 'Risolto',
    ],
    'status' => [
        'draft' => 'Bozza',
        'active' => 'Attivo',
        'completed' => 'Concluso',
        'cancelled' => 'Annullato',
    ],
    'item_status' => [
        'open' => 'Aperto',
        'notified' => 'Informato',
        'returned' => 'Reso',
        'resolved' => 'Risolto',
    ],
    'kind' => [
        'safety' => 'Sicurezza',
        'quality' => 'Qualità',
        'regulatory' => 'Obbligo normativo',
    ],
    'error' => [
        'not_draft' => 'Solo le bozze possono essere modificate.',
        'not_active' => 'Le comunicazioni sono possibili solo per richiami attivi.',
    ],
    'flash' => [
        'created' => 'Richiamo :number creato.',
        'saved' => 'Richiamo salvato.',
        'status' => 'Stato: :status.',
        'item' => 'Stato salvato.',
        'notified' => ':count clienti informati.',
        'without_email' => 'Senza e-mail valida, informare in altro modo: :customers',
        'claim' => 'Reclamo :number aperto per il reso.',
    ],
    'stats' => [
        'return_rate' => 'Tasso di reso',
    ],
    'dispatch' => [
        'queued' => 'In coda',
        'sent' => 'Inviato',
        'failed' => 'Non riuscito',
        'none' => 'Nessuna comunicazione inviata finora.',
    ],
    'mail' => [
        'subject' => 'Richiamo: :title (:number)',
        'body' => "Gentile :name,\n\nrichiamiamo il seguente prodotto: :product.\n\n:message",
        'default_message' => 'Non utilizzi più il prodotto e ci contatti; concorderemo il reso o la sostituzione.',
        'serials' => 'Numeri di serie interessati: :serials',
    ],
    'claim' => [
        'title' => 'Richiamo :number: :title',
    ],
    'portal' => [
        'subject' => 'Richiamo: :title (:product)',
    ],
    // Behördenmeldung (MVP-945).
    'authority' => [
        'title' => 'Notifica all\'autorità',
        'save' => 'Salva',
        'pdf' => 'Modulo di notifica',
        'pdf_title' => 'Modulo di richiamo',
        'pdf_note' => 'Raccolta dei dati per la notifica alla sorveglianza del mercato; la notifica avviene nel portale dell\'autorità competente.',
        'section' => [
            'product' => 'Prodotto',
            'hazard' => 'Pericolo e misura',
            'scope' => 'Portata',
            'authority' => 'Autorità',
        ],
        'field' => [
            'product' => 'Prodotto',
            'gtin' => 'GTIN',
            'batches' => 'Numeri di serie',
            'delivered' => 'Periodo di consegna',
            'hazard_kind' => 'Tipo di pericolo',
            'hazard_description' => 'Descrizione del pericolo',
            'risk_level' => 'Livello di rischio',
            'measure' => 'Misura',
            'countries' => 'Paesi di distribuzione',
            'units' => 'Unità interessate',
            'customers' => 'Clienti interessati',
            'returned' => 'Resi',
            'activated_at' => 'Richiamo dal',
            'authority_name' => 'Autorità',
            'authority_reference' => 'Riferimento',
            'authority_reported_on' => 'Notificato il',
            'contact' => 'Referente',
            'contact_name' => 'Referente',
            'contact_email' => 'E-mail del referente',
        ],
        'hint' => [
            'hazard_kind' => 'ad es. incendio, scossa elettrica, lesione, chimico',
            'countries' => 'Codici paese separati da virgola (DE, AT, …)',
        ],
        'risk' => [
            'low' => 'basso',
            'medium' => 'medio',
            'high' => 'alto',
            'serious' => 'grave',
        ],
        'measure' => [
            'withdrawal' => 'Ritiro dal mercato',
            'recall' => 'Richiamo presso gli utilizzatori',
            'warning' => 'Avvertenza',
            'destruction' => 'Distruzione',
        ],
        'flash' => [
            'saved' => 'Dati salvati.',
        ],
    ],
];
