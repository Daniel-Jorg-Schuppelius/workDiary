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
        'replace_hint' => '«:name» viene smontato e resta nello storico con la data di rimozione.',
        'label_hint' => 'Per componenti di terzi senza anagrafica articolo.',
        'interval_hint' => 'Intervallo di sostituzione in mesi — da qui la scadenza.',
        'action' => [
            'add' => 'Aggiungi componente',
            'replace' => 'Sostituisci',
            'remove' => 'Smonta',
        ],
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
];
