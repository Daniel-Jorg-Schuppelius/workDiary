<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : diagnostics.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Diagnose-Seite: Überwachungszugang (MVP-1618).
return [
    'token' => [
        'heading' => 'Monitoring access',
        'intro' => 'External monitoring fetches the report without signing in at :url and sends the token in the header “Authorization: Bearer …”. Names and addresses are not included.',
        'create' => 'Create access',
        'name' => 'Label',
        'name_help' => 'For example the name of the monitoring system, so you can identify the access later.',
        'hint' => 'Identifier',
        'created_by' => 'Created',
        'last_used' => 'Last request',
        'status' => 'Status',
        'active' => 'Active',
        'revoked_state' => 'Revoked',
        'revoke' => 'Revoke',
        'revoke_confirm' => 'Revoke this access? The monitoring system will no longer receive the report.',
        'shown_once' => 'Copy the token now. It is shown only this once and cannot be recovered.',
        'empty' => 'No monitoring access created yet.',
        'created' => 'Monitoring access created.',
        'revoked' => 'Monitoring access revoked.',
    ],
];
