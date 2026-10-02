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
        'connect_nextcloud' => 'Connect Nextcloud',
    ],
    'field' => [
        'name' => 'Name',
    ],
    'nextcloud' => [
        'description' => 'Reads documents from watched Nextcloud folders (WebDAV) — with folder rules, handover proof and an inbox for ambiguous cases.',
        'health' => [
            'no_org_context' => 'No organization context (system run).',
            'attention' => 'At least one Nextcloud connection needs attention (re-auth/blocked).',
            'backup_attention' => 'The Nextcloud backup target needs attention (re-auth/blocked) — affects all organizations.',
            'ok' => 'Nextcloud connections are healthy.',
            'error' => 'Health check failed (:class).',
        ],
        'connect_title' => 'Connect Nextcloud',
        'connect_legend' => 'Credentials',
        'connect_submit' => 'Connect',
        'field' => [
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
];
