<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : caldav.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'CalDAV',
    'intro' => 'WorkDiary appointments are published to an external CalDAV calendar (Nextcloud/ownCloud) — on-premise, without a Microsoft or Google account. WorkDiary stays authoritative; cancelled appointments disappear there and repeated runs never create duplicates.',

    'health' => [
        'ok' => 'Connected',
        'failing' => 'Unreachable',
        'inactive' => 'Inactive',
    ],

    'action' => [
        'publish' => 'Publish now',
        'disconnect' => 'Disconnect',
        'save' => 'Save',
    ],

    'connection' => [
        'heading' => 'Connection',
    ],

    // Titel der Inbox-Einträge des Kalenderimports — ein remote
    // gelöschter Termin wird gemeldet, nicht still nachgezogen.
    'import' => [
        'deleted_title' => 'Appointment deleted in the CalDAV calendar',
    ],

    'field' => [
        'name' => 'Label',
        'base_url' => 'DAV base URL',
        'base_url_help' => 'Nextcloud: .../remote.php/dav (without the calendar path).',
        'username' => 'Username',
        'app_password' => 'App password',
        'password_keep' => '•••••••• (leave unchanged)',
        'password_help' => 'Nextcloud: Settings → Security → App password. Stored encrypted.',
        'calendar_path' => 'Calendar path (collection)',
        'calendar_path_help' => 'Relative to the base URL, e.g. calendars/team/roster.',
        'active' => 'Active',
        // MVP-610b: Rückimport ist Opt-in — er ändert Daten.
        'two_way' => 'Two-way: import external changes as inbox proposals',
        'two_way_help' => 'Reimport of the calendar collection via sync-collection (RFC 6578), otherwise over a time window with an ETag comparison — new external appointments, external edits to published ones and deletions land as cases in the integration inbox (never a blind creation).',
        'scopes' => 'Published content',
        'scope_events' => 'Events',
        'scope_schedule' => 'Rosters & leave',
        'scopes_help' => 'Which content is published to this collection. No selection means events only.',
    ],

    'flash' => [
        'saved' => 'CalDAV connection saved.',
        'publish_done' => 'Publish started.',
        'disconnected' => 'CalDAV connection disconnected. Already published appointments remain externally.',
        'no_connection' => 'No active CalDAV connection.',
        'invalid_url' => 'The base URL must start with http:// or https://.',
        'password_required' => 'A new connection requires an app password.',
    ],
];
