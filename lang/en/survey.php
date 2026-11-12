<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : survey.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Umfragen (Feature 090, Phase 156).
return [
    'trend' => [
        'title' => 'NPS per month',
        'unit' => 'NPS',
    ],
    'triggers' => [
        'title' => 'Triggers',
        'order' => 'Invite automatically after an order is completed',
        'course' => 'Invite automatically after a course is completed',
        'schedule' => 'Invite at a fixed interval (days)',
        'scheduleHint' => 'Customers with a completed order or resolved ticket within the interval, at most once per interval. Empty = off.',
        'saved' => 'Triggers saved.',
    ],
    // Questionnaire header (MVP-1610).
    'header' => [
        'edit_title' => 'Edit questionnaire',
        'updated' => 'Questionnaire saved.',
        'anonymous_locked' => 'Anonymity can no longer be changed after the first invitation.',
    ],
];
