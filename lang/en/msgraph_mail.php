<?php
/*
 * Created on   : Wed Aug 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : msgraph_mail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Graph mail sending (Feature 102): mail section of the Msgraph admin panel + flow flashes.
return [
    'heading' => 'Email sending via Microsoft 365',
    'intro' => 'Sends WorkDiary mails (invoices, dunning notices, notifications) via Microsoft Graph instead of SMTP — modern auth, no basic-auth SMTP required.',
    'badge_connected' => 'Connected',
    'badge_inactive' => 'Disconnected',
    'mailer_hint' => 'The msgraph mailer is currently not active. Enable it via MAIL_MAILER=msgraph (or a failover chain including msgraph) in the installation.',
    'account' => 'Connected account',
    'from_address' => 'Sender address (optional)',
    'from_placeholder' => 'e.g. billing@company.com (shared mailbox)',
    'from_hint' => 'Empty = the connected account sends as itself. A different address requires the Exchange “Send As” right and the Mail.Send.Shared scope.',
    'save_to_sent' => 'Save a copy to the Sent Items folder',
    'connect' => 'Connect mail sending',
    'disconnect' => 'Disconnect mail sending',
    'flash' => [
        'not_configured' => 'Microsoft 365 is not configured (MSGRAPH_CLIENT_ID/SECRET missing).',
        'state_invalid' => 'The sign-in process expired or is invalid — please start again.',
        'oauth_denied' => 'The consent was cancelled.',
        'oauth_failed' => 'The connection failed (:class).',
        'connected' => 'Mail sending via Microsoft 365 connected.',
        'disconnected' => 'Mail sending disconnected — access tokens removed.',
        'no_connection' => 'No Microsoft 365 mail connection established.',
        'settings_saved' => 'Mail settings saved.',
    ],
];
