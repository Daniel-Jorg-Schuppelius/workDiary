<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : notifications.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'push' => [
        /** Max characters for the push notification body preview. */
        'body_truncate' => (int) env('NOTIFICATIONS_PUSH_BODY_TRUNCATE', 120),
    ],
];
