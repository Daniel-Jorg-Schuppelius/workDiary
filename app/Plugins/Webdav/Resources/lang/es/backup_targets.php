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
    'webdav' => [
        'connect_title' => 'Conectar destino WebDAV',
        'connect_legend' => 'Credenciales',
        'connect_submit' => 'Conectar y probar',
        'selftest_hint' => 'Al conectar se crea una carpeta de prueba, se escribe un archivo, se vuelve a leer y se elimina.',
        'field' => [
            'name' => 'Nombre',
            'server_url' => 'URL de la colección',
            'server_url_help' => 'Solo HTTPS. La colección WebDAV completa, p. ej. https://dav.example.com/remote.php/dav/files/backup/',
            'username' => 'Nombre de usuario',
            'password' => 'Contraseña',
            'password_help' => 'Preferiblemente un token de acceso propio y revocable en lugar de la contraseña de la cuenta.',
            'base_path' => 'Subcarpeta (opcional)',
            'base_path_help' => 'Vacío = directamente en la colección. La carpeta seudónima se crea debajo.',
        ],
        'validation' => [
            'https_required' => 'La URL de la colección debe empezar por https://.',
            'unsafe_url' => 'La URL de la colección debe ser accesible públicamente (ningún destino interno/privado).',
        ],
        'flash' => [
            'selftest_failed' => 'La prueba de conexión ha fallado (:class). El destino no se ha activado.',
        ],
    ],
];
