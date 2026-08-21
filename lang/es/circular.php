<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : circular.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

return [
    'title' => 'Circulares',
    'subtitle' => 'Comunicaciones comerciales a un grupo filtrado de clientes',
    'empty' => 'Todavía no se ha creado ninguna circular.',
    'empty_recipients' => 'No hay destinatarios registrados.',
    'created' => 'Circular creada.',
    'sent' => 'Circular enviada.',
    'already_sent' => 'Esta circular ya se ha enviado.',
    'no_recipients' => 'El filtro seleccionado no coincide con ningún cliente.',
    'mandatory_short' => 'Comunicación obligatoria',
    'portal_short' => 'Visible en el portal',
    'no_email' => 'sin dirección de correo',
    'confirm_send' => '¿Enviar ahora la circular a :count destinatarios?',
    'body_hint' => 'Marcadores: :firma, :kunde, :ansprechpartner',
    'mandatory_hint' => 'Las comunicaciones obligatorias también llegan a clientes que rechazaron los envíos masivos — solo para información exigida legalmente.',
    'portal_hint' => 'La comunicación aparece además en el portal de clientes.',

    'audience' => [
        'heading' => 'Destinatarios (:count)',
    ],

    'action' => [
        'create' => 'Crear circular',
        'save_draft' => 'Guardar como borrador',
        'send' => 'Enviar',
        'show' => 'Ver',
    ],

    'column' => [
        'subject' => 'Asunto',
        'status' => 'Estado',
        'recipients' => 'Destinatarios',
        'skipped' => 'No alcanzados',
        'sent_at' => 'Enviada el',
        'customer' => 'Cliente',
        'email' => 'Correo electrónico',
    ],

    'field' => [
        'body' => 'Texto',
        'is_mandatory' => 'Comunicación obligatoria',
        'portal_notice' => 'Mostrar en el portal de clientes',
    ],

    'filter' => [
        'search' => 'Búsqueda',
        'city' => 'Ciudad',
        'zip_prefix' => 'El código postal empieza por',
        'zip_hint' => 'p. ej. 30 para la zona de Hannover',
        'with_active_projects' => 'solo clientes con un proyecto activo',
    ],

    'status' => [
        'draft' => 'Borrador',
        'sending' => 'enviando',
        'sent' => 'enviada',
    ],

    'recipient_status' => [
        'pending' => 'pendiente',
        'sent' => 'entregada',
        'skipped' => 'omitido',
        'failed' => 'fallido',
    ],

    'reason' => [
        'no_email' => 'sin dirección de correo registrada',
    ],
];
