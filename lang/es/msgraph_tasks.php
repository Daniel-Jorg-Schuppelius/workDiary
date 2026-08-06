<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : msgraph_tasks.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Sincronización de To Do (Feature 102, corte E): sección en el panel de Msgraph + mensajes del flujo.
return [
    'heading' => 'Sincronizar Microsoft To Do',
    'intro' => 'Sincroniza listas de To Do vinculadas con proyectos de WorkDiary (patrón Todoist): fusión de tres vías, los conflictos van a la bandeja de integraciones — nunca last-write-wins; los borrados remotos solo se marcan.',
    'badge_connected' => 'Conectado',
    'badge_inactive' => 'Desconectado',
    'account' => 'Cuenta conectada',
    'connect' => 'Conectar sincronización de To Do',
    'disconnect' => 'Desconectar sincronización de To Do',
    'link' => [
        'list' => 'Lista de To Do',
        'target' => 'Destino',
        'project' => 'Proyecto',
        'global' => 'Kanban global',
        'mode' => 'Dirección',
        'add' => 'Vincular',
        'remove' => 'Quitar',
        'remove_confirm' => '¿Quitar realmente esta vinculación? Las tareas y referencias ya sincronizadas se conservan.',
    ],
    'mode' => [
        'bidirectional' => 'Ambas direcciones',
        'todo_to_workdiary' => 'Solo To Do → WorkDiary',
        'workdiary_to_todo' => 'Solo WorkDiary → To Do',
    ],
    'flash' => [
        'not_configured' => 'Microsoft 365 no está configurado (faltan MSGRAPH_CLIENT_ID/SECRET).',
        'state_invalid' => 'El proceso de inicio de sesión caducó o no es válido — inícielo de nuevo.',
        'oauth_denied' => 'La autorización fue cancelada.',
        'oauth_failed' => 'La conexión falló (:class).',
        'connected' => 'Microsoft To Do conectado.',
        'disconnected' => 'Sincronización de To Do desconectada — tokens de acceso eliminados.',
        'no_connection' => 'No se ha establecido ninguna conexión con Microsoft To Do.',
        'list_invalid' => 'La lista de To Do seleccionada ya no está disponible.',
        'project_invalid' => 'El proyecto seleccionado no pertenece a esta organización.',
        'link_saved' => 'Vinculación de lista guardada.',
        'link_removed' => 'Vinculación de lista eliminada.',
    ],
];
