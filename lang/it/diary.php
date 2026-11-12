<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : diary.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'priority' => [
        'low' => 'Bassa',
        'normal' => 'Normale',
        'high' => 'Alta',
        'urgent' => 'Urgente',
    ],
    'location_mode' => [
        'onsite' => 'In sede',
        'remote' => 'Da remoto',
        'hybrid' => 'Ibrido',
    ],
    'mode' => [
        'fixed' => 'Pianificato',
        'deadline' => 'Scadenza',
        'window' => 'Finestra',
        'recurring' => 'Ricorrente',
        'backlog' => 'Backlog',
    ],
    'status' => [
        'Planned' => 'Pianificato',
        'Accepted' => 'Accettato',
        'InProgress' => 'In lavorazione',
        'WaitingCustomer' => 'In attesa di riscontro',
        'WaitingMaterial' => 'In attesa di materiale',
        'Completed' => 'Completato',
        'AcceptedFinal' => 'Collaudato',
        'Invoiced' => 'Fatturato',
        'Cancelled' => 'Annullato',
    ],
    'planned_duration' => [
        'label' => 'Durata prevista (HH:MM)',
        'hint' => 'Vuoto: durata della finestra oraria o dell’appuntamento. Vale come piano in Piano/effettivo, Analisi dei tipi di ordine e Capacità del personale.',
        'format' => 'Indichi la durata prevista in ore:minuti, ad es. 1:30.',
        'range' => 'La durata prevista deve essere compresa tra 0:01 e 168:00.',
    ],
    // Kennzahl Plan/Ist am Auftrag (MVP-1552).
    'plan_actual' => [
        'label' => 'Previsto/effettivo',
        'no_plan' => 'senza previsione',
        'own_only' => 'solo i propri tempi',
    ],
    // Auftrags-Lebenszyklus (MVP-1596).
    'lifecycle' => [
        'action' => [
            'reject' => 'Restituire l\'accettazione',
            'revert_invoiced' => 'Annullare la fatturazione',
        ],
        'reject' => [
            'title' => 'Restituire l\'accettazione',
            'legend' => 'Restituzione',
            'hint' => 'L\'ordine torna su «Pianificato» e può essere accettato da un\'altra persona. La motivazione resta nella cronologia.',
        ],
        'handover' => [
            'legend' => 'Collaudo',
            'accepted_by' => 'Collaudato da (nome presso il cliente)',
            'hint' => 'Il verbale non riporta la firma del cliente. Indichi la persona che ha collaudato il lavoro presso il cliente.',
        ],
        'accepted_final_by' => 'Collaudato da :name il :date',
        'invoiced_with' => 'Fatturato con :reference',
        'invoiced' => [
            'transfer_reference' => 'Trasferimento n. :id',
        ],
        'cancel' => [
            'run_abort_reason' => 'Ordine annullato: :reason',
        ],
        'error' => [
            'stopwatch_running' => 'L\'ordine può essere chiuso solo quando nessun cronometro è in funzione. Ancora attivo: :names.',
        ],
    ],
];
