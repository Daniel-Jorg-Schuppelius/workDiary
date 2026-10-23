<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : activity.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Aktivitäts-Feed (Feature 183).
return [
    'title' => 'Activity',
    'subtitle' => 'Who changed what and when · :range',
    'subject_title' => 'Change history',
    'more' => 'Older entries are in the audit log.',
    'more_in_audit' => 'Older entries in the audit log',
    'newer' => 'Newer',
    'older' => 'Older',
    'filter' => [
        'type' => 'Object type',
        'all_types' => 'All object types',
        'user' => 'Person',
        'all_users' => 'All people',
    ],
];
