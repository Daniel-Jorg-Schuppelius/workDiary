<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : dispatch.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'heading' => 'Planificación',
    'badge_prefix' => 'Planificación',
    'set_status' => ':status',
    'override_reason' => 'Motivo de la anulación',
    'override_placeholder' => '¿Por qué confirmar a pesar del conflicto?',
    'conflicts' => [
        'hard' => 'Conflictos bloqueantes',
        'soft' => 'Advertencias',
        'none' => 'Sin conflictos para esta asignación.',
    ],
    'vehicle' => [
        'heading' => 'Reserva de vehículo',
        'label' => 'Vehículo',
        'from' => 'Desde',
        'to' => 'Hasta',
        'reserve' => 'Reservar',
        'release' => 'Liberar',
        'none' => 'Sin reserva de vehículo para este pedido.',
    ],
    'reservations' => [
        'title' => 'Reservas de vehículos',
        'subtitle' => 'Gestionar las reservas por vehículo.',
        'all_vehicles' => 'Todos los vehículos',
        'reserved_by' => 'Reservado por',
        'empty' => 'No hay reservas disponibles.',
    ],
];
