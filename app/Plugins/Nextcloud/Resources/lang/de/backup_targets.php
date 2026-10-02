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
        'connect_title' => 'Nextcloud verbinden',
        'connect_legend' => 'Zugangsdaten',
        'connect_submit' => 'Verbinden',
        'field' => [
            'name' => 'Name',
            'server_url' => 'Server-URL',
            'server_url_help' => 'Nur HTTPS. Beispiel: https://cloud.example.com',
            'username' => 'Benutzername',
            'app_password' => 'App-Passwort',
            'app_password_help' => 'Ein widerrufbares App-Passwort (Einstellungen › Sicherheit), nie das reguläre Kontopasswort.',
        ],
        'validation' => [
            'https_required' => 'Die Server-URL muss mit https:// beginnen.',
            'unsafe_url' => 'Die Server-URL muss öffentlich erreichbar sein (kein internes/privates Ziel).',
        ],
    ],
];
