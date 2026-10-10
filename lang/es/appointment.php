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
        'requested' => 'solicitada',
        'confirmed' => 'confirmada',
        'declined' => 'rechazada',
        'canceled' => 'cancelada',
        'superseded' => 'reemplazada',
    ],
    // Kundenportal (Feature 087).
    'portal' => [
        'cancel_expired' => 'Plazo de cancelación vencido',
        'cancel_policy' => 'Puede cancelar hasta :hours horas antes del inicio de la cita.',
        'cancel_until' => 'Cancelable hasta el :date',
        'order_cancel_reason' => 'Cita cancelada por el cliente en el portal de clientes.',
        'order_in_progress' => 'Esta cita ya se está atendiendo — llámenos.',
    ],
    'notification' => [
        'canceled_title' => 'Cita cancelada por :customer',
        'message' => ':service el :date',
        'requested_title' => 'Solicitud de cita de :customer',
    ],
];
