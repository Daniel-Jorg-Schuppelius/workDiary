<?php
/*
 * Created on   : Wed Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : sessions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Signed-in users',
    ],

    'subtitle' => 'Who is signed in where — active sessions and API access per user, with the option to sign them out remotely.',

    'privacy_notice' => 'Only metadata is shown (IP, device, timestamps) — never session contents or token values.',

    'hint' => [
        'driver' => 'Sessions can only be listed with the database driver; current driver: :driver. Without the database driver, targeted remote sign-out is not possible.',
        'terminals' => 'Attendance terminals are physical devices (not a user login). "Deactivate" locks the device, it does not sign out a user.',
        'remote_support' => 'Imported remote-support sessions — history only; cannot be terminated from workDiary.',
    ],

    'stat' => [
        'users' => 'Users',
        'online' => 'Online',
        'sessions' => 'Sessions',
        'tokens' => 'API tokens',
    ],

    'badge' => [
        'online' => 'Online',
        'this_device' => 'This device',
    ],

    'section' => [
        'sessions' => 'Web/app sessions',
        'tokens' => 'API tokens',
        'devices' => 'Location devices',
        'terminals' => 'Attendance terminals',
        'remote_support' => 'Recent remote support',
    ],

    'col' => [
        'device' => 'Device',
        'ip' => 'IP',
        'last_activity' => 'Last activity',
        'name' => 'Name',
        'created' => 'Created',
        'last_used' => 'Last used',
        'action' => 'Action',
        'terminal' => 'Terminal',
        'status' => 'Status',
        'last_seen' => 'Last seen',
        'provider' => 'Provider',
        'remote' => 'Identifier',
        'started' => 'Started',
        'ended' => 'Ended',
    ],

    'terminal' => [
        'inactive' => 'Deactivated',
        'offline' => 'Offline',
    ],

    'last_login' => 'Last sign-in',

    'live' => [
        'changed' => 'The active sign-ins have changed.',
        'reload' => 'Reload list',
    ],

    'action' => [
        'revoke_all' => 'Sign out all devices',
        'revoke_session' => 'Sign out',
        'revoke_token' => 'Revoke',
        'revoke_device' => 'Disconnect',
        'deactivate_terminal' => 'Deactivate',
    ],

    'confirm' => [
        'revoke_all' => 'Sign :name out of all devices? Existing sessions and "remember me" will be invalidated.',
        'revoke_session' => 'Really sign out this session remotely?',
        'revoke_token' => 'Really revoke this API token?',
        'revoke_device' => 'Really disconnect this location device?',
        'deactivate_terminal' => 'Really deactivate terminal ":name"? The device will no longer be able to sign in.',
    ],

    'empty' => [
        'title' => 'No active sign-ins.',
        'description' => 'Nobody in this organization is currently signed in.',
    ],

    'error' => [
        'own_current_session' => 'Your own current session cannot be ended here — use the normal logout instead.',
        'session_gone' => 'Session no longer exists.',
        'token_gone' => 'Token no longer exists.',
        'device_gone' => 'Device no longer exists or is already disconnected.',
    ],

    'flash' => [
        'session_revoked' => 'Session signed out remotely.',
        'all_revoked' => ':name has been signed out of all devices.',
        'token_revoked' => 'API token revoked.',
        'device_revoked' => 'Location device disconnected.',
        'terminal_deactivated' => 'Terminal deactivated.',
    ],
];
