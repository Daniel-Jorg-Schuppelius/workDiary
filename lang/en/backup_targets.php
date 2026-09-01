<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : backup_targets.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Cloud backup targets',
    'description' => 'Encrypted offsite copies of the whole installation (3-2-1 strategy). Plaintext never leaves the installation — only encrypted parts are uploaded.',

    'master_key_missing' => 'BACKUP_MASTER_KEY is not set — without the installation backup key no backups can be created or restored.',
    'recovery_key_missing' => 'No recovery key configured: if BACKUP_MASTER_KEY is lost, all cloud backups are irrecoverably lost. Set BACKUP_RECOVERY_PUBLIC_KEY and store the private key offline.',

    'connect' => 'Connect',
    'reconnect' => 'Re-authenticate',
    'disconnect' => 'Disconnect',
    'disconnect_confirm' => 'Really disconnect? Remote data stays untouched; scheduled backups stop.',
    'cleanup' => 'Cleanup',
    'no_connections' => 'No backup target connected yet.',
    'account' => 'Account',
    'quota' => 'Storage',
    'quota_value' => ':used of :total used',
    'quota_unknown' => 'Storage usage unknown',
    'pilot_note' => 'Pilot pending: this adapter has not been tested against the real provider yet.',

    'nextcloud' => [
        'connect_title' => 'Connect Nextcloud',
        'connect_legend' => 'Credentials',
        'connect_submit' => 'Connect',
        'field' => [
            'name' => 'Name',
            'server_url' => 'Server URL',
            'server_url_help' => 'HTTPS only. Example: https://cloud.example.com',
            'username' => 'Username',
            'app_password' => 'App password',
            'app_password_help' => 'A revocable app password (Settings › Security), never the regular account password.',
        ],
        'validation' => [
            'https_required' => 'The server URL must start with https://.',
            'unsafe_url' => 'The server URL must be publicly reachable (no internal/private target).',
        ],
    ],
    // Generisches WebDAV-Backupziel (Feature 123, MVP-612).
    's3' => [
        'connect_title' => 'Connect S3 backup target',
        'connect_legend' => 'S3-compatible object storage',
        'connect_submit' => 'Connect and verify',
        'selftest_hint' => 'Before saving, a test file is written, read back and deleted. If that fails, the target is not activated.',
        'field' => [
            'name' => 'Name',
            'endpoint' => 'Endpoint (empty for AWS S3)',
            'endpoint_help' => 'Enter the HTTPS address for MinIO, Wasabi, Hetzner or Scaleway. Left empty, AWS S3 is derived from the region.',
            'region' => 'Region',
            'region_help' => 'Often arbitrary on self-hosted storage — us-east-1 is the usual default.',
            'bucket' => 'Bucket',
            'access_key' => 'Access key',
            'secret_key' => 'Secret key',
            'secret_key_help' => 'Stored encrypted and never shown again.',
            'prefix' => 'Prefix (optional)',
            'prefix_help' => 'Subfolder inside the bucket. WorkDiary creates its own pseudonym folder below it.',
            'path_style' => 'Address bucket in the path (path style)',
            'path_style_help' => 'Required for MinIO and most self-hosted storage. AWS S3 does not need it.',
        ],
        'validation' => [
            'https_required' => 'The endpoint must start with https://.',
            'unsafe_url' => 'This endpoint points into a private network. Allow it via S3_BACKUP_ALLOW_PRIVATE_TARGETS.',
        ],
        'flash' => [
            'selftest_failed' => 'The target failed the write/read test (:class). It was not activated.',
        ],
    ],

    'webdav' => [
        'connect_title' => 'Connect WebDAV target',
        'connect_legend' => 'Credentials',
        'connect_submit' => 'Connect and test',
        'selftest_hint' => 'On connecting, a test folder is created, a file written, read back and deleted again.',
        'field' => [
            'name' => 'Name',
            'server_url' => 'Collection URL',
            'server_url_help' => 'HTTPS only. The full WebDAV collection, e.g. https://dav.example.com/remote.php/dav/files/backup/',
            'username' => 'User name',
            'password' => 'Password',
            'password_help' => 'Preferably a dedicated, revocable access token rather than the account password.',
            'base_path' => 'Subfolder (optional)',
            'base_path_help' => 'Empty = directly in the collection. The pseudonym folder is created below it.',
        ],
        'validation' => [
            'https_required' => 'The collection URL must start with https://.',
            'unsafe_url' => 'The collection URL must be publicly reachable (no internal/private target).',
        ],
        'flash' => [
            'selftest_failed' => 'The connection test failed (:class). The target was not activated.',
        ],
    ],
    'generations' => [
        'title' => 'Backup generations',
        'empty' => 'No backup generation yet.',
        'snapshot' => 'Snapshot',
        'target' => 'Target',
        'class' => 'Class',
        'age' => 'Created',
        'size' => 'Size',
        'status' => 'Status',
        'verified' => 'Verified',
        'restore_tested' => 'Restore test',
        'restore_pending' => 'backed up, restore not yet confirmed',
        'hold' => 'Legal hold',
        'actions' => 'Actions',
        'hold_set_action' => 'Set hold',
        'hold_release_action' => 'Release hold',
        'delete_action' => 'Delete',
        'delete_confirm' => 'Really delete this generation? Remote data and the record will be removed.',
    ],

    'cleanup_page' => [
        'title' => 'Cleanup — remote inventory',
        'description' => 'Preview of the objects in this connection\'s backup area. Deletion only happens after per-generation confirmation.',
        'empty' => 'No remote objects found in the backup area.',
        'known' => 'Known generation',
        'orphan' => 'Orphaned (no record in the database)',
        'error' => 'Remote inventory could not be loaded: :message',
        'back' => 'Back to overview',
    ],

    'flash' => [
        'not_configured' => 'The provider is not configured (client ID/secret missing).',
        'state_invalid' => 'The sign-in flow expired or is invalid — please start again.',
        'oauth_denied' => 'The authorization was cancelled or denied.',
        'oauth_failed' => 'Token exchange failed (:class).',
        'account_failed' => 'Account confirmation failed (:class).',
        'scope_missing' => 'Required permission missing (:scope) — the target is blocked.',
        'connected' => 'Backup target connected and active.',
        'disconnected' => 'Connection removed. Remote data stays untouched.',
        'hold_set' => 'Legal hold set — the generation is protected from deletion.',
        'hold_released' => 'Legal hold released.',
        'hold_blocks_delete' => 'This generation carries a legal hold and cannot be deleted.',
        'cleanup_failed' => 'Remote cleanup failed (:class).',
        'generation_deleted' => 'Generation removed (remote and record).',
    ],
];
