<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : gitea.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Importa incidencias de repositorios seleccionados de una instancia autoalojada de Gitea o Forgejo como tareas en proyectos de WorkDiary, solo lectura: título, descripción, abierta/cerrada y vencimiento. Los conflictos y casos por revisar van a la bandeja de entrada de integraciones.',
    ],
    'source' => 'Instancia',
    'task_link' => 'Abrir en Gitea/Forgejo',
    'health' => [
        'not_configured' => 'Gitea/Forgejo no está configurado (falta la URL base o el token).',
        'not_connected' => 'La conexión con Gitea/Forgejo aún no se ha comprobado.',
    ],
    'settings' => [
        'base_url' => 'URL base',
        'base_url_help' => 'Dirección de la instancia de Gitea o Forgejo, p. ej. https://git.example.com (sin /api/v1).',
        'api_token' => 'Token de acceso',
        'api_token_help' => 'Créelo en Gitea/Forgejo en Configuración → Aplicaciones, con acceso de lectura a repositorios e incidencias. Necesita los ámbitos read:user, read:repository y read:issue.',
        'allow_private_network' => 'Permitir direcciones privadas',
        'allow_private_network_help' => 'Solo para una instancia en su propia red: permite una URL base con una dirección privada o interna. Se registra y solo surte efecto si el operador lo permite.',
    ],
];
