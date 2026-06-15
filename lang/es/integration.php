<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : integration.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'webhook' => [
        'title' => [
            'index' => 'Webhooks',
            'subtitle' => 'Notificaciones de eventos salientes hacia sistemas externos.',
            'help' => '¿Cómo funcionan los webhooks?',
            'help_text' => 'Un webhook envía una carga JSON firmada mediante POST HTTPS a tu URL cuando ocurre un evento suscrito. La firma (HMAC-SHA256 sobre la marca de tiempo y el cuerpo) está en la cabecera X-WorkDiary-Signature; verifícala con la clave de firma. Tras varios intentos fallidos el endpoint se desactiva automáticamente.',
            'create' => 'Crear webhook',
            'edit' => 'Editar webhook',
            'empty' => 'Aún no se han creado webhooks.',
        ],
        'field' => [
            'basics' => 'Datos básicos',
            'label' => 'Etiqueta',
            'label_placeholder' => 'p. ej. integración ERP',
            'url' => 'URL de destino',
            'url_help' => 'Endpoint HTTPS que recibe la solicitud POST.',
            'events' => 'Eventos suscritos',
            'events_help' => 'Solo los eventos seleccionados activan un envío.',
            'security' => 'Seguridad y estado',
            'signing_secret' => 'Clave de firma',
            'endpoint_active' => 'Endpoint activo',
            'status' => 'Estado',
            'active' => 'Activo',
            'inactive' => 'Inactivo',
            'auto_disabled' => 'Desactivado automáticamente',
            'auto_disabled_help' => 'Desactivado automáticamente tras demasiados intentos fallidos. Al guardarlo como activo se reactiva el endpoint.',
            'last_deliveries' => 'Últimas entregas',
            'no_deliveries' => 'Aún no hay entregas.',
        ],
        'action' => [
            'create' => 'Crear',
            'edit' => 'Editar',
            'save' => 'Guardar',
            'delete' => 'Eliminar',
            'delete_confirm' => '¿Eliminar realmente este webhook? Los registros de entrega existentes se conservan.',
            'rotate_secret' => 'Rotar la clave de firma',
            'test' => 'Enviar evento de prueba',
        ],
        'secret' => [
            'shown_once' => 'Clave de firma – visible solo ahora',
            'shown_once_help' => 'Copia la clave ahora. Por motivos de seguridad no se volverá a mostrar en texto plano.',
            'rotate_help' => 'La clave en texto plano se muestra una sola vez al crear/rotar.',
            'rotate_confirm' => '¿Generar una nueva clave de firma? La clave anterior queda inválida de inmediato.',
        ],
        'flash' => [
            'created' => 'Webhook creado.',
            'updated' => 'Webhook actualizado.',
            'deleted' => 'Webhook eliminado.',
            'secret_rotated' => 'Clave de firma rotada.',
            'test_sent' => 'Evento de prueba en cola.',
        ],
        'event' => [
            'openIssue.assigned' => 'Punto abierto asignado',
            'openIssue.overdue' => 'Punto abierto vencido',
            'safetyEvent.reported' => 'Evento de seguridad reportado',
            'isms.incidentCritical' => 'Incidente de seguridad ISMS crítico',
            'timeCorrection.requested' => 'Corrección de tiempo de trabajo solicitada',
            'monthClosure.submitted' => 'Cierre mensual enviado',
            'sla.breached' => 'Plazo de SLA incumplido',
            'document.expired' => 'Documento caducado',
        ],
        'delivery_status' => [
            'pending' => 'Pendiente',
            'success' => 'Correcto',
            'failed' => 'Fallido',
        ],
    ],
];
