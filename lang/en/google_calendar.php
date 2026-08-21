<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : google_calendar.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Google Calendar',
    'intro' => 'WorkDiary appointments are published via the Google Calendar API to a calendar of the connected Google account. WorkDiary stays authoritative; cancelled appointments disappear there and repeated runs never create duplicates. External appointments are never read.',
    'plugin_description' => 'Publishes appointments idempotently to a Google calendar (Calendar API v3, OAuth2) — publish-only, selectable target calendar.',
    'not_configured_hint' => 'GOOGLE_CALENDAR_CLIENT_ID/SECRET are not set — the connection first needs an OAuth client in the Google Cloud Console (calendar scopes are “sensitive”: brand verification or consent type “Internal” for Workspace).',

    'health' => [
        'badge_ok' => 'Connected',
        'badge_failing' => 'Unreachable',
        'badge_inactive' => 'Inactive',
        'not_configured' => 'Google Calendar is not configured (GOOGLE_CALENDAR_CLIENT_ID/SECRET missing).',
        'no_org_context' => 'Configured (no organization in context).',
        'no_connection' => 'No Google Calendar connection established.',
        'inactive' => 'Google Calendar connection is disconnected or disabled.',
        'ok' => 'Connected — calendar list available.',
        'failing' => 'Google Calendar API unreachable or access denied.',
        'error' => 'Google Calendar error (:class).',
    ],

    'action' => [
        'connect' => 'Connect to Google',
        'publish' => 'Publish now',
        'disconnect' => 'Disconnect',
        'save' => 'Save',
    ],

    'calendar' => [
        'heading' => 'Target calendar',
        'help' => 'Which calendar of the connected account is published to. Without a selection, the primary calendar is used.',
        'target' => 'Calendar',
        'default' => 'Primary calendar',
        // MVP-610a: Rückimport ist Opt-in — er ändert Daten.
        'two_way' => 'Two-way: import external changes as inbox proposals',
        'two_way_hint' => 'Reimport of the target calendar’s change list — new external appointments, external edits to published ones and deletions land as cases in the integration inbox (never a blind creation).',
    ],

    // Titel der Inbox-Einträge des Kalenderimports — ein remote
    // gelöschter Termin wird gemeldet, nicht still nachgezogen.
    'import' => [
        'deleted_title' => 'Appointment deleted in Google Calendar',
    ],

    'flash' => [
        'not_configured' => 'Google Calendar is not configured (GOOGLE_CALENDAR_CLIENT_ID/SECRET missing).',
        'state_invalid' => 'The OAuth flow has expired or is invalid. Please start again.',
        'oauth_denied' => 'The connection was declined or cancelled.',
        'oauth_failed' => 'The token exchange failed (:class).',
        'connected' => 'Google account connected.',
        'disconnected' => 'Google Calendar connection disconnected. Already published appointments remain externally.',
        'no_connection' => 'No active Google Calendar connection.',
        'two_way_saved' => 'Reimport setting saved.',
        'calendar_saved' => 'Target calendar saved.',
        'calendar_invalid' => 'The selected calendar was not found.',
        'publish_done' => 'Publish started.',
    ],
];
