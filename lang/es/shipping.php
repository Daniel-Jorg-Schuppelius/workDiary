<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : shipping.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Envío y logística',
    'intro' => 'Conexiones de transportista para etiquetas de envío y seguimiento de envíos (DHL Paket, UPS, FedEx). Una conexión por transportista y organización; las credenciales se almacenan cifradas.',

    'form_heading' => 'Añadir conexión',
    'form_heading_edit' => 'Editar conexión :carrier',
    'form_hint' => 'Elija el transportista e introduzca sus credenciales. Modifique las conexiones existentes mediante «Editar» en la lista.',
    'secret_hint' => 'La contraseña y la clave API se almacenan cifradas y no se vuelven a mostrar. Déjelas vacías al editar para mantener los valores guardados.',
    'connections_heading' => 'Conexiones existentes',
    'no_connections' => 'Aún no hay ninguna conexión de transportista configurada.',

    'field' => [
        'carrier' => 'Transportista',
        'name' => 'Denominación',
        'username' => 'Usuario / ID de cliente',
        'password' => 'Contraseña / secreto de cliente',
        'api_key' => 'Clave API (solo DHL: dhl-api-key)',
        'returns_receiver_id' => 'ID del destinatario de devoluciones (solo DHL)',
        'returns_receiver_id_hint' => 'Destinatario de devoluciones creado en el portal de clientes empresariales de DHL; necesario para las etiquetas de devolución.',
        'billing_number' => 'Número de facturación / de cuenta',
        'sandbox' => 'Sandbox / entorno de pruebas',
        'active' => 'Activo',
        'weight_grams' => 'Peso (g)',
        'length_cm' => 'Longitud (cm)',
        'width_cm' => 'Anchura (cm)',
        'height_cm' => 'Altura (cm)',
    ],

    'label_short' => 'Envío',
    'last_tracked' => 'Última comprobación: :time',
    'confirm_cancel' => '¿Anular este envío ante el transportista? La etiqueta deja de ser válida; después podrá crear un envío nuevo.',

    'col' => [
        'mode' => 'Modo',
        'status' => 'Estado',
    ],

    'mode' => [
        'sandbox' => 'Sandbox',
        'production' => 'Producción',
    ],

    'status_label' => [
        'active' => 'Activo',
        'inactive' => 'Inactivo',
    ],

    'action' => [
        'save' => 'Guardar',
        'disconnect' => 'Desactivar',
        'edit' => 'Editar',
        'cancel_edit' => 'Cancelar',
        'download_label' => 'Descargar etiqueta',
        'track_now' => 'Consultar estado del envío',
        'cancel_shipment' => 'Anular envío',
        'create' => 'Enviar',
    ],

    'flash' => [
        'saved' => 'Conexión de transportista guardada.',
        'disconnected' => 'Conexión de transportista desactivada.',
        'credentials_required' => 'Una nueva conexión requiere usuario/ID de cliente y contraseña/secreto de cliente (DHL además: clave API).',
        'no_recipient' => 'La entrega no tiene un cliente como destinatario.',
        'already_created' => 'Ya existe un envío para esta entrega.',
        'no_connection' => 'No hay una conexión activa configurada para el transportista seleccionado.',
        'label_created' => 'Envío creado y etiqueta obtenida.',
        'label_failed' => 'No se pudo crear la etiqueta de envío: :reason',
        'tracked' => 'Estado del envío consultado: :status',
        'track_failed' => 'No se pudo consultar el estado del envío: :reason',
        'cancelled' => 'Envío anulado.',
        'cancel_failed' => 'No se pudo anular el envío: :reason',
        'not_cancellable' => 'El envío ya está en manos del transportista y no puede anularse.',
        'exists_use_edit' => 'Ya existe una conexión para este transportista. Modifíquela mediante «Editar».',
    ],

    'notify' => [
        'delivery_problem' => [
            'title' => 'Problema de entrega de un envío',
            'message' => 'El envío :tracking (:carrier) informa de un problema de entrega.',
        ],
    ],

    // Estado del envío (ShipmentStatus).
    'status' => [
        'draft' => 'Borrador',
        'labeled' => 'Etiqueta creada',
        'in_transit' => 'En tránsito',
        'delivered' => 'Entregado',
        'problem' => 'Problema de entrega',
        'cancelled' => 'Cancelado',
    ],
    'parcel' => [
        'add' => 'Añadir bulto',
        'edit' => 'Editar bulto :no',
        'delete' => 'Eliminar bulto',
        'confirm_delete' => '¿Eliminar el bulto :no? Sus números de serie vuelven a quedar libres.',
        'label' => 'Bulto :no de :of',
        'serials' => 'Números de serie en el bulto',
        'no_serials' => 'Esta entrega no tiene números de serie libres.',
        'serial_count' => ':count n.º de serie',
        'saved' => 'Bulto guardado.',
        'deleted' => 'Bulto eliminado.',
        'serial_not_allowed' => 'Los números de serie deben proceder de esta entrega y no pueden estar en otro bulto.',
        'locked' => 'Ya existe una orden de envío para esta entrega; los bultos están fijados.',
    ],
    'customs' => [
        'action' => 'Documentos aduaneros',
        'dialog_title' => 'Crear documentos aduaneros',
        'required_hint' => 'Destino fuera de la UE: para la exportación se necesitan documentos aduaneros.',
        'eu_hint' => 'Destino dentro de la UE: por lo general no se necesitan documentos aduaneros. Excepciones son los territorios fuera del territorio aduanero, como las Islas Canarias o Heligoland.',
        'reason' => 'Motivo del envío',
        'reason_hint' => 'Una venta genera una factura comercial; en otro caso, una factura proforma. El motivo se guarda en la entrega.',
        'submit' => 'Crear PDF',
        'commercial_invoice' => 'Factura comercial',
        'proforma_invoice' => 'Factura proforma',
        'value' => 'Valor de la mercancía (precio)',
        'reasons' => [
            'sale' => 'Venta',
            'gift' => 'Regalo',
            'sample' => 'Muestra comercial',
            'documents' => 'Documentos',
            'returned_goods' => 'Devolución',
            'repair' => 'Reparación',
            'other' => 'Otro',
        ],
        'error' => [
            'no_customer' => 'La entrega no tiene destinatario.',
            'missing' => 'Para los documentos aduaneros de «:article» falta: :fields.',
        ],
        'pdf' => [
            'date' => 'Fecha',
            'delivery_note' => 'Albarán',
            'invoice' => 'Factura',
            'tracking' => 'Número de seguimiento',
            'sender' => 'Remitente',
            'recipient' => 'Destinatario',
            'vat_id' => 'NIF-IVA',
            'eori' => 'Número EORI',
            'reason' => 'Motivo del envío',
            'currency' => 'Moneda',
            'col' => [
                'description' => 'Descripción de la mercancía',
                'tariff' => 'Código arancelario',
                'origin' => 'País de origen',
                'quantity' => 'Cantidad',
                'net_weight' => 'Peso neto (kg)',
                'unit_value' => 'Valor unitario',
                'total_value' => 'Valor total',
            ],
            'total_net_weight' => 'Peso neto total',
            'gross_weight' => 'Peso bruto',
            'parcels' => 'Bultos',
            'total_value' => 'Valor total',
            'no_sale' => 'Sin venta: el valor se indica solo a efectos aduaneros.',
            'declaration' => 'Declaramos que los datos de este documento son correctos y completos.',
            'place_date' => 'Lugar, fecha',
            'signature' => 'Firma',
        ],
    ],
];
