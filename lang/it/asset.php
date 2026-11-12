<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : asset.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'lifecycle' => [
        'in_operation' => 'In esercizio',
        'retired' => 'Sostituito',
        'decommissioned' => 'Dismesso',
    ],
    'dossier' => [
        'title' => 'Fascicolo oggetto',
        'back' => 'Torna all\'asset',
        'generated_at' => 'Generato il',
        'lifecycle' => 'Ciclo di vita',
        'master_data' => 'Dati anagrafici',
        'health' => 'Stato',
        'commissioned' => 'Messa in esercizio',
        'decommissioned' => 'Dismissione',
        'warranty' => 'Garanzia fino al',
        'warranty_expired' => 'scaduta',
        'in_service_days' => 'In esercizio (giorni)',
        'room_requirements' => 'Requisiti del locale',
        'maintenance' => 'Manutenzioni',
        'next_due' => 'Prossima scadenza',
        'last_run' => 'Ultima esecuzione',
        'due' => 'Scaduta',
        'scheduled' => 'Pianificata',
        'assignments' => 'Consegne / restituzioni',
        'checked_out' => 'Consegnato',
        'assignee' => 'Assegnatario',
        'returned' => 'Restituito',
        'open' => 'Aperto',
        'defects' => 'Difetti / blocchi',
        'blocks' => 'Blocca',
        'orders' => 'Ordini',
        'timeline' => 'Cronologia del ciclo di vita',
        'event' => [
            'asset.audit' => 'Evento asset',
            'order.linked' => 'Ordine collegato',
            'protocol.linked' => 'Protocollo collegato',
            'material.linked' => 'Uso materiale collegato',
            'attachment.linked' => 'Allegato aggiunto',
            'assignment.checkedOut' => 'Consegnato',
            'assignment.returned' => 'Restituito',
            'defect.reported' => 'Difetto segnalato',
            'defect.resolved' => 'Difetto risolto',
            'maintenance.completed' => 'Manutenzione eseguita',
            'unknown' => 'Evento',
        ],
    ],
    // Anlagen-Stückliste (Feature 118, MVP-607).
    'components' => [
        'title' => 'Distinta componenti',
        'stock_serial_none' => 'nessuno (pezzo di terzi)',
        'stock_serial_hint' => 'Solo i pezzi del proprio magazzino ne hanno uno.',
        'serial_no_hint' => 'Testo libero; viene sovrascritto se è collegato un numero di serie di magazzino.',
        'empty' => 'Nessun componente registrato.',
        'saved' => 'Componente registrato.',
        'replaced' => 'Componente sostituito — il precedente resta nello storico.',
        'removed' => 'Componente smontato.',
        'not_installed' => 'Questo componente non è più montato.',
        'foreign_organization' => 'Consumo di materiale e apparecchio appartengono a organizzazioni diverse.',
        'usage_of_other_asset' => 'Il consumo di materiale appartiene a un altro apparecchio.',
        'already_adopted' => 'Questo consumo di materiale è già nella distinta.',
        'adopted' => 'Materiale ripreso nella distinta.',
        'replace_hint' => '«:name» viene smontato e resta nello storico con la data di rimozione.',
        'label_hint' => 'Per componenti di terzi senza anagrafica articolo.',
        'interval_hint' => 'Intervallo di sostituzione in mesi — da qui la scadenza.',
        'action' => [
            'add' => 'Aggiungi componente',
            'replace' => 'Sostituisci',
            'remove' => 'Smonta',
            'adopt' => 'Riprendi',
        ],
        'adopt' => ['heading' => 'Materiale dagli interventi', 'hint' => 'Materiale assegnato a questo apparecchio su un foglio ore. «Riprendi» lo inserisce nella distinta con il giorno dell’intervento come data di montaggio.', 'column_date' => 'Intervento'],
        'due' => ['heading' => 'Componenti di usura in scadenza', 'hint' => 'Proposta per il prossimo intervento — decide il tecnico.'],
        'history' => ['heading' => 'Storico (componenti smontati e sostituiti)'],
        'column' => [
            'name' => 'Componente',
            'article' => 'Articolo',
            'label' => 'Denominazione (testo libero)',
            'position' => 'Posizione',
            'quantity' => 'Quantità',
            'unit' => 'Unità',
            'serial_no' => 'Numero di serie',
            'stock_serial' => 'Numero di serie di magazzino',
            'installed_on' => 'Montato il',
            'removed_on' => 'Smontato il',
            'due_on' => 'Sostituzione prevista',
            'interval' => 'Intervallo (mesi)',
            'status' => 'Stato',
            'note' => 'Nota',
        ],
        'status' => [
            'installed' => 'Montato',
            'removed' => 'Smontato',
            'replaced' => 'Sostituito',
        ],
    ],
    'label' => [
        'print' => 'Etichetta con QR',
    ],
    'nfc' => [
        'read' => 'Leggi tag NFC',
        'write' => 'Scrivi tag NFC',
    ],
    // Zustand, Außerbetriebnahme, Eigentümerwechsel, Rücknahme (MVP-1574).
    'health' => [
        'label' => 'Stato',
        'hint' => 'Derivato: critico con un blocco attivo o un difetto bloccante, limitato con un difetto aperto o manutenzione/ispezione scadute.',
    ],
    'decommission' => [
        'title' => 'Dismettere l\'asset',
        'action' => 'Dismettere',
        'intro' => 'L\'asset viene dismesso definitivamente. In seguito non può più essere consegnato, noleggiato, prenotato o pianificato; il fascicolo resta conservato.',
        'date' => 'Dismesso dal',
        'reason' => 'Motivo',
        'since' => 'Dismesso dal :date',
        'by_disposal' => 'Smaltito tramite un ordine di smaltimento.',
        'done' => 'Asset dismesso.',
        'plans' => 'Piani di manutenzione attivi: :count — verranno sospesi.',
        'duties' => 'Obblighi di ispezione attivi: :count — vengono sospesi, gli appuntamenti di ispezione aperti vengono annullati.',
        'error' => [
            'checked_out' => 'L\'asset è ancora consegnato. Lo ritiri prima.',
            'not_allowed' => 'L\'asset non può essere dismesso dallo stato «:status».',
        ],
    ],
    // Wiederinbetriebnahme (MVP-1628)
    'recommission' => [
        'title' => 'Rimettere in servizio l’asset',
        'action' => 'Rimetti in servizio',
        'intro' => 'L’asset torna attivo: può essere consegnato, prestato, prenotato e pianificato. I piani di manutenzione sospesi e gli obblighi di verifica inattivi restano disattivati: riattivarli uno per uno.',
        'reason' => 'Motivo della rimessa in servizio',
        'done' => 'Asset rimesso in servizio.',
        'error' => [
            'not_decommissioned' => 'Solo un asset dismesso può essere rimesso in servizio.',
            'disposed' => 'L’asset è stato smaltito tramite un ordine di smaltimento e resta fuori servizio.',
        ],
    ],
    'ownership' => [
        'title' => 'Cambiare proprietario',
        'action' => 'Cambiare proprietario',
        'current' => 'Attualmente: :owner',
        'owned_by' => 'Proprietà',
        'customer_hint' => 'Solo con proprietà «Cliente»; altrimenti il legame con il cliente viene rimosso.',
        'note' => 'Nota sul cambio di proprietario',
        'note_hint' => 'Viene salvata solo se cambia il cliente; il cambio compare poi nel fascicolo.',
        'done' => 'Cambio di proprietario registrato.',
        'error' => [
            'unchanged' => 'Proprietà e cliente sono invariati.',
            'forbidden' => 'Un altro cliente è un cambio di proprietario; non dispone del permesso necessario.',
        ],
    ],
    'return' => [
        'title' => 'Ritirare l\'asset',
        'condition_in' => 'Stato alla restituzione',
        'condition_placeholder' => 'ad es. completo, involucro graffiato',
    ],
    'timeline' => [
        'ownership_changed' => 'Proprietario cambiato',
        'health_changed' => 'Stato modificato',
        'blocked' => 'Bloccato',
        'unblocked' => 'Blocco rimosso',
    ],
    'block' => [
        'from_status' => 'Dallo stato «:status».',
        'status_left' => 'Stato modificato.',
    ],
    'guard' => [
        'out_of_service' => ':asset non è più in esercizio (:status) e non può essere utilizzato.',
    ],
    // Anschaffung an der Asset-Akte (MVP-1606).
    'acquisition' => [
        'title' => 'Acquisizione',
        'supplier' => 'Acquistato da',
        'date' => 'Data di acquisizione',
        'cost' => 'Costo di acquisizione',
    ],
];
