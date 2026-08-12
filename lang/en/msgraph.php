<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : msgraph.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Microsoft 365',
    'calendar_heading' => 'Calendar',
    'intro' => 'WorkDiary appointments are published via Microsoft Graph to a calendar of the connected Microsoft 365 account. WorkDiary stays authoritative; cancelled appointments disappear there and repeated runs never create duplicates. External appointments are never read.',
    'plugin_description' => 'Publishes appointments idempotently to a Microsoft 365 calendar (Microsoft Graph, OAuth2) — publish-only, selectable target calendar.',
    'not_configured_hint' => 'MSGRAPH_CLIENT_ID/SECRET (and MSGRAPH_TENANT if needed) are not set — the connection requires an app registration in the Microsoft tenant first.',

    // Teams presence on the attendance page (Feature 102, F).
    'presence' => [
        'heading' => 'Team (Teams status)',
        'state' => [
            'Available' => 'Available',
            'AvailableIdle' => 'Available (idle)',
            'Busy' => 'Busy',
            'BusyIdle' => 'Busy (idle)',
            'DoNotDisturb' => 'Do not disturb',
            'Away' => 'Away',
            'BeRightBack' => 'Be right back',
            'Offline' => 'Offline',
            'PresenceUnknown' => 'Unknown',
        ],
    ],
    // Free/busy in the event dialog (Feature 102, C2).
    'availability' => [
        'check' => 'Check availability (Microsoft 365)',
        'hint' => 'Free/busy of the selected participants in the time window — without event details.',
        'missing_input' => 'Please choose start, end and at least one participant.',
        'no_connection' => 'No active Microsoft 365 calendar connection.',
        'failed' => 'Availability lookup failed.',
        'free' => 'free',
        'busy' => 'busy',
        'unknown' => 'unknown',
    ],
    // Per-org app registration (Feature 102 variant B, plugin settings dialog).
    'settings' => [
        'client_id' => 'Client ID (own app registration)',
        'client_id_help' => 'Empty = the installation’s instance app. An own Entra app must register the same redirect URIs.',
        'client_secret' => 'Client secret',
        'client_secret_help' => 'Stored encrypted; leave empty to keep the stored value.',
        'tenant' => 'Tenant (directory ID)',
        'tenant_help' => 'GUID of the Entra tenant; empty = the instance app’s value (default “common”).',
        'tenant_invalid' => 'Tenant must be a directory GUID (or common/organizations/consumers).',
    ],
    'health' => [
        'badge_ok' => 'Connected',
        'badge_failing' => 'Unreachable',
        'badge_inactive' => 'Inactive',
        'not_configured' => 'Microsoft 365 is not configured (MSGRAPH_CLIENT_ID/SECRET missing).',
        'no_org_context' => 'Configured (no organization in context).',
        'no_connection' => 'No Microsoft 365 connection established.',
        'inactive' => 'Microsoft 365 connection is disconnected or disabled.',
        'side_connections' => 'Microsoft 365 side connections need attention (:intake document intake, :backup backup, :mail mail — re-authenticate or check scopes).',
        'ok' => 'Connected — calendar list available.',
        'failing' => 'Microsoft Graph unreachable or access denied.',
        'error' => 'Microsoft Graph error (:class).',
    ],

    'action' => [
        'connect' => 'Connect to Microsoft 365',
        'publish' => 'Publish now',
        'disconnect' => 'Disconnect',
        'save' => 'Save',
    ],

    'calendar' => [
        'heading' => 'Target calendar',
        'help' => 'Which calendar of the connected account is published to. Without a selection, the default calendar is used.',
        'target' => 'Calendar',
        'default' => 'Default calendar',
        'teams_meetings' => 'Create new events as Teams meetings (join link)',
        'teams_meetings_hint' => 'Only affects newly published events — Graph cannot revert an existing event to offline.',
        'two_way' => 'Two-way: import external changes as inbox proposals',
        'two_way_hint' => 'Delta import of the target calendar — new external events, external edits to published ones and deletions become integration inbox cases (never blind creation).',
    ],

    // Entra app & tenant-wide grant (v2 admin consent).
    'entra' => [
        'heading' => 'Entra app & tenant-wide grant',
        'intro' => 'Users connect their Microsoft 365 services through the Microsoft sign-in (OAuth2, delegated permissions only). If a Microsoft tenant policy prevents users from consenting, an Entra administrator can grant the permissions here once for the entire organization.',
        'consent' => 'Grant for organization (admin consent)',
        'consent_hint' => 'Opens the Microsoft sign-in; an Entra administrator role in the target tenant is required. The grant covers calendar, mail sending, contacts, tasks and document intake.',
        'redirects' => 'Redirect URIs for a custom app registration',
        'redirects_hint' => 'A customer-owned Entra app (plugin settings) must register exactly these URIs as redirects of type "Web":',
        'redirect_calendar' => 'Calendar',
        'redirect_mail' => 'Mail sending',
        'redirect_contacts' => 'Contacts',
        'redirect_tasks' => 'Tasks (To Do)',
        'redirect_intake' => 'Document intake',
        'redirect_adminconsent' => 'Admin consent',
        'redirect_backup' => 'Backup target (instance app only)',
    ],

    'flash' => [
        'not_configured' => 'Microsoft 365 is not configured (MSGRAPH_CLIENT_ID/SECRET missing).',
        'state_invalid' => 'The OAuth flow has expired or is invalid. Please start again.',
        'oauth_denied' => 'The connection was declined or cancelled.',
        'oauth_failed' => 'The token exchange failed (:class).',
        'connected' => 'Microsoft 365 account connected.',
        'disconnected' => 'Microsoft 365 connection disconnected. Already published appointments remain externally.',
        'no_connection' => 'No active Microsoft 365 connection.',
        'calendar_saved' => 'Target calendar saved.',
        'calendar_invalid' => 'The selected calendar was not found.',
        'publish_done' => 'Publish started.',
        'admin_consent_granted' => 'Tenant-wide grant issued — users can now connect without an individual consent prompt.',
        'admin_consent_failed' => 'Admin consent was not granted (:error).',
    ],
];
