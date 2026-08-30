<?php
/*
 * Created on   : Sun Aug 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : sync.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

return [
    'error' => [
        'stamp_in_future' => 'The timestamp lies in the future.',
        'stamp_too_old' => 'The timestamp is more than :days days old and is no longer accepted.',
        'day_locked' => 'The day is closed or the month released — please request a time correction.',
        'stamp_overlaps' => 'There is already a clocking for this point in time.',
    ],
];
