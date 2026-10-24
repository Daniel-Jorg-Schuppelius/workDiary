<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : leantime.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Takes over to-dos of selected Leantime projects as tasks in WorkDiary projects, read-only: title, description, status by status type and due date; milestones are left out. Conflicts and open cases go to the integration inbox.',
    ],
    'source' => 'Instance',
    'task_link' => 'Open in Leantime',
    'health' => [
        'not_configured' => 'Leantime is not set up (address or credentials missing).',
        'not_connected' => 'Leantime connection not checked yet.',
    ],
    'settings' => [
        'base_url' => 'Address',
        'base_url_help' => 'Address of the Leantime instance, e.g. https://leantime.example.com (without /api/jsonrpc).',
        'api_key' => 'API key',
        'api_key_help' => 'Create it in Leantime under Company settings → API and assign it to the projects to be taken over; the key is shown only once. Requires Leantime 3.8 or later.',
        'allow_private_network' => 'Allow private addresses',
        'allow_private_network_help' => 'Only for an instance in your own network: allows a private or internal address. Logged, and only effective if the operator permits it.',
    ],
];
