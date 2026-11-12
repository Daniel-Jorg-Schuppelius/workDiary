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
    // Online-Vorauszahlung der Direktbuchung (MVP-1515).
    'prepayment' => [
        'hint' => 'Una reserva directa requiere un pago anticipado en línea del :percent % del precio bruto. La reserva se mantiene :minutes minutos; se confirma al recibir el pago.',
        'amount' => 'Pago anticipado :amount',
        'due' => 'Pago hasta :until',
        'pay_now' => 'Pagar ahora',
        'unavailable' => 'La reserva directa no es posible en este momento porque no hay ningún pago en línea configurado. Envíe una solicitud.',
        'not_due' => 'No hay ningún pago pendiente para esta reserva.',
        'expired_error' => 'El plazo de pago ha vencido; la reserva se ha liberado.',
        'checkout_failed' => 'La página de pago no está disponible en este momento. Su reserva se mantiene hasta :until; vuelva a intentarlo con «Pagar ahora».',
        'description' => 'Pago anticipado :subject desde :from',
        'confirmed' => 'Pago recibido: su reserva está confirmada.',
        'pending' => 'El pago aún no está confirmado. En cuanto el proveedor de pagos lo comunique, su reserva quedará confirmada.',
        'late_notification' => 'Pago anticipado tras el vencimiento de :customer',
        'late_message' => ':subject: la reserva ya había caducado. Reserve de nuevo o reembolse el pago.',
        'expired_reason' => 'Pago anticipado en línea no recibido a tiempo.',
        'withdrawn_reason' => 'Reserva directa no pagada retirada por el cliente.',
        'on_case' => 'Pago anticipado en línea :amount pagado: se imputa a la factura.',
        'credited_on_case' => 'Pago anticipado en línea de :amount transferido al programa de contabilidad con las partidas de alquiler; descuéntelo allí en la factura.',
        'profile_percent' => 'Pago anticipado en la reserva directa (%)',
        'profile_percent_hint' => 'Vacío = valor de la organización, 0 = sin pago anticipado para este objeto de alquiler.',
        'badge' => 'Pago anticipado :percent %',
        'hint_list' => 'Una reserva directa requiere el pago anticipado en línea indicado en la lista. La reserva se mantiene :minutes minutos; se confirma al recibir el pago.',
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
    // Vormerkhinweis, Protokollpositionen, Mindestzustand, Reinigung (MVP-1604).
    'condition' => [
        'below_minimum' => ':asset tiene el estado «:current», inferior al estado mínimo «:minimum» del perfil de alquiler, y no puede asignarse.',
        'below_badge' => 'por debajo del estado mínimo',
    ],
    'soft' => [
        'title' => 'Existen prerreservas para estos equipos en este periodo',
        'hint' => 'Las prerreservas no bloquean. Aclare con los expedientes indicados quién recibe el equipo.',
        'reserved_warning' => 'Existe :count prerreserva de otro expediente en este periodo. Los expedientes afectados figuran en el expediente.|Existen :count prerreservas de otros expedientes en este periodo. Los expedientes afectados figuran en el expediente.',
    ],
    'cleaning' => [
        'not_open' => 'Esta franja no es una franja de limpieza abierta.',
        'done' => 'Limpieza terminada: el equipo puede asignarse de nuevo.',
        'mark_done' => 'Limpieza terminada',
        'form_hint' => 'Crea una franja de limpieza; el equipo no puede asignarse hasta terminar la limpieza.',
    ],
    'report' => [
        'checklist' => 'Lista de comprobación',
        'accessories' => 'Accesorios',
        'quantity_of' => 'Cantidad :label',
        'accessories_hint' => 'Desmarque una pieza que falte.',
        'accessory_missing' => 'falta',
        'condition_items' => 'Puntos de estado',
        'item_label' => 'Pieza o punto de control',
        'item_label_n' => 'Punto de estado :n: denominación',
        'item_state_n' => 'Punto de estado :n: resultado',
        'item_note' => 'Observación',
        'item_note_n' => 'Punto de estado :n: observación',
        'rows_hint' => 'Las filas vacías no se guardan.',
        'consumables' => 'Consumo',
        'consumable_label' => 'Consumible',
        'consumable_label_n' => 'Consumo :n: denominación',
        'consumable_quantity_n' => 'Consumo :n: cantidad',
        'consumable_unit_n' => 'Consumo :n: unidad',
        'details' => 'Mostrar los puntos',
        'point_done' => 'hecho',
        'point_open' => 'pendiente',
        'returns' => 'Devoluciones',
    ],
    'profile' => [
        'home_site' => 'Sede habitual',
        'min_condition' => 'Estado mínimo',
        'min_condition_hint' => 'Si el estado actual es inferior, el equipo no puede asignarse.',
        'no_minimum' => 'sin estado mínimo',
        'current_condition' => 'Estado actual',
        'current_condition_hint' => 'Se toma del acta en cada devolución; ajústelo aquí tras una reparación.',
        'condition_unknown' => 'no registrado',
        'checklist' => 'Lista de comprobación para entrega y devolución (un punto por línea)',
        'checklist_hint' => 'Los puntos aparecen para marcar en las actas de entrega y devolución.',
    ],
    'deposit' => [
        'waive' => 'Condonar',
        'waive_reason' => 'Motivo de la condonación',
        'flash' => [
            'waived' => 'Fianza condonada.',
        ],
        'error' => [
            'not_waivable' => 'Solo se pueden condonar las fianzas solicitadas que aún no se han recibido.',
            'reason_required' => 'La condonación de una fianza requiere un motivo.',
        ],
    ],
];
