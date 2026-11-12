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
    // Online-Vorauszahlung der Direktbuchung (MVP-1515).
    'prepayment' => [
        'hint' => 'A direct booking requires an online prepayment of :percent % of the gross price. The reservation is held for :minutes minutes; the booking is confirmed once the payment arrives.',
        'amount' => 'Prepayment :amount',
        'due' => 'Payment by :until',
        'pay_now' => 'Pay now',
        'unavailable' => 'Direct booking is currently not possible because no online payment is set up. Please send a request instead.',
        'not_due' => 'No payment is open for this booking.',
        'expired_error' => 'The payment deadline has passed; the reservation has been released.',
        'checkout_failed' => 'The payment page is currently unavailable. Your reservation remains until :until — please try again via “Pay now”.',
        'description' => 'Prepayment :subject from :from',
        'confirmed' => 'Payment received — your booking is confirmed.',
        'pending' => 'The payment has not been confirmed yet. As soon as the payment provider reports it, your booking is confirmed.',
        'late_notification' => 'Prepayment after the deadline from :customer',
        'late_message' => ':subject: the reservation had already expired. Please book again or refund the payment.',
        'expired_reason' => 'Online prepayment not received in time.',
        'withdrawn_reason' => 'Unpaid direct booking withdrawn by the customer.',
        'on_case' => 'Online prepayment :amount paid — credited to the invoice.',
        'credited_on_case' => 'Online prepayment of :amount handed over to the accounting program with the rental items — credit it on the invoice there.',
        'profile_percent' => 'Prepayment for direct booking (%)',
        'profile_percent_hint' => 'Empty = organization default, 0 = no prepayment for this rental item.',
        'badge' => 'Prepayment :percent %',
        'hint_list' => 'A direct booking requires the online prepayment shown in the list. The reservation is held for :minutes minutes; the booking is confirmed once the payment arrives.',
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
    // Vormerkhinweis, Protokollpositionen, Mindestzustand, Reinigung (MVP-1604).
    'condition' => [
        'below_minimum' => ':asset is in condition “:current”, below the minimum condition “:minimum” of its rental profile, and cannot be assigned.',
        'below_badge' => 'below minimum condition',
    ],
    'soft' => [
        'title' => 'There are tentative bookings for these devices in this period',
        'hint' => 'Tentative bookings do not block. Please clarify with the cases listed who gets the device.',
        'reserved_warning' => 'There is :count tentative booking from another case in this period. The cases affected are listed in the file.|There are :count tentative bookings from other cases in this period. The cases affected are listed in the file.',
    ],
    'cleaning' => [
        'not_open' => 'This window is not an open cleaning window.',
        'done' => 'Cleaning done — the device can be assigned again.',
        'mark_done' => 'Cleaning done',
        'form_hint' => 'Creates a cleaning window; the device cannot be assigned until cleaning is done.',
    ],
    'report' => [
        'checklist' => 'Checklist',
        'accessories' => 'Accessories',
        'quantity_of' => 'Quantity :label',
        'accessories_hint' => 'Untick a part that is missing.',
        'accessory_missing' => 'missing',
        'condition_items' => 'Condition items',
        'item_label' => 'Part or check point',
        'item_label_n' => 'Condition item :n: name',
        'item_state_n' => 'Condition item :n: finding',
        'item_note' => 'Remark',
        'item_note_n' => 'Condition item :n: remark',
        'rows_hint' => 'Empty rows are not saved.',
        'consumables' => 'Consumption',
        'consumable_label' => 'Consumable',
        'consumable_label_n' => 'Consumption :n: name',
        'consumable_quantity_n' => 'Consumption :n: quantity',
        'consumable_unit_n' => 'Consumption :n: unit',
        'details' => 'Show items',
        'point_done' => 'done',
        'point_open' => 'open',
        'returns' => 'Returns',
    ],
    'profile' => [
        'home_site' => 'Home site',
        'min_condition' => 'Minimum condition',
        'min_condition_hint' => 'If the current condition is below it, the device cannot be assigned.',
        'no_minimum' => 'no minimum condition',
        'current_condition' => 'Current condition',
        'current_condition_hint' => 'Taken from the report at every return; adjust it here after a repair.',
        'condition_unknown' => 'not recorded',
        'checklist' => 'Checklist for handover and return (one check point per line)',
        'checklist_hint' => 'The check points appear for ticking off in the handover and return report.',
    ],
    'deposit' => [
        'waive' => 'Waive',
        'waive_reason' => 'Reason for waiving',
        'flash' => [
            'waived' => 'Deposit waived.',
        ],
        'error' => [
            'not_waivable' => 'Only requested deposits that have not been received can be waived.',
            'reason_required' => 'Waiving a deposit requires a reason.',
        ],
    ],
];
