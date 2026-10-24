<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : vikunja.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Incorpora en modo de solo lectura las tareas de proyectos de Vikunja seleccionados como tareas en proyectos de WorkDiary: título, descripción, hecho y vencimiento. Los conflictos y casos por aclarar van a la bandeja de integración.',
    ],
    'source' => 'Instancia',
    'task_link' => 'Abrir en Vikunja',
    'health' => [
        'not_configured' => 'Vikunja no está configurado (falta la dirección o el acceso).',
        'not_connected' => 'La conexión con Vikunja aún no se ha comprobado.',
    ],
    'settings' => [
        'base_url' => 'Dirección',
        'base_url_help' => 'Dirección de la instancia de Vikunja, p. ej. https://todo.example.com (sin /api/v1).',
        'api_token' => 'Token de API',
        'api_token_help' => 'Créelo en Vikunja en Ajustes → Tokens de API, con permiso de lectura sobre proyectos y tareas. Requiere Vikunja 1.0 o posterior; con el permiso «other → user», la comprobación muestra también el nombre de la cuenta.',
        'allow_private_network' => 'Permitir direcciones privadas',
        'allow_private_network_help' => 'Solo para una instancia en su propia red: permite una dirección privada o interna. Se registra y solo surte efecto si el operador lo permite.',
    ],
];
