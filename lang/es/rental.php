<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : rental.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'terms' => [
        'title' => 'Condiciones de alquiler',
        'signed' => 'Contrato :contract, versión :revision, firmado el :date',
        'missing' => 'No hay condiciones de alquiler firmadas para este cliente.',
        'missing_required' => 'No hay condiciones de alquiler firmadas; la entrega solo es posible después.',
        'create_agreement' => 'Crear condiciones de alquiler',
        'required' => 'La entrega requiere condiciones de alquiler firmadas por el cliente (ajuste de la organización).',
    ],
    // Direktbuchung und Preisangabe im Portal (MVP-916).
    'portal' => [
        'price' => 'Precio (neto)',
        'price_estimate' => 'aprox. :amount',
        'direct_intro' => 'Puede reservar directamente los equipos libres o solicitar un equipo o un grupo de equipos, y lo confirmaremos. El precio procede de la lista de precios y es neto.',
        'direct_book' => 'Reservar ahora',
        'direct_hint' => 'Reserva de inmediato el equipo elegido si está libre en el periodo.',
        'direct_booked' => 'Equipo reservado — le enviaremos la documentación de entrega.',
        'direct_status' => 'Reservado directamente',
        'direct_disabled' => 'La reserva directa no está activada.',
        'direct_case_note' => 'Reserva directa desde el portal de clientes.',
        'direct_notification' => 'Reserva directa de :customer',
    ],
    // Mietpreisregeln (MVP-950).
    'rule' => [
        'title' => 'Reglas de precio de alquiler',
        'empty' => 'Sin reglas: se aplica la tarifa diaria.',
        'add' => 'Añadir regla',
        'line' => ':label (:percent %)',
        'from_utilization' => 'a partir del :percent % de ocupación',
        'kind' => [
            'season' => 'Temporada',
            'weekday' => 'Días de la semana',
            'utilization' => 'Ocupación',
        ],
        'field' => [
            'kind' => 'Tipo',
            'label' => 'Denominación',
            'valid_from' => 'Válido desde',
            'valid_until' => 'Válido hasta',
            'weekdays' => 'Días de la semana',
            'utilization_min_percent' => 'A partir de ocupación (%)',
            'adjust_percent' => 'Recargo/descuento (%)',
        ],
        'weekday' => [
            '1' => 'lun.',
            '2' => 'mar.',
            '3' => 'mié.',
            '4' => 'jue.',
            '5' => 'vie.',
            '6' => 'sáb.',
            '7' => 'dom.',
        ],
        'flash' => [
            'saved' => 'Regla de precio guardada.',
            'deleted' => 'Regla de precio eliminada.',
        ],
    ],
];
