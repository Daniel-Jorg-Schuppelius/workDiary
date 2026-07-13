<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : mail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Recepción de correo',
    'intro' => 'Los buzones IMAP conectados se consultan mediante el planificador; los correos nuevos llegan como sugerencias a la bandeja de integración y se asocian a un cliente — nunca se crean a ciegas. Los correos procesados solo se marcan/mueven, nunca se eliminan. WorkDiary no es un cliente de correo.',
    'to_inbox' => 'Ir a la bandeja de asignación',

    'mailboxes_heading' => 'Buzones',
    'no_connections' => 'Aún no hay ningún buzón conectado.',
    'add_heading' => 'Añadir buzón',

    'inbox' => [
        'no_subject' => '(sin asunto)',
        'book_action' => 'Registrar como nota de comunicación',
        'book_ticket_action' => 'Registrar como ticket de servicio',
        'book_customer_placeholder' => '… cliente (vacío = remitente detectado)',
    ],

    'dms' => [
        'action' => 'Importar al archivo de documentos',
        'origin' => 'Importado del correo: :subject (Message-ID :message_id)',
        'imported' => ':count archivo(s) adjunto(s) importado(s) al archivo de documentos.',
        'none' => 'No hay archivos adjuntos importables.',
    ],

    'encryption' => [
        'none' => 'Ninguna',
    ],

    'field' => [
        'name' => 'Etiqueta',
        'host' => 'Servidor IMAP',
        'port' => 'Puerto',
        'encryption' => 'Cifrado',
        'username' => 'Nombre de usuario',
        'password' => 'Contraseña',
        'folder' => 'Carpeta',
        'processed_folder' => 'Carpeta de destino (procesados)',
        'processed_folder_placeholder' => 'opcional, p. ej. Procesados',
        'active' => 'Activo',
    ],

    'action' => [
        'poll' => 'Consultar ahora',
        'disconnect' => 'Desconectar',
        'save' => 'Guardar',
    ],

    'col' => [
        'host' => 'Cuenta',
        'status' => 'Estado',
        'last_polled' => 'Última consulta',
    ],

    'status' => [
        'active' => 'Activo',
        'inactive' => 'Inactivo',
    ],

    'flash' => [
        'saved' => 'Buzón guardado.',
        'disconnected' => 'Buzón desconectado.',
        'polled' => 'Consulta iniciada.',
        'booked' => 'Correo registrado como entrada de comunicación.',
        'book_failed' => 'Registro fallido.',
        'ticket_booked' => 'Correo registrado como ticket de servicio.',
        'ticket_failed' => 'Registro del ticket fallido.',
        'dms_failed' => 'Importación al archivo de documentos fallida.',
        'already_resolved' => 'Esta entrada ya está resuelta.',
        'password_required' => 'Un buzón nuevo requiere una contraseña.',
        'customer_required' => 'Ningún cliente asociado.',
    ],
    'reference' => [
        'customer_number' => 'Número de cliente en el texto: :number',
        'invoice_number' => 'Número de factura en el texto: :number',
        'project_number' => 'Número de proyecto en el texto: :number',
    ],
];
