<?php
/*
 * Created on   : Wed Sep 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : cloud_intake.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'action' => [
        'connect_google' => 'Connect Google Drive',
    ],
    'google' => [
        'description' => 'Reads documents from monitored Google Drive folders (cloud document intake) — My Drive and shared drives; rollout blocked until Google OAuth verification.',
        'health' => [
            'not_configured' => 'Google Drive client keys not configured.',
            'no_org_context' => 'No organisation context (system run).',
            'attention' => 'At least one Google Drive connection needs attention (re-auth/blocked).',
            'backup_attention' => 'The Google Drive backup target needs attention (re-auth/blocked) — affects all organizations.',
            'ok' => 'Google Drive connections healthy.',
            'error' => 'Health check failed (:class).',
        ],
    ],
];
