<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : inspection_order.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Prüfaufträge an Dienstleister (MVP-938).
return [
    'title' => 'Órdenes de inspección',
    'subtitle' => 'Encargar inspecciones pendientes a un proveedor: oferta, aceptación, resultados por equipo y adopción como registro de inspección.',
    'create' => 'Crear orden de inspección',
    'send' => 'Enviar orden',
    'open' => 'Abrir',
    'empty' => 'Aún no hay órdenes de inspección.',
    'no_schedules' => 'No hay fechas de inspección abiertas.',
    'due' => 'vence :date',
    'accept' => 'Aceptar oferta',
    'reject' => 'Rechazar oferta',
    'take_over' => 'Adoptar resultados',
    'taken_over' => 'adoptado',
    'cancel' => 'Anular orden',
    'confirm_cancel' => '¿Anular la orden? Las fechas de inspección vuelven a quedar libres.',
    'public_title' => 'Orden de inspección de :org',
    'public_offer' => 'Presentar oferta',
    'public_submit_offer' => 'Enviar oferta',
    'public_report' => 'Comunicar resultados',
    'public_submit_report' => 'Enviar resultados',
    'public_reported' => 'Los resultados se han enviado. Gracias.',
    'field' => [
        'title' => 'Denominación',
        'supplier' => 'Proveedor de inspección',
        'recipient_email' => 'Correo del proveedor',
        'items' => 'Equipos',
        'status' => 'Estado',
        'offer_amount' => 'Precio ofertado',
        'offer_planned_on' => 'Fecha prevista',
        'offer_note' => 'Observación sobre la oferta',
        'asset' => 'Equipo',
        'result' => 'Resultado',
        'performed_on' => 'Inspeccionado el',
        'valid_until' => 'Válido hasta',
        'certificate_no' => 'Número de certificado',
        'certificate_file' => 'Certificado (PDF)',
        'event' => 'Registro de inspección',
    ],
    'status' => [
        'requested' => 'Solicitado',
        'offered' => 'Oferta recibida',
        'accepted' => 'Encargado',
        'reported' => 'Resultados comunicados',
        'completed' => 'Completado',
        'cancelled' => 'Anulado',
    ],
    'flash' => [
        'sent' => 'Orden de inspección enviada a :email.',
        'decided' => 'Decisión guardada.',
        'taken_over' => 'Se adoptaron :count registros de inspección.',
        'cancelled' => 'Orden anulada.',
        'offered' => 'Gracias, hemos recibido su oferta.',
        'reported' => 'Gracias, hemos recibido los resultados.',
    ],
    'error' => [
        'no_schedules' => 'Elija al menos una fecha de inspección abierta.',
        'nothing_reported' => 'Indique al menos un resultado.',
        'transition' => 'La orden no puede pasar de «:from» a «:to».',
    ],
    'mail' => [
        'subject' => 'Orden de inspección de :org: :title',
        'body' => "Buenos días:\n\n:org le solicita una oferta para la inspección «:title» (:count equipos). Con el siguiente enlace presenta su oferta y, tras el encargo, comunica los resultados:\n:url\n\nEl enlace es válido hasta el :until.",
    ],
];
