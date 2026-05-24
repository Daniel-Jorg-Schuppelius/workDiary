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
        'org.profile' => [
            'title' => 'Complete organisation details',
            'description' => 'Maintain name, timezone and local base settings of the organisation.',
            'link' => 'Open organisation',
        ],
        'org.branch_profile' => [
            'title' => 'Choose branch profile',
            'description' => 'Pick a branch profile so suitable defaults for classifications are available.',
            'link' => 'Open branch profiles',
        ],
        'users.invite' => [
            'title' => 'Invite first users',
            'description' => 'Invite at least one additional active person into your organisation.',
            'link' => 'Open members',
        ],
        'roles.check' => [
            'title' => 'Verify roles',
            'description' => 'Ensure that at least one org-admin and one operator are assigned.',
            'link' => 'Open access management',
        ],
        'classification.check' => [
            'title' => 'Verify classifications',
            'description' => 'Confirm or override at least one classification domain for the organisation.',
            'link' => 'Open classifications',
        ],
        'customer.first' => [
            'title' => 'Create first customer',
            'description' => 'Add the first customer manually or via CSV import.',
            'link' => 'Open customers',
        ],
        'work.first' => [
            'title' => 'First project or job',
            'description' => 'Create a first project or start the first diary entry.',
            'link' => 'Open projects',
        ],
        'time.first' => [
            'title' => 'First time entry',
            'description' => 'Capture at least one time entry to activate time tracking.',
            'link' => 'Open time tracking',
        ],
        'protocol.first_signed' => [
            'title' => 'Sign first protocol',
            'description' => 'Create a protocol and complete the signature.',
            'link' => 'Open diary',
        ],
        'backup.heartbeat' => [
            'title' => 'Backup heartbeat',
            'description' => 'Configure the backup run so that successful heartbeats are written regularly.',
            'link' => 'Open audit log',
        ],
    ],
];
