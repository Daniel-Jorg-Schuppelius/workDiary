<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : taiga.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Takes over user stories of selected Taiga projects as tasks in WorkDiary projects, read-only: title, description, open or closed and due date. Conflicts and open cases go to the integration inbox.',
    ],
    'source' => 'Instance',
    'task_link' => 'Open in Taiga',
    'health' => [
        'not_configured' => 'Taiga is not set up (address or credentials missing).',
        'not_connected' => 'Taiga connection not checked yet.',
    ],
    'settings' => [
        'base_url' => 'Address',
        'base_url_help' => 'API address: https://api.taiga.io for Taiga Cloud, otherwise the address of your own instance (without /api/v1).',
        'username' => 'Username',
        'username_help' => 'Username or email address of an account with read access to the projects; ideally a dedicated account for the integration. The account needs the right to view user stories in the projects.',
        'password' => 'Password',
        'password_help' => 'Taiga issues no permanent API tokens; the integration signs in again for each run. Accounts that sign in via GitHub or GitLab need their own password.',
        'allow_private_network' => 'Allow private addresses',
        'allow_private_network_help' => 'Only for an instance in your own network: allows a private or internal address. Logged, and only effective if the operator permits it.',
    ],
];
