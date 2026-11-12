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
    'planned_duration' => [
        'label' => 'Duración prevista (HH:MM)',
        'hint' => 'Vacío: duración de la franja horaria o de la cita. Cuenta como plan en Plan/real, Análisis de tipos de orden y Capacidad de personal.',
        'format' => 'Indique la duración prevista en horas:minutos, p. ej. 1:30.',
        'range' => 'La duración prevista debe estar entre 0:01 y 168:00.',
    ],
    // Kennzahl Plan/Ist am Auftrag (MVP-1552).
    'plan_actual' => [
        'label' => 'Previsto/real',
        'no_plan' => 'sin previsión',
        'own_only' => 'solo tiempos propios',
    ],
    // Auftrags-Lebenszyklus (MVP-1596).
    'lifecycle' => [
        'action' => [
            'reject' => 'Devolver la aceptación',
            'revert_invoiced' => 'Revertir la facturación',
        ],
        'reject' => [
            'title' => 'Devolver la aceptación',
            'legend' => 'Devolución',
            'hint' => 'La orden vuelve a «Planificado» y otra persona puede aceptarla. El motivo queda en el historial.',
        ],
        'handover' => [
            'legend' => 'Recepción',
            'accepted_by' => 'Recibido por (nombre en el cliente)',
            'hint' => 'El protocolo no lleva firma del cliente. Indique la persona que recibió el trabajo en el cliente.',
        ],
        'accepted_final_by' => 'Recibido por :name el :date',
        'invoiced_with' => 'Facturado con :reference',
        'invoiced' => [
            'transfer_reference' => 'Traspaso n.º :id',
        ],
        'cancel' => [
            'run_abort_reason' => 'Orden cancelada: :reason',
        ],
        'error' => [
            'stopwatch_running' => 'La orden solo puede cerrarse cuando no haya ningún cronómetro en marcha. Aún activo: :names.',
        ],
    ],
];
