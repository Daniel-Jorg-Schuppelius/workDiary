<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : msgraph_tasks.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// To Do sync (Feature 102, slice E): section in the Msgraph admin panel + flow flashes.
return [
    'heading' => 'Sync Microsoft To Do',
    'intro' => 'Syncs linked To Do lists with WorkDiary projects (Todoist pattern): three-way merge, conflicts go to the integration inbox — never last-write-wins; remote deletions are only flagged.',
    'badge_connected' => 'Connected',
    'badge_inactive' => 'Disconnected',
    'account' => 'Connected account',
    'connect' => 'Connect To Do sync',
    'disconnect' => 'Disconnect To Do sync',
    'link' => [
        'list' => 'To Do list',
        'target' => 'Target',
        'project' => 'Project',
        'global' => 'Global kanban',
        'mode' => 'Direction',
        'add' => 'Link',
        'remove' => 'Remove',
        'remove_confirm' => 'Really remove this link? Already synced tasks and references are kept.',
    ],
    'mode' => [
        'bidirectional' => 'Both directions',
        'todo_to_workdiary' => 'To Do → WorkDiary only',
        'workdiary_to_todo' => 'WorkDiary → To Do only',
    ],
    'flash' => [
        'not_configured' => 'Microsoft 365 is not configured (MSGRAPH_CLIENT_ID/SECRET missing).',
        'state_invalid' => 'The sign-in process expired or is invalid — please start again.',
        'oauth_denied' => 'The consent was cancelled.',
        'oauth_failed' => 'The connection failed (:class).',
        'connected' => 'Microsoft To Do connected.',
        'disconnected' => 'To Do sync disconnected — access tokens removed.',
        'no_connection' => 'No Microsoft To Do connection established.',
        'list_invalid' => 'The selected To Do list is no longer available.',
        'project_invalid' => 'The selected project does not belong to this organization.',
        'link_saved' => 'List link saved.',
        'link_removed' => 'List link removed.',
    ],
];
