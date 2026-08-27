<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : dashboard.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'width' => [
        'half' => 'Half width',
        'full' => 'Full width',
    ],

    'group' => [
        'overview' => 'Overview',
        'time' => 'Time',
        'tasks' => 'Tasks',
        'activity' => 'Activity',
        'deadlines' => 'Deadlines',
        'finance' => 'Finance',
        'operations' => 'Operations',
    ],

    'widget' => [
        'personal_kpis' => [
            'description' => 'Open entries, work in progress, upcoming shifts and on-call duties.',
        ],
        'team_kpis' => [
            'description' => 'The team\'s open and in-progress entries, items archived today, headcount.',
        ],
        'today_shifts' => [
            'description' => 'Your shifts for today.',
        ],
        'upcoming_shifts' => [
            'description' => 'Your next on-call periods and shifts.',
        ],
        'emergencies' => [
            'description' => 'Upcoming on-call assignments.',
        ],
        'scheduled_shifts' => [
            'description' => 'Duty roster for the next seven days.',
        ],
        'open_issues' => [
            'description' => 'Open items assigned to you — by due date.',
        ],
        'recent_entries' => [
            'description' => 'Your most recently edited entries.',
        ],
        'recent_comments' => [
            'description' => 'New comments on your entries.',
        ],
        'recent_attachments' => [
            'description' => 'New attachments on your entries.',
        ],
        'team_activity' => [
            'description' => 'The latest comments across the team.',
        ],
        'finance' => [
            'description' => 'This month\'s expenses and trips, plus the pending stack for approvers.',
        ],
        'vacation' => [
            'description' => 'Pending leave requests and days approved this year.',
        ],
        'onboarding' => [
            'description' => 'Progress of the setup checklist.',
        ],
        'attendance_clock' => [
            'description' => 'Clock in and out, breaks and interim status.',
        ],
        'bookmarks' => [
            'description' => 'Your saved bookmarks.',
        ],
        'data_protection' => [
            'description' => 'Overdue record reviews and open data subject requests.',
        ],
        'operations_tasks' => [
            'description' => 'Open operations tasks by urgency.',
        ],
        'stopwatch' => [
            'description' => 'The running project timer with project and description.',
        ],
        'flex_balance' => [
            'description' => 'Flexitime balance of the last settled month, with traffic light.',
        ],
        'time_accounts' => [
            'description' => 'Balances of your time accounts (overtime, special accounts).',
        ],
        'time_corrections' => [
            'description' => 'Your correction requests still in progress or submitted.',
        ],
        'reminders' => [
            'description' => 'Due items from expenses, trips and leave — the same as under the bell.',
        ],
        'kanban_status' => [
            'description' => 'How many of your jobs sit in which Kanban column.',
        ],
        'service_tickets' => [
            'description' => 'Open tickets assigned to you.',
        ],
        'chat_unread' => [
            'description' => 'Unread messages per channel.',
        ],
        'approvals' => [
            'description' => 'Expenses and leave requests awaiting your decision.',
        ],
        'asset_compliance' => [
            'description' => 'Overdue and upcoming inspections from the inspection calendar.',
        ],
        'asset_blocks' => [
            'description' => 'Assets currently blocked, with the reason.',
        ],
        'contract_deadlines' => [
            'description' => 'Open contract obligations and deadlines in the coming weeks.',
        ],
        'leasing_deadlines' => [
            'description' => 'Notice, return and renewal deadlines from leasing files.',
        ],
        'safety_due' => [
            'description' => 'Upcoming reviews of hazard assessments and medical check-ups.',
        ],
        'training_due' => [
            'description' => 'Your open training and instruction obligations.',
        ],
        'open_times' => [
            'description' => 'Billable time not yet on any invoice.',
        ],
        'open_items' => [
            'description' => 'Open receivables and payables including the overdue share.',
        ],
        'tax_filings' => [
            'description' => 'Upcoming filing and submission dates in accounting.',
        ],
        'integration_inbox' => [
            'description' => 'Imported items still waiting to be matched.',
        ],
        'backup_status' => [
            'description' => 'How fresh the backups are, per source.',
        ],
        'plugin_health' => [
            'description' => 'Plugins whose last health check failed.',
        ],
    ],

    'preset' => [
        'classic' => [
            'label' => 'Classic dashboard',
            'description' => 'Metrics and bookmarks on top, below them the four sections Overview, Tasks, Activity and Finance — the dashboard as it was before the tile rebuild, plus the time clock.',
        ],
    ],
];
