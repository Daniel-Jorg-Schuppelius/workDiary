<?php
/*
 * Created on   : Wed Sep 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : mail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'flash' => [
        'msgraph_connection_required' => 'A Microsoft 365 mailbox requires the mail sending connection in the Microsoft 365 plugin first (scope Mail.ReadWrite).',
    ],
    'transport' => [
        'msgraph' => 'Microsoft 365 (Graph)',
        'msgraph_hint' => 'Microsoft 365: uses the organization’s Graph mail connection — no IMAP credentials needed.',
    ],
];
