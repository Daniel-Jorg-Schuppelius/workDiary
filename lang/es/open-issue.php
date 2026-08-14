<?php
/*
 * Created on   : Fri Jun 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : open-issue.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Puntos abiertos',
        'show' => 'Punto abierto :title',
    ],
    'field' => [
        'title' => 'Título',
        'description' => 'Descripción',
        'category' => 'Categoría',
        'severity' => 'Gravedad',
        'status' => 'Estado',
        'assignee' => 'Asignado a',
        'creator' => 'Creado por',
        'due_at' => 'Fecha límite',
        'visibility' => 'Visibilidad',
        'closed_at' => 'Cerrado el',
        'closed_by' => 'Cerrado por',
        'reason' => 'Motivo',
        'resolution' => 'Resolución',
    ],
    'action' => [
        'create' => 'Crear punto abierto',
        'edit' => 'Editar',
        'assign' => 'Asignar',
        'start' => 'Poner en curso',
        'block' => 'Bloquear',
        'unblock' => 'Desbloquear',
        'complete' => 'Completar',
        'wontDo' => 'No se hará',
        'reopen' => 'Reabrir',
        'delete' => 'Eliminar',
        'publishToCustomer' => 'Compartir con el cliente',
    ],
    'flash' => [
        'created' => 'Punto abierto creado.',
        'updated' => 'Punto abierto actualizado.',
        'deleted' => 'Punto abierto eliminado.',
        'assigned' => 'Asignación actualizada.',
        'status' => [
            'open' => 'Punto abierto reabierto.',
            'inProgress' => 'El punto abierto está ahora en curso.',
            'blocked' => 'El punto abierto ha sido bloqueado.',
            'done' => 'El punto abierto ha sido completado.',
            'wontDo' => 'Punto abierto marcado como «no se hará».',
            'reopened' => 'El punto abierto ha sido reabierto.',
        ],
    ],
];
