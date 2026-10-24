<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : asana.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Imports tasks of selected Asana projects into WorkDiary projects, read-only: title, notes, completion and due date. Conflicts and cases to review go to the integration inbox.',
    ],
    'source' => 'Workspace',
    'task_link' => 'Open in Asana',
    'health' => [
        'not_configured' => 'Asana is not configured for this installation (the publisher’s app registration is missing).',
        'not_connected' => 'No Asana connection with a selected workspace.',
    ],
];
