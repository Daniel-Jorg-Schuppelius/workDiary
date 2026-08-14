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
];
