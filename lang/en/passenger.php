<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : passenger.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Passenger transport (MVP-456, taxi/rental car branch profile).
return [
    'entry_title' => 'Ride order (:mode)',
    'entry_content' => 'Passenger transport — order channel: :channel. Details in the ride record.',

    'error' => [
        'destination_required' => 'Provide a destination or explicitly confirm an open destination.',
        'receipt_channel_invalid' => 'Rental car / on-demand service requires a provable order receipt at the business seat — street hails are not permitted (§ 49 (4) PBefG).',
        'pickup_required' => 'Pickup location is required.',
        'not_assigned' => 'A ride can only start after dispatch (driver, vehicle, concession).',
        'tariff_required' => 'Taxi service runs on the regulated tariff — please select a tariff.',
        'fixed_price_outside_corridor' => 'The fixed price is outside the officially permitted corridor.',
        'meter_value_required' => 'The meter/device value is required to complete a ride.',
        'tax_decision_required' => 'The tax decision (rate) is required to complete a ride.',
        'payment_required' => 'The payment method is required to complete a ride.',
        'invalid_transition' => 'Invalid status transition.',
        'invalid_transition_detail' => 'Invalid status transition: :from → :to.',
        'return_not_applicable' => 'Return proof only applies to rental car service.',
    ],

    'issue' => [
        'driver_unqualified' => 'Passenger transport licence is missing or expired.',
        'concession_missing' => 'No valid concession for this operation mode.',
        'vehicle_profile_missing' => 'Vehicle has no passenger transport profile.',
        'vehicle_mode_unsupported' => 'Vehicle is not approved for this operation mode.',
        'vehicle_proofs_expired' => 'Vehicle proofs expired (calibration/BOKraft/inspection).',
        'vehicle_not_barrier_free' => 'Ride requires barrier-free access — the vehicle does not provide it.',
        'vehicle_no_wheelchair_place' => 'Ride requires a wheelchair place — the vehicle has none.',
        'vehicle_too_small' => 'Passenger count exceeds the vehicle seats.',
    ],

    'proof' => [
        'meter_calibration' => 'Taximeter/odometer calibration',
        'bokraft' => 'BOKraft inspection',
        'hu' => 'Main inspection',
    ],
];
