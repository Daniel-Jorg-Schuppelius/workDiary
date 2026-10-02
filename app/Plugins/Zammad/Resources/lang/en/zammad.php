<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : zammad.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Zammad',
    'intro' => 'Tickets from a mapped Zammad group arrive as tasks in WorkDiary — for time tracking, records and billing. The ticket system stays authoritative; re-importing never creates duplicates.',

    'health' => [
        'ok' => 'Connected',
        'failing' => 'Unreachable',
        'inactive' => 'Inactive',
    ],

    'action' => [
        'sync' => 'Import now',
        'disconnect' => 'Disconnect',
        'save' => 'Save',
    ],

    'connection' => [
        'heading' => 'Connection',
    ],

    'field' => [
        'name' => 'Label',
        'base_url' => 'Instance URL',
        'api_token' => 'API token',
        'token_keep' => '•••••••• (leave unchanged)',
        'token_help' => 'Zammad: Profile → Token Access. Stored encrypted.',
        'webhook_secret' => 'Webhook secret (optional)',
        'webhook_help' => 'Shared secret for the webhook signature (X-Hub-Signature). Empty = webhook off, polling only.',
        'default_project' => 'Default project',
        'no_project' => '— no project (global) —',
        'active' => 'Active',
        'resolved_state' => 'Status return (target state)',
        'resolved_state_help' => 'Optional: ticket target state when the task is completed (e.g. “closed”). Empty = off.',
    ],

    'queue' => [
        'heading' => 'Queue → project',
        'help' => 'Maps Zammad groups (group ID) to a WorkDiary project. Without a match the default project applies, otherwise the task is created globally.',
        'group_id' => 'Group ID',
    ],

    'flash' => [
        'saved' => 'Zammad connection saved.',
        'sync_done' => 'Ticket import started.',
        'disconnected' => 'Zammad connection disconnected. Tasks and links are retained.',
        'no_connection' => 'No active Zammad connection.',
        'invalid_url' => 'The instance URL must start with http:// or https://.',
        'token_required' => 'A new connection requires an API token.',
    ],
    'resolution' => [
        'note' => 'Resolved in WorkDiary.',
    ],
];
