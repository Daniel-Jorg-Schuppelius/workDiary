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
        'requested' => 'requested',
        'confirmed' => 'confirmed',
        'declined' => 'declined',
        'canceled' => 'cancelled',
        'superseded' => 'replaced',
    ],
    // Kundenportal (Feature 087).
    'portal' => [
        'cancel_expired' => 'Cancellation period expired',
        'cancel_policy' => 'Cancellation is possible up to :hours hours before the appointment starts.',
        'cancel_until' => 'Can be cancelled until :date',
        'order_cancel_reason' => 'Appointment cancelled by the customer in the customer portal.',
        'order_in_progress' => 'This appointment is already being processed — please call us.',
    ],
    'notification' => [
        'canceled_title' => 'Appointment cancelled by :customer',
        'message' => ':service on :date',
        'requested_title' => 'Appointment request from :customer',
    ],
];
