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
        'proposed' => 'alternative proposed',
        'superseded' => 'replaced',
    ],
    // Kundenportal (Feature 087).
    'portal' => [
        'proposal' => 'Proposal: :date',
        'proposal_accept' => 'Accept',
        'proposal_decline' => 'Decline',
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
    'proposal' => [
        'action' => 'Other time',
        'title' => 'Propose an alternative',
        'start' => 'Proposed appointment',
        'note' => 'Note to the customer (optional)',
        'submit' => 'Send proposal',
        'sent' => 'Alternative proposed — the customer decides in the portal.',
        'badge' => 'Proposal: :date',
        'in_past' => 'The appointment lies in the past.',
        'unavailable' => 'There is no open proposal for this request.',
        'accepted' => 'Appointment accepted — you will receive a confirmation.',
        'declined' => 'Proposal declined — the request has been cancelled.',
    ],
    'reschedule' => [
        'action' => 'Reschedule',
        'title' => 'Reschedule appointment',
        'start' => 'New appointment',
        'submit' => 'Reschedule',
        'done' => 'Appointment rescheduled — the customer receives the new time.',
        'not_confirmed' => 'Only confirmed appointments can be rescheduled.',
    ],
    'mail' => [
        'proposed_subject' => 'Appointment proposal: :service',
        'proposed_title' => 'We propose a different appointment',
        'proposed_body' => 'Unfortunately your requested time for :service on :wish is not available. We propose :date instead.',
        'proposed_action' => 'Accept or decline in the customer portal',
        'rescheduled_title' => 'Your appointment has been moved',
    ],
];
