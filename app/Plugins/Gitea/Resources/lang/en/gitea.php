<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : gitea.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Imports issues of selected repositories of a self-hosted Gitea or Forgejo instance as tasks into WorkDiary projects, read-only: title, description, open/closed and due date. Conflicts and cases to review go to the integration inbox.',
    ],
    'source' => 'Instance',
    'task_link' => 'Open in Gitea/Forgejo',
    'health' => [
        'not_configured' => 'Gitea/Forgejo is not set up (base URL or token missing).',
        'not_connected' => 'Gitea/Forgejo connection not checked yet.',
    ],
    'settings' => [
        'base_url' => 'Base URL',
        'base_url_help' => 'Address of the Gitea or Forgejo instance, e.g. https://git.example.com (without /api/v1).',
        'api_token' => 'Access token',
        'api_token_help' => 'Create it in Gitea/Forgejo under Settings → Applications, with read access to repositories and issues. It needs the scopes read:user, read:repository and read:issue.',
        'allow_private_network' => 'Allow private addresses',
        'allow_private_network_help' => 'Only for an instance in your own network: allows a base URL with a private or internal address. Logged, and only effective if the operator permits it.',
    ],
];
