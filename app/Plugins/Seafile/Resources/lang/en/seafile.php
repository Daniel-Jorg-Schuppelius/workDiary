<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : seafile.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Takes over files from a library of a self-hosted Seafile instance into the cloud document intake, read-only, and serves as an encrypted backup target. The intake rules route files to incoming invoices or the document storage.',
    ],
    'settings' => [
        'base_url' => 'Address',
        'base_url_help' => 'Address of the Seafile instance for the document intake, e.g. https://seafile.example.com (without /api2).',
        'api_token' => 'API token',
        'api_token_help' => 'Account token of a Seafile user with read access to the library. WorkDiary changes nothing in Seafile for the intake. The token is returned by POST /api2/auth-token/ with user name and password (with two-factor sign-in, also the header X-Seafile-OTP with the current code).',
        'allow_private_network' => 'Allow private addresses',
        'allow_private_network_help' => 'Only for an instance in your own network: allows a private or internal address. Logged, and only effective if the operator permits it.',
    ],
    'action' => [
        'connect_intake' => 'Connect Seafile',
    ],
    'connect' => [
        'title' => 'Connect Seafile',
        'legend' => 'Connection',
        'instance' => 'Instance from the plugin settings: :instance',
        'name' => 'Connection name',
        'hint' => 'Then choose the library, the root folder within it, and create rules. Encrypted libraries are not offered.',
        'submit' => 'Check connection',
        'not_configured' => 'First enter the address and API token in the plugin settings.',
        'to_settings' => 'Go to plugin settings',
    ],
    'container' => [
        'library' => 'Library',
    ],
    'flash' => [
        'not_configured' => 'Seafile is not set up: address or API token missing in the plugin settings.',
        'instance_changed' => 'The plugin settings point to a different Seafile instance than this connection. Disconnect it first; documents already taken over remain.',
    ],
    'health' => [
        'not_configured' => 'Seafile is not set up (address or token missing).',
        'not_connected' => 'No Seafile connection in the cloud document intake yet.',
        'attention' => 'The Seafile connection reports an error — see cloud document intake.',
        'backup_attention' => 'A Seafile backup target needs attention — see backup targets.',
    ],
    'backup' => [
        'connect_title' => 'Connect Seafile as backup target',
        'connect_legend' => 'Credentials',
        'connect_submit' => 'Connect',
        'name' => 'Name',
        'server_url' => 'Server URL',
        'server_url_help' => 'HTTPS only. Example: https://seafile.example.com',
        'api_token' => 'API token',
        'api_token_help' => 'Account token of a dedicated Seafile user for backups, with write access to the library. The token is returned by POST /api2/auth-token/ with user name and password (with two-factor sign-in, also the header X-Seafile-OTP with the current code).',
        'library' => 'Library',
        'library_help' => 'Name or ID of an existing, unencrypted library. WorkDiary only writes into its own folder within it.',
        'https_required' => 'The server URL must start with https://.',
        'unsafe_url' => 'The server URL must be publicly reachable (no internal/private target).',
        'library_not_found' => 'This library does not exist for the account. Create it in Seafile or check the name and sharing.',
        'library_encrypted' => 'This library is encrypted. Choose an unencrypted library — WorkDiary encrypts the backup itself.',
        'library_ambiguous' => 'Several libraries have this name. Enter the library ID instead.',
        'library_read_only' => 'The account may not write to this library. Choose a library with read and write permission.',
    ],
];
