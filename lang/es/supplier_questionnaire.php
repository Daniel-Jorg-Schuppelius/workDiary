<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : supplier_questionnaire.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Lieferanten-Selbstauskunft (MVP-937).
return [
    'title' => 'Autoevaluación de proveedores',
    'subtitle' => 'Cuestionarios para proveedores (p. ej. sostenibilidad, cadena de suministro, calidad) con enlace único, revisión y validez.',
    'card' => 'Autoevaluación',
    'questionnaires' => 'Cuestionarios',
    'recent' => 'Solicitudes recientes',
    'create' => 'Crear cuestionario',
    'edit' => 'Editar cuestionario',
    'save' => 'Guardar',
    'send' => 'Solicitar autoevaluación',
    'send_hint' => 'El proveedor recibe por correo un enlace válido :days días. Una solicitud abierta del mismo cuestionario se retira.',
    'open' => 'Abrir',
    'none' => 'Aún no se ha solicitado ninguna autoevaluación.',
    'empty' => 'Aún no hay cuestionarios.',
    'no_requests' => 'Aún no hay solicitudes.',
    'inactive' => 'inactivo',
    'valid_until' => 'válido hasta el :date',
    'submitted_at' => 'enviado el :date',
    'waiting' => 'Esperando respuesta de :email (enlace válido hasta el :date).',
    'accept' => 'Aceptar',
    'reject' => 'Devolver para corregir',
    'public_title' => 'Autoevaluación para :org',
    'public_submit' => 'Enviar respuestas',
    'public_thanks' => 'Gracias, hemos recibido sus respuestas.',
    'public_rework' => 'Complete sus respuestas: :note',
    'field' => [
        'name' => 'Cuestionario',
        'description' => 'Nota para el proveedor',
        'questions' => 'Preguntas',
        'validity_months' => 'Validez (meses)',
        'is_active' => 'Activo',
        'requests' => 'Solicitudes',
        'recipient_email' => 'Correo del proveedor',
        'sent_at' => 'Solicitado',
        'status' => 'Estado',
        'valid_until' => 'Válido hasta',
        'note' => 'Observación',
    ],
    'status' => [
        'sent' => 'Solicitado',
        'submitted' => 'Enviado',
        'accepted' => 'Aceptado',
        'rejected' => 'Devuelto para corregir',
        'withdrawn' => 'Retirado',
    ],
    'flash' => [
        'saved' => 'Cuestionario guardado.',
        'sent' => 'Solicitud enviada a :email.',
        'reviewed' => 'Revisión guardada.',
    ],
    'error' => [
        'transition' => 'La autoevaluación no puede pasar de «:from» a «:to».',
    ],
    'mail' => [
        'subject' => 'Autoevaluación para :org',
        'body' => "Buenos días:\n\n:org le solicita la autoevaluación «:name». Complete el cuestionario antes del :until:\n:url\n\nGracias.",
    ],
];
