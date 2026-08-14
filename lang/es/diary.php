<?php
/*
 * Created on   : Fri Jun 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : diary.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'priority' => [
        'low' => 'Baja',
        'normal' => 'Normal',
        'high' => 'Alta',
        'urgent' => 'Urgente',
    ],
    'location_mode' => [
        'onsite' => 'En el sitio',
        'remote' => 'En remoto',
        'hybrid' => 'Híbrido',
    ],
    'mode' => [
        'fixed' => 'Planificado',
        'deadline' => 'Fecha límite',
        'window' => 'Ventana',
        'recurring' => 'Recurrente',
        'backlog' => 'Backlog',
    ],
    'status' => [
        'Planned' => 'Planificado',
        'Accepted' => 'Aceptado',
        'InProgress' => 'En curso',
        'WaitingCustomer' => 'Esperando respuesta',
        'WaitingMaterial' => 'Esperando material',
        'Completed' => 'Completado',
        'AcceptedFinal' => 'Recepcionado',
        'Invoiced' => 'Facturado',
        'Cancelled' => 'Cancelado',
    ],
];
