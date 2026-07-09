<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : maintenance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'window' => [
        'title' => 'Ventanas de mantenimiento',
        'subtitle' => 'Anunciar, iniciar, prolongar y cerrar de forma trazable las paradas planificadas.',
        'read_only_message' => 'Mantenimiento: la aplicación está temporalmente en solo lectura.',
        'scope' => [
            'system' => 'A nivel de instalación',
            'organization' => 'Solo esta organización',
        ],
        'mode' => [
            'full' => 'Bloqueo total',
            'read_only' => 'Solo lectura',
            'block_ingest' => 'Ingesta bloqueada',
            'read_only_toggle' => 'Modo solo lectura (la consulta sigue siendo posible)',
            'block_ingest_toggle' => 'Bloquear la ingesta de terminal/CTI/ubicación durante el mantenimiento',
        ],
        'status' => [
            'planned' => 'Planificada',
            'announced' => 'Anunciada',
            'active' => 'Activa',
            'extended' => 'Prolongada',
            'completed' => 'Completada',
            'rolled_back' => 'Rollback',
            'cancelled' => 'Cancelada',
        ],
        'field' => [
            'window' => 'Franja horaria',
            'scope' => 'Ámbito',
            'mode' => 'Modo',
            'status' => 'Estado',
            'actions' => 'Acciones',
            'announce_from' => 'Anunciar desde',
            'starts_at' => 'Inicio',
            'ends_at' => 'Fin',
            'message' => 'Texto informativo',
        ],
        'action' => [
            'plan' => 'Planificar ventana de mantenimiento',
            'save' => 'Planificar',
            'announce' => 'Anunciar',
            'start' => 'Iniciar ahora',
            'complete' => 'Finalizar',
            'extend' => 'Prolongar',
            'rollback' => 'Rollback',
            'cancel' => 'Cancelar',
        ],
        'banner' => [
            'upcoming' => 'Mantenimiento planificado: :from a :to — guarde su trabajo a tiempo.',
            'read_only' => 'Mantenimiento activo hasta :to — los cambios no son posibles temporalmente.',
        ],
        'hint' => [
            'message' => 'Opcional: ¿qué se mantiene, qué se puede esperar?',
        ],
        'empty' => [
            'title' => 'Sin ventanas de mantenimiento',
            'message' => 'No hay ventanas de mantenimiento planificadas.',
        ],
        'flash' => [
            'planned' => 'Ventana de mantenimiento planificada.',
            'announce' => 'Ventana de mantenimiento anunciada.',
            'start' => 'Ventana de mantenimiento iniciada.',
            'complete' => 'Ventana de mantenimiento finalizada.',
            'extend' => 'Ventana de mantenimiento prolongada.',
            'rollback' => 'Mantenimiento cerrado como rollback.',
            'cancel' => 'Ventana de mantenimiento cancelada.',
        ],
    ],
];
