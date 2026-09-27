<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : onboarding.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'page' => [
        'title' => 'Onboarding',
        'heading' => 'Onboarding checklist',
        'progress_label' => 'Progress',
        'progress_summary' => 'Required steps: :done of :total (:percent %)',
        'badge_required' => 'Required',
        'badge_recommended' => 'Recommended',
        'badge_done' => 'Done',
        'badge_open' => 'Open',
        'badge_skipped' => 'Skipped',
    ],

    'widget' => [
        'title' => 'Set up onboarding',
        'subtitle' => ':done of :total required steps done',
        'open_link' => 'Open onboarding',
        'dismiss' => 'Dismiss widget',
        'dismissed_at' => 'Widget dismissed: :date',
        'complete_headline' => 'All required steps done',
        'complete_subtitle' => 'The organisation is ready to go.',
        'open_steps' => '{0} No open steps|{1} :count open step|[2,*] :count open steps',
    ],

    'action' => [
        'skip' => 'Skip',
        'skip_placeholder' => 'Reason for skipping',
        'flash_skipped' => 'Onboarding step has been skipped.',
        'flash_dismissed' => 'Onboarding widget has been dismissed.',
        'error_step_not_skippable' => 'This onboarding step cannot be skipped.',
    ],

    'step' => [
        'org' => [
            'profile' => [
                'title' => 'Complete organisation details',
                'description' => 'Maintain name, timezone and local base settings of the organisation.',
                'link' => 'Open organisation',
            ],
            'branch_profile' => [
                'title' => 'Choose branch profile',
                'description' => 'Pick a branch profile so suitable defaults for classifications are available.',
                'link' => 'Open branch profiles',
            ],
            'scope' => [
                'title' => 'Choose feature scope',
                'description' => 'Pick a feature-scope preset or adjust the active modules — anything you do not need stays hidden without losing any data.',
                'link' => 'Open feature scope',
            ],
            'workspaces' => [
                'title' => 'Set up workspaces',
                'description' => 'Choose which workspaces appear in the switcher and which is the default — everyone can switch any time.',
                'link' => 'Open workspaces',
            ],
        ],
        'users' => [
            'invite' => [
                'title' => 'Invite first users',
                'description' => 'Invite at least one additional active person into your organisation.',
                'link' => 'Open members',
            ],
        ],
        'roles' => [
            'check' => [
                'title' => 'Verify roles',
                'description' => 'Ensure that at least one org-admin and one operator are assigned.',
                'link' => 'Open access management',
            ],
        ],
        'classification' => [
            'check' => [
                'title' => 'Verify classifications',
                'description' => 'Confirm or override at least one classification domain for the organisation.',
                'link' => 'Open classifications',
            ],
        ],
        'customer' => [
            'first' => [
                'title' => 'Create first customer',
                'description' => 'Add the first customer manually or via CSV import.',
                'link' => 'Open customers',
            ],
        ],
        'work' => [
            'first' => [
                'title' => 'First project or job',
                'description' => 'Create a first project or start the first diary entry.',
                'link' => 'Open projects',
            ],
        ],
        'time' => [
            'first' => [
                'title' => 'First time entry',
                'description' => 'Capture at least one time entry to activate time tracking.',
                'link' => 'Open time tracking',
            ],
        ],
        'protocol' => [
            'first_signed' => [
                'title' => 'Sign first protocol',
                'description' => 'Create a protocol and complete the signature.',
                'link' => 'Open diary',
            ],
        ],
        'backup' => [
            'heartbeat' => [
                'title' => 'Backup heartbeat',
                'description' => 'Configure the backup run so that successful heartbeats are written regularly.',
                'link' => 'Open audit log',
            ],
        ],
    ],
    // Persönlicher Einstieg je Rolle (MVP-911).
    'personal' => [
        'title' => 'My start',
        'description' => 'A few steps to get started with WorkDiary in your role. WorkDiary detects completed steps itself; tick off steps without an indicator.',
        'progress' => ':done of :total steps done',
        'open' => 'Open start',
        'go' => 'Open',
        'mark_done' => 'Done',
        'dismiss' => 'Hide start',
        'marked' => 'Step marked as done.',
        'dismissed' => 'Start hidden; you can still find it under “My start”.',
        'step' => [
            'profile' => [
                'two_factor' => [
                    'title' => 'Set up a second factor',
                    'hint' => 'Protects your account with an app, passkey or security key.',
                ],
                'startpage' => [
                    'title' => 'Choose your start page',
                    'hint' => 'Decide what WorkDiary opens after you sign in.',
                ],
            ],
            'dashboard' => [
                'customize' => [
                    'title' => 'Customise the dashboard',
                    'hint' => 'Show the tiles you need every day.',
                ],
            ],
            'time' => [
                'first' => [
                    'title' => 'Record your first time entry',
                    'hint' => 'Book working time, for example via “Today”.',
                ],
            ],
            'attendance' => [
                'first' => [
                    'title' => 'Clock in',
                    'hint' => 'Record arrival and departure once.',
                ],
            ],
            'expense' => [
                'first' => [
                    'title' => 'Record expenses',
                    'hint' => 'Record a receipt or a trip as an expense.',
                ],
            ],
            'diary' => [
                'first' => [
                    'title' => 'Create your first order',
                    'hint' => 'Create and assign an order.',
                ],
            ],
            'invoice' => [
                'first' => [
                    'title' => 'Create your first invoice',
                    'hint' => 'Create an invoice as a draft.',
                ],
            ],
            'reports' => [
                'accounting' => [
                    'title' => 'Explore the financial reports',
                    'hint' => 'Open the overview of the financial reports once.',
                ],
            ],
            'org' => [
                'checklist' => [
                    'title' => 'Organisation setup',
                    'hint' => 'Go through the organisation checklist.',
                ],
            ],
            'help' => [
                'center' => [
                    'title' => 'Open the help centre',
                    'hint' => 'Guides and answers for your tasks.',
                ],
            ],
        ],
    ],
];
