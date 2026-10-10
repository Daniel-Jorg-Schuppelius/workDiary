<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : appointment.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Eigene Gruppe: die losen Schlüssel „bestätigt“/„storniert“ gehören der Buchhaltung (acknowledged/reversed).
return [
    'status' => [
        'requested' => 'angefragt',
        'confirmed' => 'bestätigt',
        'declined' => 'abgelehnt',
        'canceled' => 'storniert',
        'superseded' => 'ersetzt',
    ],
    // Kundenportal (Feature 087).
    'portal' => [
        'cancel_expired' => 'Stornofrist abgelaufen',
        'cancel_policy' => 'Stornieren ist bis :hours Stunden vor Terminbeginn möglich.',
        'cancel_until' => 'Stornierbar bis :date',
        'order_cancel_reason' => 'Termin vom Kunden im Kundenportal storniert.',
        'order_in_progress' => 'Dieser Termin wird bereits bearbeitet — bitte rufen Sie uns an.',
    ],
    'notification' => [
        'canceled_title' => 'Termin von :customer storniert',
        'message' => ':service am :date',
        'requested_title' => 'Terminanfrage von :customer',
    ],
];
