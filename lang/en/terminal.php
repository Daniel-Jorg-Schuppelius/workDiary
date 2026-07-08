<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : terminal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Time clock terminals',
    'intro' => 'Fixed RFID/NFC terminals clock employees without a work device in and out. The events run into the same attendance logic as browser stamps (corrections, reports). Device tokens and badge IDs are stored hashed only.',

    'new_heading' => 'Terminal ingest URL',
    'new_hint' => 'Enter it into the terminal now — the token is shown only this once.',

    'terminals_heading' => 'Terminals',
    'no_terminals' => 'No terminal registered yet.',
    'badges_heading' => 'Badges',
    'no_badges' => 'No badge assigned yet.',

    'field' => [
        'name' => 'Label',
        'name_placeholder' => 'e.g. Hall North',
        'site' => 'Site',
        'no_site' => '— no site —',
    ],

    'badge' => [
        'user' => 'Employee',
        'label' => 'Label',
        'uid' => 'Badge ID',
        'uid_placeholder' => 'RFID/NFC UID',
        'uid_help' => 'Stored as a hash only (no plaintext ID).',
    ],

    'action' => [
        'register' => 'Register',
        'disable' => 'Disable',
        'assign' => 'Assign',
        'revoke' => 'Revoke',
    ],

    'col' => [
        'status' => 'Status',
        'last_seen' => 'Last seen',
    ],

    'status' => [
        'active' => 'Active',
        'inactive' => 'Disabled',
        'revoked' => 'Revoked',
    ],

    'flash' => [
        'registered' => 'Terminal registered.',
        'terminal_disabled' => 'Terminal disabled.',
        'badge_assigned' => 'Badge assigned.',
        'badge_revoked' => 'Badge revoked.',
        'badge_taken' => 'This badge ID is already assigned.',
    ],
];
