<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : firefly.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Mirrors deposits and withdrawals from Firefly III and assigns them to invoices or incoming invoices via the document number in the description. Nothing is booked in WorkDiary, and nothing is written to Firefly III.',
        'capability' => 'Payment reconciliation',
    ],
    'page' => [
        'subtitle' => 'Account movements from Firefly III, assigned via document numbers in the description — read-only.',
    ],
    'settings' => [
        'base_url' => 'Address',
        'base_url_help' => 'Address of the Firefly III installation, e.g. https://finance.example.com (without /api).',
        'access_token' => 'Personal access token',
        'access_token_help' => 'Create a personal access token in Firefly III under Options → Profile → OAuth.',
        'window_days' => 'Time window (days)',
        'window_days_help' => 'Each sync reads the movements of this period up to today. 1 to 365 days.',
        'allow_private_network' => 'Allow private addresses',
        'allow_private_network_help' => 'Only for an installation in your own network: allows a private or internal address. Logged, and only effective if the operator permits it.',
    ],
    'connection' => [
        'title' => 'Connection',
        'connected' => 'Connected',
        'not_connected' => 'Not connected',
        'verify' => 'Check connection',
        'settings' => 'Credentials',
        'disconnect' => 'Disconnect',
        'confirm_disconnect' => 'Disconnect from Firefly III? Syncing stops; mirrored movements remain until the next complete run.',
        'sync_now' => 'Sync now',
        'not_configured' => 'Firefly III is not set up: enter the address and token in the plugin settings.',
        'privacy_note' => 'WorkDiary reads deposits and withdrawals with description and counterparty and writes nothing to Firefly III. WorkDiary does not book payments from them.',
        'instance' => 'Instance',
        'account' => 'Account',
        'window' => 'Time window',
        'window_value' => 'Movements of the last :days days',
        'last_sync' => 'Last sync',
        'incomplete' => 'incomplete',
        'last_error' => 'Last error',
    ],
    'list' => [
        'search' => 'Search',
        'search_placeholder' => 'Description, counterparty, document number',
        'all_matches' => 'All assignments',
        'open_matches' => 'Open (none or ambiguous)',
        'col' => [
            'date' => 'Date',
            'kind' => 'Type',
            'counterparty' => 'Counterparty',
            'description' => 'Description',
            'match' => 'Assignment',
            'amount' => 'Amount',
            'actions' => 'Actions',
        ],
        'empty' => 'No movements yet',
        'foreign_amount' => 'Amount in second currency',
    ],
    // Erledigt markieren (MVP-1628)
    'settle' => [
        'title' => 'Mark movement as settled',
        'action' => 'Settled',
        'intro' => 'The movement needs no document (for example an account fee or a private withdrawal). It then no longer counts in the “Finance movements” tile and the work queue; the synchronisation does not change that.',
        'note' => 'Reason',
        'done' => 'Movement marked as settled.',
        'already' => 'The movement is already settled.',
        'reopened' => 'Movement reopened.',
        'reopen' => 'Reopen',
        'filter' => 'Settled',
        'badge' => 'Settled by :name on :date',
    ],
    'search' => [
        'domain' => 'Firefly III movements',
    ],
    'flash' => [
        'not_configured' => 'Firefly III is not set up: the address or token is missing in the plugin settings.',
        'verify_failed' => 'The connection to Firefly III failed (:class).',
        'source_locked' => 'The plugin settings point to a different instance. As long as movements of the previous one are mirrored, switching is not possible.',
        'verified' => 'Firefly III connection checked: :source_name.',
        'not_ready' => 'Check the connection first.',
        'sync_failed' => 'The sync failed (:class).',
        'synced' => 'Sync: :read read, :created new, :updated updated, :matched assigned, :removed removed.',
        'disconnected' => 'Firefly III disconnected.',
    ],
    'health' => [
        'not_configured' => 'Firefly III is not set up (address or token missing).',
        'not_connected' => 'Firefly III connection not checked yet.',
    ],
    'work_queue' => [
        'source' => 'Firefly III',
        'unmatched' => '{1} 1 Firefly transaction without assignment|[2,*] :count Firefly transactions without assignment',
        'ambiguous' => '{1} 1 Firefly transaction with ambiguous assignment|[2,*] :count Firefly transactions with ambiguous assignment',
    ],
];
