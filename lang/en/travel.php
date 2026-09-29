<?php
/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : travel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'vehicle' => [
        'company' => 'Company car',
        'private' => 'Private car',
        'rental' => 'Rental car',
        'public_transport' => 'Public transport',
        'bicycle' => 'Bicycle',
        'foot' => 'On foot',
        'other' => 'Other',
    ],
    'trip_kind' => [
        'business' => 'Business',
        'commute' => 'Commute',
        'private' => 'Private',
    ],
    'tour_leg_purpose' => 'Leg to :title',
    'tour_return_purpose' => 'Return trip',
    'driving_time' => [
        'legend' => 'Driving and rest times',
        'hint' => 'For vehicles subject to driving time rules: second driver in multi-manning and crossings where the vehicle travels on a ferry or train.',
        'co_driver' => 'Second driver (multi-manning)',
        'co_driver_none' => '— driving alone —',
        'ferry_or_train' => 'Ferry/train: vehicle travels along, no driving time',
    ],
];
