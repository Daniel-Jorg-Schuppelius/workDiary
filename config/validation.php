<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : validation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 *
 * Validation length / range limits used in form requests. Centralised so
 * they can be tuned per organisation without touching controllers.
 */

return [
    'attendance' => [
        'note_max' => (int) env('VALIDATION_ATTENDANCE_NOTE_MAX', 1000),
        'device_max' => (int) env('VALIDATION_ATTENDANCE_DEVICE_MAX', 64),
        'break_minutes_max' => (int) env('VALIDATION_ATTENDANCE_BREAK_MAX', 600),
    ],
    'tag' => [
        'name_max' => (int) env('VALIDATION_TAG_NAME_MAX', 60),
    ],
    'comment' => [
        'body_max' => (int) env('VALIDATION_COMMENT_BODY_MAX', 5000),
    ],
    'duty_plan' => [
        'note_max' => (int) env('VALIDATION_DUTY_PLAN_NOTE_MAX', 2000),
    ],
];
