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
        'requested' => 'demandé',
        'confirmed' => 'confirmé',
        'declined' => 'refusé',
        'canceled' => 'annulé',
        'superseded' => 'remplacé',
    ],
    // Kundenportal (Feature 087).
    'portal' => [
        'cancel_expired' => 'Délai d’annulation expiré',
        'cancel_policy' => 'L’annulation est possible jusqu’à :hours heures avant le début du rendez-vous.',
        'cancel_until' => 'Annulable jusqu’au :date',
        'order_cancel_reason' => 'Rendez-vous annulé par le client dans le portail client.',
        'order_in_progress' => 'Ce rendez-vous est déjà en cours de traitement — veuillez nous appeler.',
    ],
    'notification' => [
        'canceled_title' => 'Rendez-vous annulé par :customer',
        'message' => ':service le :date',
        'requested_title' => 'Demande de rendez-vous de :customer',
    ],
];
