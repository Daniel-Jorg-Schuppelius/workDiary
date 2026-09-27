<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : rental.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'terms' => [
        'title' => 'Rental terms',
        'signed' => 'Contract :contract, version :revision, signed on :date',
        'missing' => 'No signed rental terms exist for this customer.',
        'missing_required' => 'No signed rental terms — handover is only possible afterwards.',
        'create_agreement' => 'Create rental terms',
        'required' => 'Handover requires rental terms signed by the customer (organisation setting).',
    ],
    // Direktbuchung und Preisangabe im Portal (MVP-916).
    'portal' => [
        'price' => 'Price (net)',
        'price_estimate' => 'approx. :amount',
        'direct_intro' => 'You can book available equipment directly, or request equipment or an equipment group and we will confirm. The price is taken from the price list and is net.',
        'direct_book' => 'Book now',
        'direct_hint' => 'Reserves the selected equipment immediately if it is available in the period.',
        'direct_booked' => 'Equipment reserved — we will send you the handover documents.',
        'direct_status' => 'Booked directly',
        'direct_disabled' => 'Direct booking is not enabled.',
        'direct_case_note' => 'Direct booking from the customer portal.',
        'direct_notification' => 'Direct booking by :customer',
    ],
    // Mietpreisregeln (MVP-950).
    'rule' => [
        'title' => 'Rental price rules',
        'empty' => 'No rules: the daily rate applies.',
        'add' => 'Add rule',
        'line' => ':label (:percent %)',
        'from_utilization' => 'from :percent % utilisation',
        'kind' => [
            'season' => 'Season',
            'weekday' => 'Weekdays',
            'utilization' => 'Utilisation',
        ],
        'field' => [
            'kind' => 'Type',
            'label' => 'Label',
            'valid_from' => 'Valid from',
            'valid_until' => 'Valid until',
            'weekdays' => 'Weekdays',
            'utilization_min_percent' => 'From utilisation (%)',
            'adjust_percent' => 'Surcharge/discount (%)',
        ],
        'weekday' => [
            '1' => 'Mon',
            '2' => 'Tue',
            '3' => 'Wed',
            '4' => 'Thu',
            '5' => 'Fri',
            '6' => 'Sat',
            '7' => 'Sun',
        ],
        'flash' => [
            'saved' => 'Rental price rule saved.',
            'deleted' => 'Rental price rule removed.',
        ],
    ],
];
