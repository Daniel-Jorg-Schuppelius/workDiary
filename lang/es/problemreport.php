<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : problemreport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'create' => 'Informar de un problema',
        'eyebrow' => 'Problema técnico',
        'index' => 'Mis informes de problemas',
        'index_subtitle' => 'Sus problemas técnicos notificados con número de referencia y estado.',
        'inbox' => 'Informes de problemas',
        'inbox_subtitle' => 'Informes técnicos entrantes: revisar, responder, convertir en ticket.',
    ],
    'section' => [
        'what' => '¿Qué ha pasado?',
        'context' => 'Datos transmitidos',
    ],
    'field' => [
        'summary' => 'Resumen',
        'description' => 'Descripción',
        'expected' => 'Comportamiento esperado',
        'actual' => 'Comportamiento observado',
        'severity' => 'Gravedad',
        'screenshots' => 'Capturas/adjuntos (máx. 3)',
        'contact_ok' => 'El soporte puede contactarme sobre este informe.',
        'contact_ok_short' => 'Contacto ok',
        'include_diagnostics' => 'Incluir extracto de diagnóstico anonimizado (recomendado)',
        'reference' => 'Referencia',
        'status' => 'Estado',
        'created_at' => 'Notificado el',
        'reporter' => 'Autor',
        'diagnostics' => 'Extracto de diagnóstico (anonimizado)',
        'delivery_error' => 'Error de envío',
        'ticket' => 'Ticket',
    ],
    'severity' => [
        'low' => 'Baja',
        'normal' => 'Normal',
        'high' => 'Alta',
        'blocking' => 'Bloqueante',
    ],
    'status' => [
        'new' => 'Nuevo',
        'in_review' => 'En revisión',
        'answered' => 'Respondido',
        'closed' => 'Cerrado',
    ],
    'delivery' => [
        'saas_inbox' => 'Bandeja de soporte (este sistema)',
        'mail' => 'Correo de soporte',
        'webhook' => 'Webhook',
        'local_export' => 'Exportación local',
    ],
    'action' => [
        'submit' => 'Enviar informe',
        'open' => 'Abrir',
        'set_status' => 'Establecer estado',
        'download' => 'Descargar como JSON',
        'convert' => 'Convertir en ticket',
    ],
    'hint' => [
        'context' => 'Estos datos técnicos se transmiten con su informe: sin datos de clientes ni pedidos.',
        'diagnostics_always' => 'Según la política de la organización se incluye un extracto de diagnóstico anonimizado.',
        'diagnostics_preview' => 'Ver extracto de diagnóstico (se transmite exactamente así)',
        'no_diagnostics' => 'Sin extracto de diagnóstico adjunto (decisión del autor o política de la organización).',
    ],
    'context' => [
        'route' => 'Página',
        'topic' => 'Tema de ayuda',
        'version' => 'Versión de la aplicación',
    ],
    'empty' => [
        'title' => 'Sin informes',
        'message' => 'Todavía no ha notificado ningún problema técnico.',
        'inbox_title' => 'Sin informes de problemas',
        'inbox_message' => 'Actualmente no hay informes de problemas técnicos.',
    ],
    'filter' => [
        'all_statuses' => 'Todos los estados',
    ],
    'flash' => [
        'created' => '¡Gracias! Su informe se registró como :reference.',
        'status_updated' => 'Estado actualizado.',
        'converted' => 'Convertido en el ticket :reference.',
        'already_converted' => 'Ya convertido en el ticket :reference.',
    ],
    'mail' => [
        'heading' => 'Informe de problema :reference',
        'contact_ok' => ':name acepta preguntas de seguimiento.',
        'attachment_hint' => 'El registro anonimizado completo se adjunta como JSON.',
    ],
];
