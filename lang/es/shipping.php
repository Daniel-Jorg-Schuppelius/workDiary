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
    'intro' => 'Conexiones de transportista para etiquetas de envío y seguimiento de envíos (DHL Paket y otros). Una conexión por transportista y organización; las credenciales se almacenan cifradas.',

    'form_heading' => 'Añadir / editar conexión',
    'form_hint' => 'Elige el transportista e introduce sus credenciales. Guardar de nuevo con el mismo transportista actualiza la conexión existente.',
    'secret_hint' => 'La contraseña y la clave API se almacenan cifradas y no se vuelven a mostrar. Déjalas vacías al editar para mantener los valores guardados.',
    'connections_heading' => 'Conexiones existentes',
    'no_connections' => 'Aún no hay ninguna conexión de transportista configurada.',

    'field' => [
        'carrier' => 'Transportista',
        'name' => 'Denominación',
        'username' => 'Usuario (cuenta comercial)',
        'password' => 'Contraseña',
        'api_key' => 'Clave API (dhl-api-key)',
        'billing_number' => 'Número de facturación',
        'sandbox' => 'Sandbox / entorno de pruebas',
        'active' => 'Activo',
        'weight_grams' => 'Peso (g)',
    ],

    'label_short' => 'Envío',

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
        'create' => 'Enviar',
    ],

    'flash' => [
        'saved' => 'Conexión de transportista guardada.',
        'disconnected' => 'Conexión de transportista desactivada.',
        'credentials_required' => 'El usuario, la contraseña y la clave API son obligatorios para una nueva conexión.',
        'no_recipient' => 'La entrega no tiene un cliente como destinatario.',
        'already_created' => 'Ya existe un envío para esta entrega.',
        'no_connection' => 'No hay una conexión activa configurada para el transportista seleccionado.',
        'label_created' => 'Envío creado y etiqueta obtenida.',
        'label_failed' => 'No se pudo crear la etiqueta de envío: :reason',
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
];
