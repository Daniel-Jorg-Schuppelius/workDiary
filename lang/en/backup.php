<?php
/*
 * Created on   : Sun Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : backup.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'status' => 'Backup & Restore',
        'log_restore_test' => 'Log a restore test',
    ],

    'subtitle' => 'Status of external backups per source, freshness warnings and the register of performed restore tests.',

    'section' => [
        'last_per_source' => 'Last backup per source',
        'restore_register' => 'Restore test register',
        'restore_test' => 'Restore test',
        'retention' => 'Retention',
    ],

    'field' => [
        'source' => 'Source',
        'occurred_at' => 'Timestamp',
        'age' => 'Age',
        'size' => 'Size',
        'manifest_hash' => 'Manifest hash',
        'state' => 'State',
        'tested_on' => 'Tested on',
        'result' => 'Result',
        'scope' => 'Scope',
        'restored_size' => 'Restored',
        'restored_size_bytes' => 'Restored size (bytes)',
        'duration' => 'Duration',
        'duration_minutes' => 'Duration (minutes)',
        'next_due' => 'Next due',
        'performed_by' => 'Performed by',
        'notes' => 'Notes',
        'last_passed' => 'Last successful test',
        'no_passed_test' => 'No successful restore test logged yet',
    ],

    'badge' => [
        'fresh' => 'fresh',
        'overdue' => 'overdue',
    ],

    'value' => [
        'hours' => ':n h',
        'minutes' => ':n min',
        'days_ago' => ':n days ago',
    ],

    'action' => [
        'log_restore_test' => 'Log a restore test',
        'save' => 'Save',
        'open_help' => 'Open backup handbook',
    ],

    'warn' => [
        'no_heartbeat_title' => 'No backup registered',
        'no_heartbeat_body' => 'No backup heartbeat has been received yet. Check that the external backup script runs and calls the heartbeat endpoint with a valid token.',
        'overdue_title' => 'Backup overdue',
        'overdue_body' => 'At least one source has not reported a heartbeat for more than :hours hours. Check the last backup.',
        'restore_overdue_title' => 'Restore test overdue',
        'restore_overdue_body' => 'No successful restore test has been logged for more than :days days. Please perform a recovery test and record it here.',
    ],

    'hint' => [
        'freshness' => 'A source is considered overdue if its latest heartbeat is older than :hours hours (configurable via BACKUP_HEARTBEAT_FRESHNESS_HOURS).',
        'register_manual' => 'This is an auditable register. The actual recovery is performed manually or via script outside of WorkDiary — automated restore execution is intentionally not part of this page.',
        'retention' => 'Recommended retention: 7 daily, 4 weekly, 12 monthly backups (3-2-1 rule). At least one offsite backup at a different location.',
        'see_docs' => 'Details on the strategy, the heartbeat and the step-by-step recovery are in docs/backup-restore.md.',
    ],

    'empty' => [
        'no_heartbeat' => 'No backup registered',
        'no_heartbeat_hint' => 'Once the external backup script sends a heartbeat, the last backup per source will appear here.',
        'no_restore_tests' => 'No restore tests logged yet',
    ],

    'placeholder' => [
        'source' => 'e.g. nightly, offsite, weekly-full',
        'scope' => 'e.g. DB+storage, attachments only',
        'notes' => 'Observations, conditions, deviations …',
    ],

    'flash' => [
        'restore_test_logged' => 'Restore test logged.',
    ],

    'generated_at' => 'As of: :at',
];
