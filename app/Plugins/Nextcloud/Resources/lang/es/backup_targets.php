<?php
/*
 * Created on   : Wed Sep 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : backup_targets.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'nextcloud' => [
        'connect_title' => 'Conectar Nextcloud',
        'connect_legend' => 'Credenciales',
        'connect_submit' => 'Conectar',
        'field' => [
            'name' => 'Nombre',
            'server_url' => 'URL del servidor',
            'server_url_help' => 'Solo HTTPS. Ejemplo: https://cloud.example.com',
            'username' => 'Nombre de usuario',
            'app_password' => 'Contraseña de aplicación',
            'app_password_help' => 'Una contraseña de aplicación revocable (Ajustes › Seguridad), nunca la contraseña normal de la cuenta.',
        ],
        'validation' => [
            'https_required' => 'La URL del servidor debe comenzar con https://.',
            'unsafe_url' => 'La URL del servidor debe ser accesible públicamente (sin destino interno/privado).',
        ],
    ],
];
