<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : operations.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Operations tasks',
        'subtitle' => 'Updates, backups, expirations and outages — prioritised and trackable.',
        'widget' => 'Open operations tasks',
    ],
    'type' => [
        'backup_overdue' => 'Backup overdue',
        'backup_failed' => 'Backup failed',
        'restore_test_overdue' => 'Restore test overdue',
        'update_available' => 'Update available',
        'update_security' => 'Security update',
        'license_expiring' => 'License expiry',
        'license_limit_near' => 'User limit almost reached',
        'credential_expiring' => 'Credential/token expiry',
        'connection_failing' => 'Connection failure',
        'component_eol' => 'Component end-of-life',
        'plugin_disabled' => 'Plugin disabled',
        'scheduler_overdue' => 'Scheduled task overdue',
        'maintenance_scheduled' => 'Maintenance window',
        'config_missing' => 'Missing configuration',
        'support_grant_open' => 'Open support grant',
        'problem_report_open' => 'Open problem report',
        'cloud_intake_reauth' => 'Cloud intake: sign-in required',
        'cloud_intake_quarantined' => 'Cloud intake: imports rejected',
    ],
    'severity' => [
        'info' => 'Notice',
        'warning' => 'Warning',
        'critical' => 'Critical',
    ],
    'status' => [
        'open' => 'Open',
        'snoozed' => 'Snoozed',
        'delegated' => 'Delegated',
        'ignored' => 'Ignored',
        'done' => 'Done',
        'resolved' => 'Resolved itself',
    ],
    'field' => [
        'task' => 'Task',
        'severity' => 'Severity',
        'status' => 'Status',
        'first_seen' => 'First seen',
        'last_seen' => 'Last confirmed',
        'assignee' => 'Assignee',
        'actions' => 'Actions',
        'note' => 'Reason',
        'snooze_until' => 'Snooze until',
        'system_wide' => 'Installation-wide',
    ],
    'action' => [
        'done' => 'Done',
        'snooze' => 'Snooze',
        'delegate' => 'Delegate',
        'ignore' => 'Ignore',
        'reopen' => 'Reopen',
        'open_link' => 'Go to cause',
    ],
    'task' => [
        'backup_overdue' => 'Last backup is :hours hours old (threshold :threshold h).',
        'backup_failed' => 'Backup check failed: :reason',
        'backup_target_failed' => 'Cloud backup failed: :reason',
        'backup_target_verify_failed' => 'Cloud backup verification failed: :reason',
        'restore_test_overdue' => 'Last restore test was :days days ago (threshold :threshold days).',
        'restore_test_missing' => 'No restore test has ever been logged.',
        'update_available' => 'Update available for :component: :installed → :available.',
        'update_security' => 'Security update for :component: :installed → :available (:classification).',
        'license_expiring' => 'License expires on :date (:days days remaining).',
        'license_limit_near' => ':org: :current of :max licensed seats in use — extend the license in time.',
        'credential_expiring' => ':kind ":name" expires on :date.',
        'connection_failing' => 'Connection ":name" (:kind) failing: :error',
        'component_eol' => ':component :version has been unsupported since :date.',
        'plugin_disabled' => 'Plugin ":plugin" was disabled automatically after :failures failures.',
        'scheduler_overdue' => 'Scheduled task ":job" is overdue (due :due).',
        'maintenance_scheduled' => 'Maintenance window :from – :to::scope',
        'support_grant_open' => 'Support grant for :grantee active until :until.',
        'problem_report_open' => 'Problem report :reference from :name awaits processing.',
        'problem_report_summary' => ':count open problem report(s) await processing.',
        'cloud_intake_reauth' => 'Cloud document intake :provider (":folder") needs to be reconnected (:status).',
        'cloud_intake_quarantined' => ':count file(s) from the cloud document intake were rejected (last reason: :reason).',
        'support_grant_summary' => ':count active support grant(s) — review and revoke if needed.',
    ],
    'filter' => [
        'active' => 'Active tasks',
        'all_severities' => 'All severities',
        'all_types' => 'All types',
    ],
    'empty' => [
        'title' => 'No operations tasks',
        'message' => 'Nothing to do right now — all operations tasks are done or resolved themselves.',
    ],
    'hint' => [
        'auto_disabled_after' => 'Disabled automatically after :failures failed attempts.',
        'no_contact_since' => 'No contact since :date.',
    ],
    'flash' => [
        'done' => 'Task marked as done.',
        'snoozed' => 'Task snoozed until :date.',
        'delegated' => 'Task delegated.',
        'ignored' => 'Task ignored.',
        'reopened' => 'Task reopened.',
    ],
    'widget' => [
        'open' => 'Open tasks',
        'empty' => 'No open operations tasks.',
        'all' => 'Show all operations tasks',
    ],
];
