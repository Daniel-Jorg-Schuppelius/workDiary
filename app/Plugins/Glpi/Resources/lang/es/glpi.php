<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : glpi.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Incorpora en modo de solo lectura los tickets de entidades de GLPI seleccionadas como tareas en proyectos de WorkDiary: título, descripción, estado y plazo de resolución. Los conflictos y casos por aclarar van a la bandeja de integración.',
    ],
    'source' => 'Instancia',
    'task_link' => 'Abrir en GLPI',
    'health' => [
        'not_configured' => 'GLPI no está configurado (falta la dirección o el acceso).',
        'not_connected' => 'La conexión con GLPI aún no se ha comprobado.',
    ],
    'settings' => [
        'base_url' => 'Dirección',
        'base_url_help' => 'Dirección de la instancia de GLPI, p. ej. https://glpi.example.com (sin /apirest.php). La API REST debe estar activada en GLPI en Configuración → General → API.',
        'app_token' => 'Token de aplicación',
        'app_token_help' => 'Solo es necesario si el cliente de API en GLPI exige un token de aplicación.',
        'user_token' => 'Token de usuario',
        'user_token_help' => 'Genérelo en GLPI en las preferencias del usuario en «Claves de acceso remoto» (token de API). Se ven las entidades y tickets de ese usuario.',
        'allow_private_network' => 'Permitir direcciones privadas',
        'allow_private_network_help' => 'Solo para una instancia en su propia red: permite una dirección privada o interna. Se registra y solo surte efecto si el operador lo permite.',
    ],
];
