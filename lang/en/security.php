<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : security.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Security',
    ],

    'subtitle' => 'Read-only overview of security-relevant state: active sessions, API tokens, external integrations, recent exports and support accesses.',

    'scope' => [
        'label' => 'Scope',
        'platform' => 'Platform-wide',
    ],

    'privacy_notice' => 'This page shows metadata only. Token values, hashes, secrets, passwords or session contents are never displayed. All data stays local.',

    'deferred_notice' => 'The automated deletion and retention runs are not part of this overview and follow in a later step (Feature 016, "Later").',

    'section' => [
        'advisories' => 'Dependency security posture',
        'sessions' => 'Active sessions',
        'tokens' => 'API tokens',
        'integrations' => 'External integrations',
        'exports' => 'Recent exports',
        'support_access' => 'Recent support accesses',
        'two_factor' => 'Two-factor authentication',
        'encryption' => 'Encryption (at rest)',
    ],

    'field' => [
        'severity' => 'Severity',
        'package' => 'Package',
        'advisory' => 'Advisory',
        'fixed_in' => 'Fixed in',
        'statement' => 'Assessment (VEX)',
        'statement_placeholder' => 'e.g. not exploitable — feature not in use',
        'last_pull' => 'Last pull',
        'user' => 'User',
        'guest' => 'Not signed in',
        'ip' => 'IP address',
        'user_agent' => 'User agent',
        'last_activity' => 'Last activity',
        'sessions_total' => 'Sessions total',
        'sessions_active' => 'Of which active (< 2 h)',
        'token_name' => 'Name',
        'abilities' => 'Abilities',
        'last_used_at' => 'Last used',
        'expires_at' => 'Expires',
        'created_at' => 'Created',
        'tokens_total' => 'Tokens total',
        'plugins_active' => 'Active plugins',
        'external_references' => 'External references',
        'export_kind' => 'Kind',
        'export_subject' => 'Subject',
        'format' => 'Format',
        'status' => 'Status',
        'rows' => 'Records',
        'event' => 'Event',
        'subject' => 'Subject',
        'users_total' => 'Users total',
        'users_with_2fa' => 'With active 2FA',
        'credentials' => 'Confirmed factors',
        'coverage' => 'Coverage',
        'encrypted_fields' => 'Encrypted fields',
        'table' => 'Table',
        'fields' => 'Fields',
    ],

    'export' => [
        'kind' => [
            'data_transfer' => 'Data transfer',
            'time' => 'Time export',
        ],
    ],

    'status' => [
        'active' => 'active',
        'inactive' => 'inactive',
        'app_key_set' => 'APP_KEY set',
        'app_key_missing' => 'APP_KEY missing',
    ],

    'hint' => [
        'advisories' => 'Source: OSV.dev for composer.lock/package-lock.json — pulled daily (security:advisories-pull); assessment (VEX) is manual.',
        'sessions_driver' => 'Session driver ":driver" — no database overview available. Only the "database" driver provides a session list.',
        'tokens_no_secret' => 'Only metadata is shown — never the token value or its hash.',
        'support_access' => 'Source: audit log, event prefix "support." (see support access principles).',
        'two_factor' => 'Plain count of confirmed factors — no secrets are read.',
        'encryption' => 'These fields are encrypted via "php artisan :command". Encryption depends on the APP_KEY.',
    ],

    'empty' => [
        'advisories' => 'No open security advisories.',
        'sessions' => 'No sessions found.',
        'tokens' => 'No active API tokens.',
        'integrations' => 'No active external integrations.',
        'exports' => 'No exports recorded yet.',
        'support_access' => 'No support accesses logged.',
    ],

    'generated_at' => 'Generated: :at',
    'action' => [
        'pull_advisories' => 'Pull now',
    ],
];
