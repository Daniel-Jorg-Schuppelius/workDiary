<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : scheduler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Scheduled tasks',
        'subtitle' => 'Pause, reschedule and monitor registry jobs — without code changes.',
        'help' => 'Registered jobs only, allowed times only',
        'help_text' => 'All jobs come from the server-side job registry. Rescheduling is limited to the intervals allowed per job; changes are audited and take effect on the next scheduler tick.',
        'reschedule' => 'Reschedule job',
    ],
    'field' => [
        'job' => 'Job',
        'plan' => 'Schedule',
        'last_run' => 'Last run',
        'next_due' => 'Next due',
        'failures' => 'Consecutive failures',
        'actions' => 'Actions',
        'cadence_type' => 'Interval',
        'time' => 'Time',
        'day' => 'Day',
        'expression' => 'Cron expression',
    ],
    'action' => [
        'reschedule' => 'Reschedule',
        'pause' => 'Pause',
        'resume' => 'Resume',
        'reset' => 'Reset to default',
        'test_run' => 'Start test run',
        'save' => 'Save',
    ],
    'state' => [
        'paused' => 'Paused',
        'success' => 'Successful',
        'failed' => 'Failed',
        'never_ran' => 'Never ran',
    ],
    'source' => [
        'default' => 'Default plan',
        'setting' => 'From setting',
        'override' => 'Manually rescheduled',
    ],
    'cadence' => [
        'everyMinute' => 'Every minute',
        'everyFiveMinutes' => 'Every 5 minutes',
        'everyFifteenMinutes' => 'Every 15 minutes',
        'everyThirtyMinutes' => 'Every 30 minutes',
        'hourly' => 'Hourly',
        'dailyAt' => 'Daily at',
        'weeklyOn' => 'Weekly on',
        'monthlyOn' => 'Monthly on',
        'cron' => 'Cron expression',
    ],
    'criticality' => [
        'core' => 'Core operations',
        'integration' => 'Integration',
        'housekeeping' => 'Housekeeping',
    ],
    'hint' => [
        'time' => 'Only for daily/weekly/monthly plans.',
        'day' => 'Weekday 0–6 (0 = Sunday) or day of month 1–31.',
        'expression' => 'Operators only: minute hour day month weekday.',
        'allowlist' => 'Expected runtime approx. :runtime min. The job runs with overlap protection; intervals that are too tight are rejected server-side.',
    ],
    'flash' => [
        'rescheduled' => 'Job :job has been rescheduled.',
        'paused' => 'Job :job has been paused.',
        'resumed' => 'Job :job has been resumed.',
        'reset' => 'Job :job uses the default plan again.',
        'test_run_queued' => 'Test run for :job has been queued.',
        'test_run_cooldown' => 'Please wait — only one test run per job every :minutes minutes.',
    ],
];
