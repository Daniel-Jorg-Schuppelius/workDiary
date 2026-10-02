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
        'connect_title' => 'Connect Nextcloud',
        'connect_legend' => 'Credentials',
        'connect_submit' => 'Connect',
        'field' => [
            'name' => 'Name',
            'server_url' => 'Server URL',
            'server_url_help' => 'HTTPS only. Example: https://cloud.example.com',
            'username' => 'Username',
            'app_password' => 'App password',
            'app_password_help' => 'A revocable app password (Settings › Security), never the regular account password.',
        ],
        'validation' => [
            'https_required' => 'The server URL must start with https://.',
            'unsafe_url' => 'The server URL must be publicly reachable (no internal/private target).',
        ],
    ],
];
