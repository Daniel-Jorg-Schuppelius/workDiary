<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : todoist.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'subtitle' => 'Task synchronisation with Todoist — only explicitly mapped projects, conflicts via the integration inbox.',
    'task_link' => 'Open in Todoist',

    'connection' => [
        'title' => 'Connection',
        'none' => 'No Todoist connection. Exactly one connection is established per organisation.',
        'privacy_note' => 'Connecting transfers titles, descriptions, status, due dates and assignees of mapped tasks to Todoist and reads them from there. Delete scopes are not requested.',
        'connect' => 'Connect to Todoist',
        'reconnect' => 'Renew connection',
        'disconnect' => 'Disconnect',
        'confirm_disconnect' => 'Disconnect? Mappings and references are kept.',
        'account' => 'Account',
        'connected_at' => 'Connected since',
        'last_sync' => 'Last sync',
        'sync_now' => 'Sync now',
        'open_inbox' => 'Integration inbox',
    ],

    'status' => [
        'active' => 'Active',
        'paused' => 'Paused',
        'disconnected' => 'Disconnected',
    ],

    'links' => [
        'title' => 'Project mappings',
        'empty' => 'No project mappings yet.',
        'add' => 'Map',
        'hint' => 'New mappings start as draft — activation only after preflight (no unattended full import).',
        'global_kanban' => 'Global kanban',
        'target_project' => 'WorkDiary project',
        'workdiary_project' => 'WorkDiary project',
        'preflight' => 'Preflight',
        'activate' => 'Activate',
        'pause' => 'Pause',
        'remove' => 'Remove',
        'confirm_remove' => 'Remove mapping? References are kept.',
        'col' => [
            'todoist_project' => 'Todoist project',
            'target' => 'Target',
            'mode' => 'Direction',
            'last_run' => 'Last run',
            'actions' => 'Actions',
        ],
    ],

    'mode' => [
        'todoist_to_workdiary' => 'Todoist → WorkDiary',
        'workdiary_to_todoist' => 'WorkDiary → Todoist',
        'bidirectional' => 'Bidirectional',
    ],

    'link_status' => [
        'draft' => 'Draft',
        'active' => 'Active',
        'paused' => 'Paused',
    ],

    'preflight' => [
        'title' => 'Preflight',
        'counters' => 'Counters',
        'tasks' => 'Active tasks',
        'subtasks' => 'Subtasks',
        'recurring' => 'Recurring',
        'timed_due' => 'Due with time',
        'unassignable' => 'Unassignable assignees',
        'referenced' => 'Already referenced',
        'hint' => 'Recurring tasks and timed due dates are only taken over in Todoist-led read mode. Default is “map existing only”.',
        'collaborators' => 'Assignee mapping',
        'suggestion' => 'Suggestion',
        'unassign' => '— unassign —',
        'no_collaborators' => 'No collaborators found.',
        'sections' => 'Sections → status',
        'no_sections' => 'This project has no sections.',
        'section_unmapped' => '— unmapped (status untouched) —',
        'section_open' => 'Open',
        'section_in_progress' => 'In progress',
        'col' => [
            'collaborator' => 'Todoist collaborator',
            'email' => 'Email',
            'mapped' => 'Mapped',
            'assign' => 'Assign',
        ],
    ],

    'flash' => [
        'not_configured' => 'Todoist is not configured (TODOIST_CLIENT_ID/SECRET missing).',
        'state_invalid' => 'Invalid or expired OAuth state — please connect again.',
        'oauth_denied' => 'Authorisation was cancelled.',
        'oauth_failed' => 'Token exchange failed (:class).',
        'connected' => 'Todoist connected.',
        'disconnected' => 'Connection disconnected.',
        'link_saved' => 'Mapping saved.',
        'link_removed' => 'Mapping removed.',
        'link_project_required' => 'Please choose a WorkDiary project.',
        'no_connection' => 'No active Todoist connection.',
        'sync_done' => 'Full sync started.',
        'preflight_failed' => 'Preflight failed (:class).',
        'sections_saved' => 'Section mappings saved.',
        'collaborator_assigned' => 'Assignee mapped.',
        'collaborator_unassigned' => 'Mapping removed.',
        'collaborator_invalid' => 'Invalid user.',
    ],
];
