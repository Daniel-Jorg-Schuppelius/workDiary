<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : settingsregistry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Settings (registry)',
        'subtitle' => 'Registered system and organisation settings — with effective value, origin and rollback.',
        'help_text' => 'Only keys declared in the registry can be changed here; validation, sensitivity and auditing are defined per key. Infrastructure values (APP_KEY, database, mail transport) deliberately do not appear here.',
    ],
    'scopes' => [
        'system' => 'System (operator)',
        'organization' => 'Organisation',
        'user' => 'User',
    ],
    'sources' => [
        'organization' => 'Org override',
        'system' => 'System override',
        'config' => 'Configuration file',
        'default' => 'Default value',
    ],
    'field' => [
        'search' => 'Search keys…',
        'sensitive' => 'Sensitive',
        'sensitive_placeholder' => 'Enter new value (current value hidden)',
        'affects' => 'Affects',
    ],
    'action' => [
        'save' => 'Save',
        'reset' => 'Reset to default',
        'history' => 'History',
    ],
    'empty' => [
        'title' => 'No settings found',
        'message' => 'No registry keys exist for this scope or search term.',
        'history' => 'No changes logged yet.',
    ],
    'flash' => [
        'saved' => 'Setting :key saved.',
        'reset' => 'Setting :key reset to default.',
    ],
];
