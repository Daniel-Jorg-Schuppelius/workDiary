<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : b2b_catalog.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Acceso a catálogo B2B (funcionalidad 099): punchout OCI saliente + recepción de pedidos openTRANS.
return [
    'title' => 'Acceso a catálogo B2B',
    'intro' => 'Los sistemas de compra de sus clientes B2B acceden por OCI 4.0 al catálogo de artículos liberado y devuelven los pedidos como openTRANS 2.1 ORDER.',
    'punchout_url' => 'URL de punchout (para el sistema de compra del cliente)',

    'access_new_heading' => 'Emitir nuevo acceso',
    'access_new_hint' => 'Un acceso por cliente: usuario + secreto para el punchout OCI. El secreto se muestra solo una vez.',
    'access_heading' => 'Accesos punchout',
    'access_empty' => 'Aún no se han emitido accesos.',
    'access_title' => 'Acceso: :label',

    'new_secret_heading' => 'Nuevo secreto de punchout',
    'new_secret_hint' => 'Cópielo ahora y guárdelo en el sistema de compra del cliente — el texto claro se muestra solo esta vez.',

    'items_heading' => 'Artículos liberados',
    'items_hint' => 'Solo los artículos liberados explícitamente son visibles en el punchout. Sin precio de cliente rige el precio de venta estándar.',
    'items_empty' => 'Aún no hay artículos liberados.',

    'orders_heading' => 'Pedidos openTRANS',
    'orders_hint' => 'Los pedidos (carga, correo o nube) aparecen como propuestas en la bandeja de asignación; solo la contabilización crea el encargo.',
    'orders_empty' => 'Aún no se han recibido pedidos.',

    'field' => [
        'customer' => 'Cliente',
        'customer_placeholder' => '… seleccionar cliente',
        'label' => 'Etiqueta',
        'username' => 'Usuario',
        'items_count' => 'Artículos',
        'last_used' => 'Último uso',
        'status' => 'Estado',
        'actions' => 'Acciones',
        'article' => 'Artículo',
        'article_placeholder' => '… seleccionar artículo',
        'article_number' => 'N.º de artículo',
        'article_name' => 'Artículo',
        'default_price' => 'Precio estándar',
        'custom_price' => 'Precio de cliente',
        'custom_price_placeholder' => 'Estándar',
        'order_id' => 'N.º de pedido',
        'source' => 'Canal',
        'total_net' => 'Total neto',
        'ordered_at' => 'Fecha de pedido',
    ],

    'action' => [
        'datanorm' => 'Exportar DATPREIS',
        'issue' => 'Emitir acceso',
        'manage' => 'Gestionar',
        'revoke' => 'Desactivar',
        'rotate' => 'Rotar secreto',
        'back' => 'Volver al resumen',
        'release' => 'Liberar artículo',
        'remove' => 'Quitar',
        'upload_order' => 'Subir pedido',
    ],

    'status' => [
        'active' => 'Activo',
        'revoked' => 'Desactivado',
        'order_open' => 'Abierto (bandeja)',
        'order_booked' => 'Contabilizado',
        'order_dismissed' => 'Descartado',
    ],

    'flash' => [
        'datanorm_empty' => 'No hay artículos autorizados con precio para este acceso.',
        'datanorm_revoked' => 'Este acceso está revocado — ya no se exportan listas de precios del cliente.',
        'access_issued' => 'Acceso emitido.',
        'access_rotated' => 'Secreto rotado.',
        'access_revoked' => 'Acceso desactivado.',
        'item_released' => 'Artículo liberado.',
        'item_removed' => 'Liberación eliminada.',
        'order_received' => 'Pedido :id recibido — hay una propuesta en la bandeja de asignación.',
        'order_duplicate' => 'El pedido :id ya está registrado (sin cambios).',
    ],

    'error' => [
        'not_opentrans' => 'El archivo no es un openTRANS 2.1 ORDER legible: :reason',
        'customer_required' => 'Seleccione un cliente.',
        'not_open' => 'El pedido ya no está abierto.',
    ],

    'order' => [
        'entry_title' => 'Pedido :id',
        'entry_intro' => 'Pedido openTRANS :id (canal: :source).',
        'line_unmatched' => 'artículo sin asignar',
    ],

    'public' => [
        'title' => 'Catálogo B2B',
        'footer' => 'Catálogo punchout — el carrito se entrega a su sistema de compra; el pedido se realiza a través de su propio sistema.',
        'search_placeholder' => 'Número de artículo o denominación …',
        'search' => 'Buscar',
        'empty' => 'No se encontraron artículos liberados.',
        'col_number' => 'N.º art.',
        'col_name' => 'Denominación',
        'col_unit' => 'Unidad',
        'col_price' => 'Precio',
        'col_quantity' => 'Cantidad',
        'page_of' => 'Página :current de :last',
        'prev' => 'Anterior',
        'next' => 'Siguiente',
        'to_cart' => 'Entregar carrito',
        'transfer_title' => 'Entrega al sistema de compra',
        'transfer_hint' => 'El carrito se transfiere a su sistema de compra. Si la redirección no comienza automáticamente, use el botón.',
        'transfer_submit' => 'Transferir el carrito ahora',
        'error_title' => 'Acceso al catálogo',
        'error_hook_url' => 'HOOK_URL no válida — solo se permiten direcciones HTTPS.',
        'error_credentials' => 'Credenciales no válidas o acceso desactivado.',
        'error_session' => 'La sesión del catálogo ha caducado. Vuelva a iniciar el punchout desde su sistema de compra.',
        'error_empty_cart' => 'Ninguna posición con cantidad seleccionada.',
    ],
];
