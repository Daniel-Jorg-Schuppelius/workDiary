<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : leantime.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Incorpora en modo de solo lectura las tareas de proyectos de Leantime seleccionados como tareas en proyectos de WorkDiary: título, descripción, estado según el tipo de estado y vencimiento; los hitos quedan fuera. Los conflictos y casos por aclarar van a la bandeja de integración.',
    ],
    'source' => 'Instancia',
    'task_link' => 'Abrir en Leantime',
    'health' => [
        'not_configured' => 'Leantime no está configurado (falta la dirección o el acceso).',
        'not_connected' => 'La conexión con Leantime aún no se ha comprobado.',
    ],
    'settings' => [
        'base_url' => 'Dirección',
        'base_url_help' => 'Dirección de la instancia de Leantime, p. ej. https://leantime.example.com (sin /api/jsonrpc).',
        'api_key' => 'Clave de API',
        'api_key_help' => 'Créela en Leantime en Ajustes de la empresa → API y asígnela a los proyectos que se van a incorporar; la clave solo se muestra una vez. Requiere Leantime 3.8 o posterior.',
        'allow_private_network' => 'Permitir direcciones privadas',
        'allow_private_network_help' => 'Solo para una instancia en su propia red: permite una dirección privada o interna. Se registra y solo surte efecto si el operador lo permite.',
    ],
];
