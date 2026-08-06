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
    'title' => 'Microsoft 365 calendar',
    'intro' => 'WorkDiary appointments are published via Microsoft Graph to a calendar of the connected Microsoft 365 account. WorkDiary stays authoritative; cancelled appointments disappear there and repeated runs never create duplicates. External appointments are never read.',
    'plugin_description' => 'Publishes appointments idempotently to a Microsoft 365 calendar (Microsoft Graph, OAuth2) — publish-only, selectable target calendar.',
    'not_configured_hint' => 'MSGRAPH_CLIENT_ID/SECRET (and MSGRAPH_TENANT if needed) are not set — the connection requires an app registration in the Microsoft tenant first.',

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
    ],
];
