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
        'connect_title' => 'Connect WebDAV target',
        'connect_legend' => 'Credentials',
        'connect_submit' => 'Connect and test',
        'selftest_hint' => 'On connecting, a test folder is created, a file written, read back and deleted again.',
        'field' => [
            'name' => 'Name',
            'server_url' => 'Collection URL',
            'server_url_help' => 'HTTPS only. The full WebDAV collection, e.g. https://dav.example.com/remote.php/dav/files/backup/',
            'username' => 'User name',
            'password' => 'Password',
            'password_help' => 'Preferably a dedicated, revocable access token rather than the account password.',
            'base_path' => 'Subfolder (optional)',
            'base_path_help' => 'Empty = directly in the collection. The pseudonym folder is created below it.',
        ],
        'validation' => [
            'https_required' => 'The collection URL must start with https://.',
            'unsafe_url' => 'The collection URL must be publicly reachable (no internal/private target).',
        ],
        'flash' => [
            'selftest_failed' => 'The connection test failed (:class). The target was not activated.',
        ],
    ],
];
