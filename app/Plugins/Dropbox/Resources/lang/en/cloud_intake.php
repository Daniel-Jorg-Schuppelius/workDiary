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
        'connect_dropbox' => 'Connect Dropbox',
    ],
    'dropbox' => [
        'description' => 'Reads documents from monitored Dropbox folders (cloud document intake) — with folder rules, transfer evidence and an inbox for unclear cases.',
        'health' => [
            'not_configured' => 'Dropbox app keys not configured.',
            'no_org_context' => 'No organisation context (system run).',
            'attention' => 'At least one Dropbox connection needs attention (re-auth/blocked).',
            'backup_attention' => 'The Dropbox backup target needs attention (re-auth/blocked) — affects all organizations.',
            'ok' => 'Dropbox connections healthy.',
            'error' => 'Health check failed (:class).',
        ],
    ],
];
