<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : claims.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'pattern' => [
        'title' => 'Schemi rilevanti (difetti di serie, lotti)',
        'hint' => 'Gruppi con almeno :threshold reclami nel periodo. Un’indicazione, non una decisione.',
        'none' => 'Nessuno schema rilevante nel periodo.',
        'rule_label' => 'Regola',
        'group' => 'Gruppo',
        'cases' => 'Casi',
        'count' => 'Numero',
        'rule' => [
            'lot' => 'Lotto',
            'article_defect' => 'Articolo × tipo di difetto',
            'article_cause' => 'Articolo × causa',
            'supplier_defect' => 'Fornitore × tipo di difetto',
            'entry_type_cause' => 'Tipo di ordine × causa',
        ],
        'notify_title' => 'Schema di reclami rilevante: :label',
        'notify_message' => ':count reclami in :days giorni (:rule).',
    ],
    // Lagerbezug der Rückläufer (MVP-1576).
    'rma' => [
        'group_return' => 'Reso',
        'group_receipt' => 'Entrata merci',
        'group_stock' => 'Riferimento di magazzino',
        'hint_stock' => 'Con magazzino, variante e quantità, l’entrata merci registra la giacenza in quarantena. Senza variante il reso viene solo documentato.',
        'hint_warehouse' => 'Necessario per la registrazione di magazzino non appena è scelta una variante.',
        'hint_serial' => 'La selezione imposta la variante e la quantità 1.',
        'option' => ['no_stock' => '— senza articolo di magazzino —'],
        'without_stock' => 'senza riferimento di magazzino — nessuna registrazione di giacenza',
        'no_stock_module' => 'Il modulo magazzino non è attivo. Il reso viene registrato senza movimento di giacenza.',
        'disposition_books' => 'La decisione trasferisce la giacenza fuori dalla quarantena o la scarica.',
        'disposition_documents' => 'Questo reso non ha un riferimento di magazzino. La decisione viene registrata senza movimento di giacenza.',
        'flash' => [
            'received_booked' => 'Entrata merci registrata in quarantena (:state).',
            'received_unbooked' => 'Entrata merci registrata senza movimento di giacenza (nessun articolo di magazzino o modulo magazzino non attivo).',
            'disposition_booked' => 'Decisione di destinazione registrata.',
            'disposition_unbooked' => 'Decisione di destinazione registrata senza movimento di giacenza.',
        ],
        'error' => [
            'serial_variant' => 'Il numero di serie appartiene a un’altra variante di articolo.',
            'serial_qty' => 'Un reso con numero di serie ha quantità 1.',
            'lot_variant' => 'Il lotto appartiene a un’altra variante di articolo.',
        ],
    ],
    // Bezugsobjekte, Einstieg, Finanzfolge und Regress der Akte (MVP-1577).
    'form' => [
        'title_edit' => 'Modifica reclamo',
        'lot_article_mismatch' => 'Il lotto appartiene a un altro articolo.',
        'group_links' => 'Oggetti interessati',
        'hint_links' => 'Colleghi ciò a cui si riferisce il reclamo. Con un cliente selezionato, gli elenchi mostrano solo i suoi oggetti.',
        'hint_serial' => 'Un numero di serie noto a magazzino viene collegato alla pratica.',
        'serial_linked' => 'Il numero di serie è noto a magazzino ed è collegato.',
    ],
    'open' => [
        'action' => 'Apri reclamo',
    ],
    'financial' => [
        'hint_invoice_local' => 'Nota di credito, storno, riduzione del prezzo e correzione nascono da questa fattura. Lo storno richiede una fattura emessa e non pagata, la nota di credito una fattura pagata.',
        'hint_invoice_external' => 'Le fatture di questo cliente sono gestite in un sistema esterno. Il documento di rettifica viene creato lì; ne riporti poi qui il numero.',
        'source_invoice' => 'per la fattura :number',
        'error' => [
            'invoice_required' => 'Selezioni la fattura da cui deve nascere il documento di rettifica.',
            'not_cancellable' => 'La fattura :number non può essere stornata. Lo storno richiede una fattura emessa e non pagata.',
            'not_creditable' => 'Per la fattura :number non è possibile creare una nota di credito. Deve essere pagata e non avere ancora una rettifica.',
            'not_rejectable' => 'Si possono rifiutare solo le conseguenze proposte.',
        ],
        'reject' => 'Rifiutare',
        'reject_confirm' => 'Rifiutare la proposta? Si conclude senza documento; resta possibile una nuova proposta.',
        'flash' => [
            'rejected' => 'Proposta rifiutata.',
        ],
    ],
    'recourse' => [
        'group_claim' => 'Richiesta',
        'group_reference' => 'Riferimento',
        'hint_due_days' => 'Senza indicazione valgono :days giorni dalla presentazione.',
        'hint_terms' => 'Ad esempio durata della garanzia, esclusioni o termine di denuncia secondo il contratto di fornitura.',
        'field_incoming_invoice' => 'Fattura in entrata',
        'due_days' => 'Termine di risposta: :days giorni dalla presentazione',
    ],
    // Retourenlabel einer RMA (MVP-917).
    'return_label' => [
        'title' => 'Etichetta di reso',
        'create' => 'Crea etichetta di reso',
        'download' => 'Scarica etichetta',
        'created' => 'Etichetta di reso creata (spedizione :tracking).',
        'no_address' => 'L\'etichetta di reso richiede l\'indirizzo del cliente (via, CAP, città).',
    ],
    // Retourenanmeldung im Kundenportal (MVP-935).
    'portal_return' => [
        'list' => [
            'claim' => 'Reclamo',
            'empty' => 'Nessun reso ancora registrato.',
            'rma' => 'Numero di reso',
            'status' => 'Stato',
            'title' => 'I miei resi',
        ],
        'capability' => 'Registrare un reso',
        'nav' => 'Registrare un reso',
        'title' => 'Registrare un reso',
        'intro' => 'Scelga la consegna o l\'oggetto, descriva il motivo e alleghi foto se necessario. Riceverà un numero di reso; se serve forniamo un\'etichetta di reso.',
        'empty' => 'Non ci sono consegne né oggetti per il suo account.',
        'submit' => 'Registra il reso',
        'label' => 'Scarica l\'etichetta di reso',
        'field' => [
            'delivery' => 'Consegna',
            'asset' => 'Oggetto',
            'serial_no' => 'Numero di serie',
            'quantity' => 'Quantità',
            'title' => 'Descrizione breve',
            'description' => 'Motivo del reso',
            'photos' => 'Foto o documenti (max. 5)',
        ],
        'flash' => [
            'submitted' => 'Reso registrato: reclamo :number, numero di reso :rma.',
        ],
        'error' => [
            'subject' => 'Scelga una consegna o un oggetto.',
            'serial' => 'Questo numero di serie non appartiene alla consegna scelta.',
        ],
    ],
    // Nachreichungen aus dem Kundenportal.
    'portal_note' => [
        'history' => 'Le Sue integrazioni successive',
        'notification_title' => 'Integrazione successiva al reclamo :number',
    ],
    'action' => [
        'follow_up_order' => 'Ordine di follow-up',
        'follow_up_none' => '— nessun ordine di follow-up —',
    ],
];
