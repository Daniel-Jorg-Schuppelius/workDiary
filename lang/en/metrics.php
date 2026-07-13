<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : metrics.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Operations metrics',
    ],

    'subtitle' => 'Technical key figures and aggregated feature usage of this installation.',

    'privacy_notice' => 'All metrics are collected and stored locally only. Nothing is sent externally; feature usage is counted solely as a daily aggregate per organisation — without personal reference and without business content.',

    'section' => [
        'queue' => 'Queue',
        'backups' => 'Backup heartbeats',
        'plugin_errors' => 'Plugin errors (7 days)',
        'storage' => 'Storage',
        'active_users' => 'Active users (30 days)',
        'module_counts' => 'Records per core module',
        'feature_usage' => 'Feature usage (30 days)',
        'transparency' => 'Metrics transparency',
    ],

    'field' => [
        'version' => 'Version',
        'queue_pending' => 'Pending jobs',
        'queue_failed' => 'Failed jobs',
        'attachments' => 'Attachments',
        'document_versions' => 'Document versions',
        'feature' => 'Feature',
        'usage_total' => 'Count',
        'last_used_on' => 'Last used',
        'counter_description' => 'What is counted',
    ],

    'transparency' => [
        'status_enabled' => 'Usage counters active',
        'status_disabled' => 'Usage counters disabled',
        'settings_link' => 'Switch "telemetry.enabled" in the settings',
        'intro' => 'This installation collects only the following usage counters — aggregated per organisation and day, without personal reference and without business content. No other telemetry exists.',
        'storage' => 'Storage location: local database of this installation (table feature_usage_counters). Nothing is sent to external systems.',
        'retention' => 'Retention: daily aggregates remain local; the evaluation above covers the last 30 days. With the switch disabled nothing is counted — existing aggregates are kept.',
    ],

    'counter' => [
        'communications' => [
            'created' => 'Communication notes created (count only)',
        ],
        'documents' => [
            'created' => 'Documents created (count only)',
        ],
        'forms' => [
            'submitted' => 'Forms submitted (count only)',
        ],
        'knowledge' => [
            'created' => 'Knowledge articles created (count only)',
        ],
        'timeExports' => [
            'built' => 'Time/payroll exports built (count only)',
        ],
    ],

    'module' => [
        'diary_entries' => 'Jobs (diary)',
        'protocols' => 'Protocols',
        'documents' => 'Documents',
        'form_submissions' => 'Forms (submitted)',
        'knowledge_articles' => 'Knowledge articles',
        'communication_notes' => 'Communication notes',
    ],

    'empty' => [
        'queue' => 'No queue tables available (sync driver).',
        'backups' => 'No backup heartbeats received yet.',
        'plugin_errors' => 'No plugin errors in the last 7 days.',
        'active_users' => 'No login data available.',
        'feature_usage' => 'No feature usage recorded yet.',
    ],

    'hint' => [
        'storage_db_metadata' => 'Count and size according to database metadata (no file system scan — disk usage is shown on the diagnostics page).',
        'active_users' => 'Distinct users with a login during the last 30 days (source: audit log).',
        'feature_usage_window' => 'Aggregated per organisation and day over the last 30 days. Data stays local.',
    ],

    'generated_at' => 'Generated: :at',
];
