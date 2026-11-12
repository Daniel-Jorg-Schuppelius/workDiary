<?php
/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : diary.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'priority' => [
        'low' => 'Niedrig',
        'normal' => 'Normal',
        'high' => 'Hoch',
        'urgent' => 'Dringend',
    ],
    'location_mode' => [
        'onsite' => 'Vor Ort',
        'remote' => 'Remote',
        'hybrid' => 'Hybrid',
    ],
    'mode' => [
        'fixed' => 'Terminiert',
        'deadline' => 'Deadline',
        'window' => 'Zeitfenster',
        'recurring' => 'Wiederkehrend',
        'backlog' => 'Backlog',
    ],
    'status' => [
        'Planned' => 'Geplant',
        'Accepted' => 'Angenommen',
        'InProgress' => 'In Bearbeitung',
        'WaitingCustomer' => 'Wartet auf Rückmeldung',
        'WaitingMaterial' => 'Wartet auf Material',
        'Completed' => 'Abgeschlossen',
        'AcceptedFinal' => 'Abgenommen',
        'Invoiced' => 'Berechnet',
        'Cancelled' => 'Storniert',
    ],
    'planned_duration' => [
        'label' => 'Geplante Dauer (HH:MM)',
        'hint' => 'Leer: Länge des Zeitfensters bzw. Dauer des Termins. Gilt als Plan in Plan/Ist, Auftragstypanalyse und Personal-Kapazität.',
        'format' => 'Bitte geben Sie die geplante Dauer als Stunden:Minuten an, z. B. 1:30.',
        'range' => 'Die geplante Dauer muss zwischen 0:01 und 168:00 liegen.',
    ],
    // Kennzahl Plan/Ist am Auftrag (MVP-1552).
    'plan_actual' => [
        'label' => 'Plan/Ist',
        'no_plan' => 'ohne Plan',
        'own_only' => 'nur eigene Zeiten',
    ],
    // Auftrags-Lebenszyklus (MVP-1596).
    'lifecycle' => [
        'action' => [
            'reject' => 'Annahme zurückgeben',
            'revert_invoiced' => 'Berechnung zurücknehmen',
        ],
        'reject' => [
            'title' => 'Annahme zurückgeben',
            'legend' => 'Rückgabe',
            'hint' => 'Der Auftrag steht danach wieder auf „Geplant“ und kann von einer anderen Person angenommen werden. Die Begründung steht im Verlauf.',
        ],
        'handover' => [
            'legend' => 'Abnahme',
            'accepted_by' => 'Abgenommen von (Name beim Kunden)',
            'hint' => 'Das Protokoll trägt keine Kundenunterschrift. Bitte nennen Sie die Person, die beim Kunden abgenommen hat.',
        ],
        'accepted_final_by' => 'Abgenommen von :name am :date',
        'invoiced_with' => 'Berechnet mit :reference',
        'invoiced' => [
            'transfer_reference' => 'Übergabe Nr. :id',
        ],
        'cancel' => [
            'run_abort_reason' => 'Auftrag storniert: :reason',
        ],
        'error' => [
            'stopwatch_running' => 'Der Auftrag lässt sich erst abschließen, wenn keine Stoppuhr mehr läuft. Noch aktiv: :names.',
        ],
    ],
];
