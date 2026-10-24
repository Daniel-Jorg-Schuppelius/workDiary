<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : plane.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Incorpora en modo de solo lectura los elementos de trabajo de proyectos de Plane seleccionados como tareas en proyectos de WorkDiary: título, descripción, estado según el grupo de estado y fecha objetivo. Los conflictos y casos por aclarar van a la bandeja de integración.',
    ],
    'source' => 'Espacio de trabajo',
    'task_link' => 'Abrir en Plane',
    'health' => [
        'not_configured' => 'Plane no está configurado (falta la dirección o el acceso).',
        'not_connected' => 'La conexión con Plane aún no se ha comprobado.',
    ],
    'settings' => [
        'base_url' => 'Dirección',
        'base_url_help' => 'Dirección de la API: https://api.plane.so para Plane Cloud; si no, la dirección de su propia instancia (sin /api/v1).',
        'workspace_slug' => 'Identificador del espacio de trabajo',
        'workspace_slug_help' => 'La parte de la dirección después del host, p. ej. «acme» en https://app.plane.so/acme/.',
        'api_key' => 'Clave de API',
        'api_key_help' => 'Créela en Plane en Ajustes del perfil → Tokens de acceso personal; ve los proyectos de los que el perfil es miembro. Requiere Plane 0.28 o posterior.',
        'allow_private_network' => 'Permitir direcciones privadas',
        'allow_private_network_help' => 'Solo para una instancia en su propia red: permite una dirección privada o interna. Se registra y solo surte efecto si el operador lo permite.',
    ],
];
