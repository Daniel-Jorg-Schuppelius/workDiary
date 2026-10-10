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
        'requested' => 'richiesta',
        'confirmed' => 'confermata',
        'declined' => 'rifiutata',
        'canceled' => 'annullata',
        'superseded' => 'sostituita',
    ],
    // Kundenportal (Feature 087).
    'portal' => [
        'cancel_expired' => 'Termine di annullamento scaduto',
        'cancel_policy' => 'È possibile annullare fino a :hours ore prima dell’inizio dell’appuntamento.',
        'cancel_until' => 'Annullabile fino al :date',
        'order_cancel_reason' => 'Appuntamento annullato dal cliente nel portale clienti.',
        'order_in_progress' => 'Questo appuntamento è già in lavorazione — ci chiami.',
    ],
    'notification' => [
        'canceled_title' => 'Appuntamento annullato da :customer',
        'message' => ':service il :date',
        'requested_title' => 'Richiesta di appuntamento da :customer',
    ],
];
