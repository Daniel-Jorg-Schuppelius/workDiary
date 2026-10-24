<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : freescout.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Takes over conversations of selected FreeScout mailboxes as tasks in WorkDiary projects, read-only: subject, first message as description and status. Conflicts and open cases go to the integration inbox.',
    ],
    'source' => 'Instance',
    'task_link' => 'Open in FreeScout',
    'health' => [
        'not_configured' => 'FreeScout is not set up (address or credentials missing).',
        'not_connected' => 'FreeScout connection not checked yet.',
    ],
    'settings' => [
        'base_url' => 'Address',
        'base_url_help' => 'Address of the FreeScout instance, e.g. https://support.example.com (without /api). The “API & Webhooks” module must be active.',
        'api_key' => 'API key',
        'api_key_help' => 'Generate it in FreeScout under Manage → API & Webhooks. The key applies to the whole instance.',
        'allow_private_network' => 'Allow private addresses',
        'allow_private_network_help' => 'Only for an instance in your own network: allows a private or internal address. Logged, and only effective if the operator permits it.',
    ],
];
