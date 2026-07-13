<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : carddav.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'CardDAV',
    'intro' => 'Contacts are read from a self-hosted CardDAV address book (Nextcloud/Radicale/Baïkal) and fed into the integration inbox as matching suggestions — no automatic merging, no writes to customer data. Unchanged cards are skipped (UID+ETag).',
    'description' => 'Reads contacts from a CardDAV address book (RFC 6352) and feeds them into the integration inbox as matching suggestions — read-only, on-premise, without a Microsoft/Google account.',

    'health' => [
        'ok' => 'Connected',
        'failing' => 'Unreachable',
        'inactive' => 'Inactive',
        'no_connection' => 'No CardDAV connection configured.',
        'inactive_or_incomplete' => 'CardDAV connection is disabled or incomplete.',
        'unreachable' => 'CardDAV server unreachable or credentials invalid.',
        'error' => 'CardDAV error (:class).',
        'last_error' => 'Last error: :error',
    ],

    'action' => [
        'discover' => 'Find address books',
        'choose_addressbook' => 'Use address book',
        'sync' => 'Sync now',
        'disconnect' => 'Disconnect',
        'save' => 'Save',
    ],

    'connection' => [
        'heading' => 'Connection',
    ],

    'addressbook' => [
        'heading' => 'Address book',
        'current' => 'Current sync source: :name',
        'hint' => 'Use "Find address books" to query the server, then pick an address book as the sync source.',
    ],

    'status' => [
        'last_synced' => 'Last synced :at.',
    ],

    'field' => [
        'name' => 'Name',
        'base_url' => 'DAV base URL',
        'base_url_help' => 'Nextcloud: .../remote.php/dav — Radicale/Baïkal: server root. Address book discovery follows RFC 6764 (.well-known/carddav).',
        'username' => 'Username',
        'app_password' => 'App password',
        'password_keep' => '•••••••• (leave unchanged)',
        'password_help' => 'With 2FA enabled (e.g. Nextcloud) an app password is required. Stored encrypted.',
        'allow_private_network' => 'Allow private/internal addresses',
        'allow_private_network_help' => 'Enable only if the CardDAV server lives on your own network (e.g. 192.168.x.x). This is audited.',
        'active' => 'Active',
    ],

    'flash' => [
        'saved' => 'CardDAV connection saved.',
        'invalid_url' => 'The base URL must start with http:// or https://.',
        'private_url_blocked' => 'The base URL points to a private/internal address. Enable the private address opt-in for a server on your own network.',
        'password_required' => 'An app password is required for a new connection.',
        'no_connection' => 'No active CardDAV connection available.',
        'discovery_failed' => 'Address book discovery failed — server unreachable or credentials invalid.',
        'no_addressbooks' => 'No address books were found on the server.',
        'discovered' => ':count address books found — please pick a sync source.',
        'addressbook_not_discovered' => 'Please run "Find address books" first and pick a discovered address book.',
        'addressbook_saved' => 'Address book set as sync source.',
        'not_syncable' => 'Sync not possible — connection inactive, failing, or no address book selected.',
        'sync_done' => 'Sync started. New contacts will appear as suggestions in the matching inbox.',
        'disconnected' => 'CardDAV connection disconnected. Previously staged suggestions are kept.',
    ],
];
