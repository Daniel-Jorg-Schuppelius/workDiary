<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : msgraph_contacts.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Contact push (Feature 102, slice D): section in the Msgraph admin panel + customer page button.
return [
    'heading' => 'Push contacts to Outlook',
    'intro' => 'Pushes WorkDiary customers to the connected account’s Outlook contacts on demand (idempotent — no duplicate on repeated pushes).',
    'badge_connected' => 'Connected',
    'badge_inactive' => 'Disconnected',
    'account' => 'Connected account',
    'connect' => 'Connect contact push',
    'disconnect' => 'Disconnect contact push',
    'push_button' => 'To Outlook',
    'flash' => [
        'not_configured' => 'Microsoft 365 is not configured (MSGRAPH_CLIENT_ID/SECRET missing).',
        'state_invalid' => 'The sign-in process expired or is invalid — please start again.',
        'oauth_denied' => 'The consent was cancelled.',
        'oauth_failed' => 'The connection failed (:class).',
        'connected' => 'Contact push to Outlook connected.',
        'disconnected' => 'Contact push disconnected — access tokens removed.',
        'no_connection' => 'No Microsoft 365 contact connection established.',
        'plugin_disabled' => 'The Microsoft 365 plugin is not enabled.',
        'pushed' => 'Customer pushed as Outlook contact (ID :id).',
        'push_failed' => 'Push failed (:class).',
    ],
];
