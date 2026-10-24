<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : taiga.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Incorpora en modo de solo lectura las historias de usuario de proyectos de Taiga seleccionados como tareas en proyectos de WorkDiary: título, descripción, abierta o cerrada y vencimiento. Los conflictos y casos por aclarar van a la bandeja de integración.',
    ],
    'source' => 'Instancia',
    'task_link' => 'Abrir en Taiga',
    'health' => [
        'not_configured' => 'Taiga no está configurado (falta la dirección o el acceso).',
        'not_connected' => 'La conexión con Taiga aún no se ha comprobado.',
    ],
    'settings' => [
        'base_url' => 'Dirección',
        'base_url_help' => 'Dirección de la API: https://api.taiga.io para Taiga Cloud; si no, la dirección de su propia instancia (sin /api/v1).',
        'username' => 'Nombre de usuario',
        'username_help' => 'Nombre de usuario o dirección de correo de una cuenta con acceso de lectura a los proyectos; idealmente una cuenta propia para la integración. La cuenta necesita en los proyectos el permiso para ver historias de usuario.',
        'password' => 'Contraseña',
        'password_help' => 'Taiga no emite tokens de API permanentes; la integración inicia sesión de nuevo en cada ejecución. Las cuentas que inician sesión con GitHub o GitLab necesitan su propia contraseña.',
        'allow_private_network' => 'Permitir direcciones privadas',
        'allow_private_network_help' => 'Solo para una instancia en su propia red: permite una dirección privada o interna. Se registra y solo surte efecto si el operador lo permite.',
    ],
];
