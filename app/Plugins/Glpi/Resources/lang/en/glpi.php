<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : glpi.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Takes over tickets of selected GLPI entities as tasks in WorkDiary projects, read-only: title, description, status and time to resolve. Conflicts and open cases go to the integration inbox.',
    ],
    'source' => 'Instance',
    'task_link' => 'Open in GLPI',
    'health' => [
        'not_configured' => 'GLPI is not set up (address or credentials missing).',
        'not_connected' => 'GLPI connection not checked yet.',
    ],
    'settings' => [
        'base_url' => 'Address',
        'base_url_help' => 'Address of the GLPI instance, e.g. https://glpi.example.com (without /apirest.php). The REST API must be enabled in GLPI under Setup → General → API.',
        'app_token' => 'App token',
        'app_token_help' => 'Only needed if the API client in GLPI requires an app token.',
        'user_token' => 'User token',
        'user_token_help' => 'Generate it in GLPI in the user’s preferences under “Remote access keys” (API token). The entities and tickets of this user are visible.',
        'allow_private_network' => 'Allow private addresses',
        'allow_private_network_help' => 'Only for an instance in your own network: allows a private or internal address. Logged, and only effective if the operator permits it.',
    ],
];
