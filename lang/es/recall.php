<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : recall.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Rückrufaktionen (MVP-921/922).
return [
    'title' => 'Retiradas de productos',
    'nav' => 'Retiradas',
    'subtitle' => 'Retirada por variante de artículo: identificar entregas y clientes afectados, bloquear existencias y seguir el estado por cliente.',
    'empty' => 'No hay retiradas.',
    'items_none' => 'No hay entregas afectadas.',
    'yes' => 'sí',
    'no' => 'no',
    'kpi' => [
        'active' => 'Retiradas activas',
    ],
    'filter' => [
        'all_status' => 'Todos los estados',
    ],
    'field' => [
        'number' => 'Número',
        'title' => 'Denominación',
        'variant' => 'Variante de artículo',
        'kind' => 'Motivo',
        'open_items' => 'Abiertos / afectados',
        'status' => 'Estado',
        'reason' => 'Motivo y medida',
        'customer_message' => 'Mensaje a los clientes',
        'manufacturing_orders' => 'Órdenes de fabricación',
        'delivered_from' => 'Entregado desde',
        'delivered_until' => 'Entregado hasta',
        'serial_numbers' => 'Números de serie',
        'is_blocking_stock' => 'Bloquear las existencias del alcance',
        'activated_at' => 'Activado el',
        'delivered_at' => 'Entregado el',
        'customer' => 'Cliente',
        'quantity' => 'Cantidad',
        'serial' => 'Número de serie',
        'actions' => 'Acciones',
        'claim' => 'Reclamación',
        'sent_at' => 'Enviado el',
        'recipient' => 'Destinatario',
    ],
    'hint' => [
        'customer_message' => 'Se utiliza en el portal de clientes y en la carta.',
        'scope' => 'Los campos vacíos no restringen; todos los campos rellenados se aplican juntos.',
        'list' => 'Separados por comas o uno por línea.',
        'claim' => 'Abrir una reclamación con RMA para la devolución.',
    ],
    'section' => [
        'recall' => 'Retirada',
        'scope' => 'Alcance',
        'preview' => 'Vista previa de entregas afectadas',
        'items' => 'Entregas afectadas',
        'dispatches' => 'Justificantes de envío',
    ],
    'preview' => [
        'summary' => ':deliveries entregas afectadas, :stock números de serie en almacén',
        'none' => 'Ninguna entrega en este alcance.',
    ],
    'action' => [
        'create' => 'Crear retirada',
        'show' => 'Mostrar',
        'edit' => 'Editar',
        'save' => 'Guardar',
        'notify' => 'Informar a los clientes',
        'claim' => 'Reclamación',
    ],
    'dialog' => [
        'create' => 'Crear retirada',
        'edit' => 'Editar retirada',
    ],
    'transition' => [
        'active' => 'Activar',
        'completed' => 'Cerrar',
        'cancelled' => 'Cancelar',
    ],
    'confirm' => [
        'active' => '¿Activar la retirada? Las entregas afectadas se fijan y se bloquean las existencias del alcance.',
        'completed' => '¿Cerrar la retirada?',
        'cancelled' => '¿Cancelar la retirada? Se levantan los bloqueos de esta retirada.',
        'notify' => '¿Enviar un correo a todos los clientes con posiciones abiertas?',
    ],
    'item_transition' => [
        'notified' => 'Informado',
        'returned' => 'Devuelto',
        'resolved' => 'Resuelto',
    ],
    'status' => [
        'draft' => 'Borrador',
        'active' => 'Activa',
        'completed' => 'Cerrada',
        'cancelled' => 'Cancelada',
    ],
    'item_status' => [
        'open' => 'Abierto',
        'notified' => 'Informado',
        'returned' => 'Devuelto',
        'resolved' => 'Resuelto',
    ],
    'kind' => [
        'safety' => 'Seguridad',
        'quality' => 'Calidad',
        'regulatory' => 'Requisito normativo',
    ],
    'error' => [
        'not_draft' => 'Solo se pueden modificar los borradores.',
        'not_active' => 'Solo se puede informar en retiradas activas.',
    ],
    'flash' => [
        'created' => 'Retirada :number creada.',
        'saved' => 'Retirada guardada.',
        'status' => 'Estado: :status.',
        'item' => 'Estado guardado.',
        'notified' => ':count clientes informados.',
        'without_email' => 'Sin correo válido, informe por otra vía: :customers',
        'claim' => 'Reclamación :number abierta para la devolución.',
    ],
    'stats' => [
        'return_rate' => 'Tasa de devolución',
    ],
    'dispatch' => [
        'queued' => 'En cola',
        'sent' => 'Enviado',
        'failed' => 'Fallido',
        'none' => 'Aún no se ha enviado ninguna comunicación.',
    ],
    'mail' => [
        'subject' => 'Retirada: :title (:number)',
        'body' => "Estimado/a :name:\n\nretiramos el siguiente producto: :product.\n\n:message",
        'default_message' => 'Deje de utilizar el producto y póngase en contacto con nosotros; acordaremos la devolución o el cambio.',
        'serials' => 'Números de serie afectados: :serials',
    ],
    'claim' => [
        'title' => 'Retirada :number: :title',
    ],
    'portal' => [
        'subject' => 'Retirada: :title (:product)',
    ],
    // Behördenmeldung (MVP-945).
    'authority' => [
        'title' => 'Notificación a la autoridad',
        'save' => 'Guardar',
        'pdf' => 'Formulario de notificación',
        'pdf_title' => 'Formulario de retirada',
        'pdf_note' => 'Recopilación de datos para la notificación a la vigilancia del mercado; la notificación se realiza en el portal de la autoridad competente.',
        'section' => [
            'product' => 'Producto',
            'hazard' => 'Peligro y medida',
            'scope' => 'Alcance',
            'authority' => 'Autoridad',
        ],
        'field' => [
            'product' => 'Producto',
            'gtin' => 'GTIN',
            'batches' => 'Números de serie',
            'delivered' => 'Periodo de entrega',
            'hazard_kind' => 'Tipo de peligro',
            'hazard_description' => 'Descripción del peligro',
            'risk_level' => 'Nivel de riesgo',
            'measure' => 'Medida',
            'countries' => 'Países de distribución',
            'units' => 'Unidades afectadas',
            'customers' => 'Clientes afectados',
            'returned' => 'Devoluciones',
            'activated_at' => 'Retirada desde',
            'authority_name' => 'Autoridad',
            'authority_reference' => 'Referencia',
            'authority_reported_on' => 'Notificado el',
            'contact' => 'Contacto',
            'contact_name' => 'Persona de contacto',
            'contact_email' => 'Correo de contacto',
        ],
        'hint' => [
            'hazard_kind' => 'p. ej. incendio, descarga eléctrica, lesión, químico',
            'countries' => 'Códigos de país separados por comas (DE, AT, …)',
        ],
        'risk' => [
            'low' => 'bajo',
            'medium' => 'medio',
            'high' => 'alto',
            'serious' => 'grave',
        ],
        'measure' => [
            'withdrawal' => 'Retirada del mercado',
            'recall' => 'Retirada a los usuarios finales',
            'warning' => 'Advertencia',
            'destruction' => 'Destrucción',
        ],
        'flash' => [
            'saved' => 'Datos guardados.',
        ],
    ],
];
