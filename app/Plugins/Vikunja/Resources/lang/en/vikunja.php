<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : vikunja.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Takes over tasks of selected Vikunja projects as tasks in WorkDiary projects, read-only: title, description, done and due date. Conflicts and open cases go to the integration inbox.',
    ],
    'source' => 'Instance',
    'task_link' => 'Open in Vikunja',
    'health' => [
        'not_configured' => 'Vikunja is not set up (address or credentials missing).',
        'not_connected' => 'Vikunja connection not checked yet.',
    ],
    'settings' => [
        'base_url' => 'Address',
        'base_url_help' => 'Address of the Vikunja instance, e.g. https://todo.example.com (without /api/v1).',
        'api_token' => 'API token',
        'api_token_help' => 'Create it in Vikunja under Settings → API Tokens, with read permission on projects and tasks. Requires Vikunja 1.0 or later; with the right “other → user” the check also shows the account name.',
        'allow_private_network' => 'Allow private addresses',
        'allow_private_network_help' => 'Only for an instance in your own network: allows a private or internal address. Logged, and only effective if the operator permits it.',
    ],
];
