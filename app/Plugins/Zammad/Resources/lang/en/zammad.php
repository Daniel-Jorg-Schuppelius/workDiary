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
    'intro' => 'Open tickets the API token can see arrive as tasks in WorkDiary — for time tracking, records and billing. With “Mapped groups only” only groups mapped to a project. The ticket system stays authoritative; re-importing never creates duplicates.',

    'health' => [
        'ok' => 'Connected',
        'failing' => 'Unreachable',
        'inactive' => 'Inactive',
    ],

    'action' => [
        'sync' => 'Import now',
        'disconnect' => 'Disconnect',
        'save' => 'Save',
        'switch_target' => 'Switch target',
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
        'webhook_url' => 'Webhook address (enter it in Zammad under Webhook as the endpoint, with the secret as HMAC SHA1 signature token)',
        'default_project' => 'Default project',
        'no_project' => '— no project (global) —',
        'active' => 'Active',
        'resolved_state' => 'Status return (target state)',
        'resolved_state_help' => 'Optional: ticket target state when the task is completed (e.g. “closed”). Empty = off.',
        'time_unit' => 'Time booking to the ticket',
        'time_unit_off' => 'Off',
        'time_unit_minute' => 'Minutes',
        'time_unit_hour' => 'Hours',
        'time_unit_help' => 'Optional: books times recorded on linked tasks as time accounting on the ticket. The unit must match the time accounting unit in Zammad. Off = no time booking.',
        'allow_private_network' => 'Allow private/internal addresses',
        'allow_private_network_help' => 'Enable only if Zammad lives on your own network (e.g. 192.168.x.x). This is audited and only takes effect if the operator permits it.',
    ],

    'queue' => [
        'heading' => 'Queue → project',
        'help' => 'Maps Zammad groups (group ID) to a WorkDiary project. Without a match the default project applies, otherwise the task is created globally.',
        'group_id' => 'Group ID',
        'limited' => 'Mapped groups only',
        'limited_help' => 'On: only tickets from groups mapped to a project here arrive as tasks. Off: all tickets the API token can see.',
    ],

    'flash' => [
        'saved' => 'Zammad connection saved.',
        'sync_done' => 'Ticket import started.',
        'disconnected' => 'Zammad connection disconnected. Tasks and links are retained.',
        'no_connection' => 'No active Zammad connection.',
        'invalid_url' => 'The instance URL must start with http:// or https://.',
        'token_required' => 'A new connection requires an API token.',
        'private_url_blocked' => 'The instance URL points to a private/internal address. For Zammad on your own network, enable the approval of private addresses.',
        'helpdesk_required' => 'Service tickets require the Helpdesk module.',
        'queue_required' => 'Please choose a queue.',
        'target_switched' => 'Ticket target switched.',
    ],

    'target' => [
        'heading' => 'Ticket target',
        'help' => 'Determines whether new tickets arrive as tasks or as service tickets of a queue. Tickets already imported stay where they are. Open assignment conflicts in the Mapping Inbox block the switch.',
        'current' => 'Current',
        'task' => 'Tasks',
        'service_ticket' => 'Service tickets',
        'service_ticket_in' => 'Service tickets in “:queue”',
        'field' => 'New tickets as',
        'queue' => 'Queue',
        'queue_help' => 'Service tickets only. Zammad then leads the tickets of this queue.',
        'no_queue' => '— choose queue —',
        'helpdesk_hint' => 'Service tickets require the Helpdesk module.',
        'no_queues_hint' => 'There is no queue yet. Create one under Service desk → Queues.',
        'confirm_title' => 'Switch ticket target',
        'confirm' => 'New tickets will then arrive the chosen way; tickets already imported stay where they are. The switch is audited.',
    ],

    'guard' => [
        'subject' => 'The instance URL',
        'private_hint' => 'For Zammad on your own network, the approval of private addresses must be enabled on the connection.',
    ],

    'resolution' => [
        'note' => 'Resolved in WorkDiary.',
    ],
];
