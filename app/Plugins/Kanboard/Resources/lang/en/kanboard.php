<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : kanboard.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Takes over tasks of selected Kanboard projects as tasks in WorkDiary projects, read-only: title, description, open or closed and due date. Conflicts and open cases go to the integration inbox.',
    ],
    'source' => 'Instance',
    'task_link' => 'Open in Kanboard',
    'health' => [
        'not_configured' => 'Kanboard is not set up (address or credentials missing).',
        'not_connected' => 'Kanboard connection not checked yet.',
    ],
    'settings' => [
        'base_url' => 'Address',
        'base_url_help' => 'Address of the Kanboard instance, e.g. https://kanban.example.com (without /jsonrpc.php).',
        'username' => 'Username',
        'username_help' => 'Leave empty for application access (user “jsonrpc” with the token from Settings → API). With a username, the personal access token of that user applies, and only their projects.',
        'api_token' => 'API token',
        'api_token_help' => 'Application token from Settings → API or the user’s personal access token.',
        'allow_private_network' => 'Allow private addresses',
        'allow_private_network_help' => 'Only for an instance in your own network: allows a private or internal address. Logged, and only effective if the operator permits it.',
    ],
];
