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
        'proposed' => 'Ersatztermin vorgeschlagen',
        'superseded' => 'ersetzt',
    ],
    // Kundenportal (Feature 087).
    'portal' => [
        'proposal' => 'Vorschlag: :date',
        'proposal_accept' => 'Annehmen',
        'proposal_decline' => 'Ablehnen',
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
    'proposal' => [
        'action' => 'Anderer Termin',
        'title' => 'Ersatztermin vorschlagen',
        'start' => 'Vorgeschlagener Termin',
        'note' => 'Hinweis an den Kunden (optional)',
        'submit' => 'Vorschlag senden',
        'sent' => 'Ersatztermin vorgeschlagen — der Kunde entscheidet im Portal.',
        'badge' => 'Vorschlag: :date',
        'in_past' => 'Der Termin liegt in der Vergangenheit.',
        'unavailable' => 'Für diese Anfrage liegt kein offener Vorschlag vor.',
        'accepted' => 'Termin angenommen — Sie erhalten eine Bestätigung.',
        'declined' => 'Vorschlag abgelehnt — die Anfrage ist storniert.',
    ],
    'reschedule' => [
        'action' => 'Umbuchen',
        'title' => 'Termin umbuchen',
        'start' => 'Neuer Termin',
        'submit' => 'Umbuchen',
        'done' => 'Termin umgebucht — der Kunde erhält die neue Zeit.',
        'not_confirmed' => 'Umbuchen geht nur bei bestätigten Terminen.',
    ],
    'mail' => [
        'proposed_subject' => 'Terminvorschlag: :service',
        'proposed_title' => 'Wir schlagen Ihnen einen anderen Termin vor',
        'proposed_body' => 'Ihr Wunschtermin für :service am :wish Uhr ist leider belegt. Wir schlagen den :date Uhr vor.',
        'proposed_action' => 'Im Kundenportal annehmen oder ablehnen',
        'rescheduled_title' => 'Ihr Termin wurde verschoben',
    ],
];
