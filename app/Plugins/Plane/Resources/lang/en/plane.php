<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : plane.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Takes over work items of selected Plane projects as tasks in WorkDiary projects, read-only: title, description, status by state group and target date. Conflicts and open cases go to the integration inbox.',
    ],
    'source' => 'Workspace',
    'task_link' => 'Open in Plane',
    'health' => [
        'not_configured' => 'Plane is not set up (address or credentials missing).',
        'not_connected' => 'Plane connection not checked yet.',
    ],
    'settings' => [
        'base_url' => 'Address',
        'base_url_help' => 'API address: https://api.plane.so for Plane Cloud, otherwise the address of your own instance (without /api/v1).',
        'workspace_slug' => 'Workspace slug',
        'workspace_slug_help' => 'The part of the address after the host, e.g. “acme” in https://app.plane.so/acme/.',
        'api_key' => 'API key',
        'api_key_help' => 'Create it in Plane under Profile settings → Personal access tokens; it sees the projects the profile is a member of. Requires Plane 0.28 or later.',
        'allow_private_network' => 'Allow private addresses',
        'allow_private_network_help' => 'Only for an instance in your own network: allows a private or internal address. Logged, and only effective if the operator permits it.',
    ],
];
