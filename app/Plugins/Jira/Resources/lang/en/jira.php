<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : jira.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Imports issues of selected Jira Cloud projects as tasks into WorkDiary projects, read-only: title, description, status via the status category and due date. Conflicts and cases to review go to the integration inbox.',
    ],
    'source' => 'Site',
    'task_link' => 'Open in Jira',
    'health' => [
        'not_configured' => 'Jira is not configured for this installation (the publisher’s app registration is missing).',
        'not_connected' => 'No Jira connection with a selected site.',
    ],
];
