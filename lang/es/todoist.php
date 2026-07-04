<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : todoist.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'subtitle' => 'Sincronización de tareas con Todoist — solo proyectos asignados expresamente, conflictos a través de la bandeja de integración.',
    'task_link' => 'Abrir en Todoist',

    'connection' => [
        'title' => 'Conexión',
        'none' => 'Sin conexión con Todoist. Se establece exactamente una conexión por organización.',
        'privacy_note' => 'Con la conexión se transfieren a Todoist los títulos, descripciones, estados, vencimientos y responsables de las tareas asignadas, y se leen desde allí. No se solicitan permisos de borrado.',
        'connect' => 'Conectar con Todoist',
        'reconnect' => 'Renovar conexión',
        'disconnect' => 'Desconectar',
        'confirm_disconnect' => '¿Desconectar? Las asignaciones y referencias se conservan.',
        'account' => 'Cuenta',
        'connected_at' => 'Conectado desde',
        'last_sync' => 'Última sincronización',
        'sync_now' => 'Sincronizar ahora',
        'open_inbox' => 'Bandeja de integración',
    ],

    'status' => [
        'active' => 'Activa',
        'paused' => 'En pausa',
        'disconnected' => 'Desconectada',
    ],

    'links' => [
        'title' => 'Asignaciones de proyectos',
        'empty' => 'Aún no hay asignaciones de proyectos.',
        'add' => 'Asignar',
        'hint' => 'Las nuevas asignaciones comienzan como borrador — activación solo tras el preflight (sin importación completa desatendida).',
        'global_kanban' => 'Kanban global',
        'target_project' => 'Proyecto WorkDiary',
        'workdiary_project' => 'Proyecto WorkDiary',
        'preflight' => 'Preflight',
        'activate' => 'Activar',
        'pause' => 'Pausar',
        'remove' => 'Eliminar',
        'confirm_remove' => '¿Eliminar la asignación? Las referencias se conservan.',
        'col' => [
            'todoist_project' => 'Proyecto Todoist',
            'target' => 'Destino',
            'mode' => 'Dirección',
            'last_run' => 'Última ejecución',
            'actions' => 'Acciones',
        ],
    ],

    'mode' => [
        'todoist_to_workdiary' => 'Todoist → WorkDiary',
        'workdiary_to_todoist' => 'WorkDiary → Todoist',
        'bidirectional' => 'Bidireccional',
    ],

    'link_status' => [
        'draft' => 'Borrador',
        'active' => 'Activa',
        'paused' => 'En pausa',
    ],

    'preflight' => [
        'title' => 'Preflight',
        'counters' => 'Indicadores',
        'tasks' => 'Tareas activas',
        'subtasks' => 'Subtareas',
        'recurring' => 'Recurrentes',
        'timed_due' => 'Vencimiento con hora',
        'unassignable' => 'Responsables no asignables',
        'referenced' => 'Ya referenciadas',
        'hint' => 'Las tareas recurrentes y los vencimientos con hora solo se adoptan en modo lectura dirigido por Todoist. Por defecto: «asignar solo lo existente».',
        'collaborators' => 'Asignación de responsables',
        'suggestion' => 'Sugerencia',
        'unassign' => '— desasignar —',
        'no_collaborators' => 'No se encontraron colaboradores.',
        'sections' => 'Secciones → estado',
        'no_sections' => 'Este proyecto no tiene secciones.',
        'section_unmapped' => '— sin asignar (estado intacto) —',
        'section_open' => 'Abierta',
        'section_in_progress' => 'En curso',
        'col' => [
            'collaborator' => 'Colaborador de Todoist',
            'email' => 'Correo',
            'mapped' => 'Asignado',
            'assign' => 'Asignar',
        ],
    ],

    'flash' => [
        'not_configured' => 'Todoist no está configurado (faltan TODOIST_CLIENT_ID/SECRET).',
        'state_invalid' => 'Estado OAuth no válido o caducado — vuelva a conectar.',
        'oauth_denied' => 'La autorización fue cancelada.',
        'oauth_failed' => 'Falló el intercambio de token (:class).',
        'connected' => 'Todoist conectado.',
        'disconnected' => 'Conexión desconectada.',
        'link_saved' => 'Asignación guardada.',
        'link_removed' => 'Asignación eliminada.',
        'link_project_required' => 'Seleccione un proyecto de WorkDiary.',
        'no_connection' => 'Sin conexión activa con Todoist.',
        'sync_done' => 'Sincronización completa ejecutada.',
        'preflight_failed' => 'Preflight fallido (:class).',
        'sections_saved' => 'Asignaciones de secciones guardadas.',
        'collaborator_assigned' => 'Responsable asignado.',
        'collaborator_unassigned' => 'Asignación eliminada.',
        'collaborator_invalid' => 'Usuario no válido.',
    ],
];
