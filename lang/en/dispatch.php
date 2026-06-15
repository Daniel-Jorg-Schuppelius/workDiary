<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : dispatch.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'heading' => 'Dispatch',
    'badge_prefix' => 'Dispatch',
    'set_status' => ':status',
    'override_reason' => 'Override reason',
    'override_placeholder' => 'Why confirm despite the conflict?',
    'conflicts' => [
        'hard' => 'Hard conflicts',
        'soft' => 'Warnings',
        'none' => 'No conflicts for this assignment.',
    ],
    'vehicle' => [
        'heading' => 'Vehicle reservation',
        'label' => 'Vehicle',
        'from' => 'From',
        'to' => 'To',
        'reserve' => 'Reserve',
        'release' => 'Release',
        'none' => 'No vehicle reservation for this order.',
    ],
    'reservations' => [
        'title' => 'Vehicle reservations',
        'subtitle' => 'Manage reservations per vehicle.',
        'all_vehicles' => 'All vehicles',
        'reserved_by' => 'Reserved by',
        'empty' => 'No reservations available.',
    ],
];
