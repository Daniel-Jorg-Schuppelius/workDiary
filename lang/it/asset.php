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
];
