<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : kanboard.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Incorpora en modo de solo lectura las tareas de proyectos de Kanboard seleccionados como tareas en proyectos de WorkDiary: título, descripción, abierta o cerrada y vencimiento. Los conflictos y casos por aclarar van a la bandeja de integración.',
    ],
    'source' => 'Instancia',
    'task_link' => 'Abrir en Kanboard',
    'health' => [
        'not_configured' => 'Kanboard no está configurado (falta la dirección o el acceso).',
        'not_connected' => 'La conexión con Kanboard aún no se ha comprobado.',
    ],
    'settings' => [
        'base_url' => 'Dirección',
        'base_url_help' => 'Dirección de la instancia de Kanboard, p. ej. https://kanban.example.com (sin /jsonrpc.php).',
        'username' => 'Nombre de usuario',
        'username_help' => 'Déjelo vacío para el acceso de aplicación (usuario «jsonrpc» con el token de Ajustes → API). Con un nombre de usuario se usa el token de acceso personal de ese usuario y solo sus proyectos.',
        'api_token' => 'Token de API',
        'api_token_help' => 'Token de aplicación de Ajustes → API o token de acceso personal del usuario.',
        'allow_private_network' => 'Permitir direcciones privadas',
        'allow_private_network_help' => 'Solo para una instancia en su propia red: permite una dirección privada o interna. Se registra y solo surte efecto si el operador lo permite.',
    ],
];
