<?php
/*
 * Created on   : Wed Sep 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : cloud_intake.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'action' => [
        'connect_nextcloud' => 'Conectar Nextcloud',
    ],
    'field' => [
        'name' => 'Nombre',
    ],
    'nextcloud' => [
        'description' => 'Incorpora documentos de carpetas de Nextcloud supervisadas (WebDAV) — con reglas de carpeta, comprobante de entrega y bandeja de entrada para casos ambiguos.',
        'health' => [
            'no_org_context' => 'Sin contexto de organización (ejecución del sistema).',
            'attention' => 'Al menos una conexión de Nextcloud requiere atención (reautenticación/bloqueada).',
            'backup_attention' => 'El destino de copia de seguridad de Nextcloud necesita atención (reautenticación/bloqueado) — afecta a todas las organizaciones.',
            'ok' => 'Conexiones de Nextcloud en orden.',
            'error' => 'La comprobación de estado falló (:class).',
        ],
        'connect_title' => 'Conectar Nextcloud',
        'connect_legend' => 'Credenciales',
        'connect_submit' => 'Conectar',
        'field' => [
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
