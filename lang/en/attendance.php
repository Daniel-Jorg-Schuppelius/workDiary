<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : attendance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    // Intermediate statuses (MVP-532): home office/errand.
    'intermediate' => [
        'homeoffice' => 'Home office',
        'errand' => 'Errand',
        'start_homeoffice' => 'Start home office',
        'end_homeoffice' => 'End home office',
        'start_errand' => 'Start errand',
        'end_errand' => 'End errand',
    ],
    'status' => [
        'open' => 'Open',
        'closed' => 'Closed',
        'auto_closed' => 'Auto-closed',
        'adjusted' => 'Adjusted',
        'cancelled' => 'Cancelled',
    ],
    'source' => [
        'clock' => 'Clock',
        'manual' => 'Manual',
        'import' => 'Import',
        'auto_close' => 'Auto close',
        'terminal' => 'Terminal',
        'phone' => 'Phone',
        'learning' => 'Learning time',
        'checkin' => 'Check-in (QR/NFC)',
    ],
    'correction' => [
        'action' => [
            'create' => 'Create',
            'update' => 'Update',
            'delete' => 'Delete',
        ],
    ],
    'error' => [
        'target_day_locked' => 'The target day is closed or the month released — please request a time correction.',
        'duration_too_long' => 'A clocking must not exceed :hours hours.',
    ],
    'checkpoint_kind' => [
        'site' => 'Location',
        'vehicle' => 'Vehicle',
    ],
    'checkin' => [
        'title' => 'Check-in',
        'subtitle' => 'Clock in and out using the code at the location or vehicle.',
        'state' => [
            'in' => 'You have been clocked in since :time.',
            'out' => 'You are not clocked in right now.',
        ],
        'action' => [
            'in' => 'Clock in',
            'out' => 'Clock out',
        ],
        'location_hint' => 'Your position is checked once when clocking (radius :radius m). It is not stored.',
        'flash' => [
            'in' => 'Clock-in at “:name” recorded.',
            'out' => 'Clock-out at “:name” recorded.',
        ],
        'error' => [
            'already_in' => 'You are already clocked in.',
            'not_in' => 'You are not clocked in.',
            'no_center' => 'This point has a radius but no location. Please contact the administration.',
            'location_required' => 'This point requires your position.',
            'too_far' => 'You are :distance m away; :radius m are allowed.',
            'location_denied' => 'Your position could not be determined. Please allow location access.',
        ],
    ],
];
