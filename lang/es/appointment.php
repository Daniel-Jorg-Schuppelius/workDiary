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
        'proposed' => 'alternativa propuesta',
        'superseded' => 'reemplazada',
    ],
    // Kundenportal (Feature 087).
    'portal' => [
        'proposal' => 'Propuesta: :date',
        'proposal_accept' => 'Aceptar',
        'proposal_decline' => 'Rechazar',
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
    'proposal' => [
        'action' => 'Otra cita',
        'title' => 'Proponer otra cita',
        'start' => 'Cita propuesta',
        'note' => 'Nota para el cliente (opcional)',
        'submit' => 'Enviar propuesta',
        'sent' => 'Otra cita propuesta: el cliente decide en el portal.',
        'badge' => 'Propuesta: :date',
        'in_past' => 'La cita está en el pasado.',
        'unavailable' => 'No hay ninguna propuesta abierta para esta solicitud.',
        'accepted' => 'Cita aceptada: recibirá una confirmación.',
        'declined' => 'Propuesta rechazada: la solicitud se ha cancelado.',
    ],
    'reschedule' => [
        'action' => 'Cambiar cita',
        'title' => 'Cambiar la cita',
        'start' => 'Nueva cita',
        'submit' => 'Cambiar',
        'done' => 'Cita cambiada: el cliente recibe la nueva hora.',
        'not_confirmed' => 'Solo se pueden cambiar citas confirmadas.',
    ],
    'mail' => [
        'proposed_subject' => 'Propuesta de cita: :service',
        'proposed_title' => 'Le proponemos otra cita',
        'proposed_body' => 'Lamentablemente, la hora solicitada para :service el :wish no está disponible. Le proponemos el :date.',
        'proposed_action' => 'Aceptar o rechazar en el portal de clientes',
        'rescheduled_title' => 'Su cita se ha cambiado',
    ],
];
