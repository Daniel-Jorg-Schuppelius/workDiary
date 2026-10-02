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
        'connect_title' => 'WebDAV-Ziel verbinden',
        'connect_legend' => 'Zugangsdaten',
        'connect_submit' => 'Verbinden und testen',
        'selftest_hint' => 'Beim Verbinden wird ein Testordner angelegt, eine Datei geschrieben, zurückgelesen und wieder gelöscht.',
        'field' => [
            'name' => 'Name',
            'server_url' => 'Collection-URL',
            'server_url_help' => 'Nur HTTPS. Die vollständige WebDAV-Collection, z. B. https://dav.example.com/remote.php/dav/files/backup/',
            'username' => 'Benutzername',
            'password' => 'Passwort',
            'password_help' => 'Vorzugsweise ein eigenes, widerrufbares Zugangs-Token statt des Kontopassworts.',
            'base_path' => 'Unterordner (optional)',
            'base_path_help' => 'Leer = direkt in der Collection. Der Pseudonym-Ordner wird darunter angelegt.',
        ],
        'validation' => [
            'https_required' => 'Die Collection-URL muss mit https:// beginnen.',
            'unsafe_url' => 'Die Collection-URL muss öffentlich erreichbar sein (kein internes/privates Ziel).',
        ],
        'flash' => [
            'selftest_failed' => 'Der Verbindungstest ist fehlgeschlagen (:class). Das Ziel wurde nicht aktiviert.',
        ],
    ],
];
