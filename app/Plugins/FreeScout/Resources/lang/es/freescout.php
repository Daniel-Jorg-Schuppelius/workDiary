<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : freescout.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Incorpora en modo de solo lectura las conversaciones de buzones de FreeScout seleccionados como tareas en proyectos de WorkDiary: asunto, primer mensaje como descripción y estado. Los conflictos y casos por aclarar van a la bandeja de integración.',
    ],
    'source' => 'Instancia',
    'task_link' => 'Abrir en FreeScout',
    'health' => [
        'not_configured' => 'FreeScout no está configurado (falta la dirección o el acceso).',
        'not_connected' => 'La conexión con FreeScout aún no se ha comprobado.',
    ],
    'settings' => [
        'base_url' => 'Dirección',
        'base_url_help' => 'Dirección de la instancia de FreeScout, p. ej. https://support.example.com (sin /api). El módulo «API & Webhooks» debe estar activo.',
        'api_key' => 'Clave de API',
        'api_key_help' => 'Genérela en FreeScout en Administrar → API & Webhooks. La clave vale para toda la instancia.',
        'allow_private_network' => 'Permitir direcciones privadas',
        'allow_private_network_help' => 'Solo para una instancia en su propia red: permite una dirección privada o interna. Se registra y solo surte efecto si el operador lo permite.',
    ],
];
