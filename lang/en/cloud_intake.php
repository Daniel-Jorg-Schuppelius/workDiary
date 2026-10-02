<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : cloud_intake.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Cloud-Dokumenteingang (Feature 080).
return [
    'validation' => [
        'pattern_empty' => 'The path pattern must not be empty.',
        'pattern_triple_star' => 'Invalid pattern: "***" is not allowed (only * and **).',
        'unknown_variable' => 'Unknown path variable :variable.',
        'duplicate_variable' => 'Path variable :variable occurs more than once.',
    ],
    'title' => [
        'index' => 'Cloud document intake',
        'subtitle' => 'Read documents from monitored cloud folders and route them to invoice intake and DMS via folder rules.',
        'empty' => 'No cloud connection yet.',
    ],
    'field' => [
        'provider' => 'Provider',
        'account' => 'Account',
        'root_folder' => 'Root folder',
        'routes' => 'Rules',
        'status' => 'Status',
        'account_unconfirmed' => 'Account not confirmed yet',
        'container' => 'Container/drive',
        'root_folder_id' => 'Root folder ID (optional)',
    ],
    'picker' => [
        'search_label' => 'Search containers',
        'search_placeholder' => 'empty = own drives; a search term also finds SharePoint libraries',
        'load' => 'Load containers',
        'load_failed' => 'Containers could not be loaded — please enter the ID manually.',
    ],
    'action' => [
        'preview' => 'Preview',
        'save_folder' => 'Apply folder',
        'disconnect' => 'Disconnect',
        'disconnect_confirm' => 'Really disconnect? Imported documents and transfer evidence remain; only access and checkpoint are removed.',
    ],
    'flash' => [
        'not_configured' => 'This provider is not configured (installation app keys missing).',
        'state_invalid' => 'The sign-in flow expired or is invalid — please start again.',
        'oauth_denied' => 'Authorisation was cancelled.',
        'oauth_failed' => 'Sign-in failed (:class).',
        'account_failed' => 'The account could not be confirmed (:class).',
        'connected' => 'Connection established — account confirmed.',
        'folder_selected' => 'Root folder applied — the next run starts with a fresh sync.',
        'overlapping_root' => 'The root folder overlaps with connection ":name" of the same account.',
        'preview_failed' => 'Preview failed (:class).',
        'preview_result' => 'Preview (first page:more): :files files, :size — :matched matched by rules, :unmatched unassigned.',
        'disconnected' => 'Connection removed — evidence and imported documents remain.',
        'route_saved' => 'Folder rule saved.',
        'route_deleted' => 'Folder rule deleted.',
    ],
    'route' => [
        'heading' => 'Folder rules',
        'create' => 'Create rule',
        'edit' => 'Edit rule',
        'save' => 'Save',
        'delete' => 'Delete',
        'delete_confirm' => 'Really delete this folder rule?',
        'basics' => 'Rule',
        'pattern' => 'Path pattern',
        'pattern_help' => '* = one folder segment, ** = any depth; variables: {customer_number}, {project_number}, {order_number}, {asset_number}, {contract_number}. Unclear matches go to the integration inbox.',
        'target' => 'Target',
        'document_type' => 'Document type',
        'priority' => 'Priority',
        'extensions' => 'Allowed extensions',
        'extensions_help' => 'Comma-separated; empty = all (except globally blocked).',
        'max_size' => 'Max size (bytes)',
        'auto_version' => 'Adopt new revisions automatically as versions',
        'auto_version_help' => 'Without approval, new revisions become version proposals in the inbox.',
        'active' => 'Active',
        'inactive' => 'Inactive',
        'empty' => 'No rule yet — without a valid rule the connection does not import.',
    ],
    'log' => [
        'heading' => 'Import log',
        'empty' => 'No transfers yet.',
        'path' => 'Source path',
        'revision' => 'Revision',
        'reason' => 'Reason',
        'when' => 'When',
    ],
    // Import report (feature 080 P9; audit 2026-08, W4.4).
    'report' => [
        'title' => 'Cloud document intake report',
        'nav' => 'Cloud document intake',
        'subtitle' => 'Imported and rejected documents in the period',
        'kpi' => [
            'total' => 'Items total',
            'imported' => 'Imported',
            'inbox' => 'In the matching inbox',
            'rejected' => 'Rejected',
        ],
        'chart' => [
            'per_period' => 'Items :per',
            'by_provider' => 'Items per provider',
        ],
        'unit' => ['documents' => 'Documents'],
        'section' => [
            'connections' => 'Connections',
            'reasons' => 'Rejection reasons',
            'items' => 'Items',
        ],
        'column' => [
            'folder' => 'Folder',
            'provider' => 'Provider',
            'status' => 'Status',
            'imported' => 'Imported',
            'rejected' => 'Rejected',
            'last_run' => 'Last run',
            'reason' => 'Reason',
            'count' => 'Count',
            'date' => 'Time',
            'path' => 'Source path',
        ],
        'empty' => [
            'connections' => 'No cloud connection linked yet.',
            'reasons' => 'No rejections in the selected period.',
            'items' => 'No data yet in the selected period.',
        ],
    ],
];
