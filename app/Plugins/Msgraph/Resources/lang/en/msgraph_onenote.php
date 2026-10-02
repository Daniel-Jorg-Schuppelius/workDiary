<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : msgraph_onenote.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// OneNote-Übernahme (Feature 155, MVP-815): Sektion im Msgraph-Admin-Panel + Flow-Flashes.
return [
    'heading' => 'Import from OneNote',
    'intro' => 'Imports a OneNote notebook once or on demand as notes or knowledge articles — read-only (Notes.Read), no writing back and no ongoing sync. The notebook becomes a collection, sections become sub-collections.',
    'badge_connected' => 'Connected',
    'badge_disabled' => 'Switched off',
    'account' => 'Connected account',
    'connect' => 'Connect OneNote',
    'disconnect' => 'Disconnect OneNote',
    'open_hub' => 'Go to “Knowledge”',
    'enable_hint' => 'First switch on “Allow OneNote import” in the plugin settings — only then does the connection request the additional Notes.Read permission.',
    'flash' => [
        'not_configured' => 'Microsoft 365 is not configured (MSGRAPH_CLIENT_ID/SECRET missing).',
        'state_invalid' => 'The sign-in has expired or is invalid — please start again.',
        'oauth_denied' => 'The consent was cancelled.',
        'oauth_failed' => 'The connection failed (:class).',
        'connected' => 'OneNote connected.',
        'disconnected' => 'OneNote disconnected — access token removed.',
        'disabled' => 'The OneNote import is switched off in the plugin settings.',
    ],
];
