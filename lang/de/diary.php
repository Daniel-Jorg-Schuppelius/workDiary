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
];
