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
    'intro' => 'Los buzones se consultan periódicamente desde el último punto alcanzado, incluidos los correos ya leídos. Los correos nuevos llegan como sugerencias a la bandeja de integración y las facturas a las facturas recibidas; los correos procesados solo se marcan o se mueven, nunca se eliminan.',
    'to_inbox' => 'Ir a la bandeja de asignación',
    'title_edit' => 'Editar buzón',
    'to_inbox_count' => 'Ir a la bandeja de asignación (:count)',

    'mailboxes_heading' => 'Buzones',
    'no_connections' => 'Aún no hay ningún buzón conectado.',
    'add_heading' => 'Añadir buzón',

    'inbox' => [
        'invoice_action' => 'Pasar a factura recibida',
        'no_subject' => '(sin asunto)',
        'invoice_mailbox' => 'Buzón de facturas · :from',
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
        'mailbox_address' => 'Dirección del buzón',
        'mailbox_address_hint' => 'Déjelo vacío para el buzón de la cuenta conectada. Para un buzón compartido (p. ej. facturas@…), la cuenta necesita acceso completo.',
        'password_keep' => 'Déjelo vacío para conservar la contraseña guardada.',
        'fetch_since' => 'Consultar desde',
        'fetch_since_hint' => 'Los correos recibidos antes de este día no se consultan. Una fecha anterior recupera correos más antiguos.',
        'folder_hint_list' => 'Las sugerencias proceden del buzón; separe las subcarpetas con «/», p. ej. Bandeja de entrada/Facturas.',
        'folder_hint_error' => 'No se pudo cargar la lista de carpetas (:error). Introduzca la carpeta manualmente y separe las subcarpetas con «/».',
        'folder_hint_free' => 'Tras el primer guardado, el diálogo propone las carpetas del buzón. Separe las subcarpetas con «/», p. ej. INBOX/Facturas.',
        'einvoice_intake' => 'Buzón de facturas: pasar las facturas de los adjuntos a las facturas recibidas',
        'einvoice_intake_hint' => 'Cada archivo PDF, XML o de imagen de un correo se convierte en una factura recibida. Elija preferiblemente una carpeta propia en la que una regla clasifique las facturas.',
        'callreport_intake' => 'Buzón de informes de llamadas: pasar las listas de llamadas de FRITZ!Box (CSV) a la importación de listas de llamadas',
        'name' => 'Etiqueta',
        'transport' => 'Transporte',
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
        'create' => 'Nuevo buzón',
        'edit' => 'Editar',
        'backfill' => 'Recuperar correos anteriores',
        'activate' => 'Activar',
        'poll' => 'Consultar ahora',
        'disconnect' => 'Desconectar',
        'save' => 'Guardar',
    ],

    'col' => [
        'purpose' => 'Uso',
        'fetch_since' => 'desde :date',
        'host' => 'Cuenta',
        'status' => 'Estado',
        'last_polled' => 'Última consulta',
    ],

    'status' => [
        'error' => 'Error',
        'backfill_running' => 'Recuperación en curso · :count procesados',
        'active' => 'Activo',
        'inactive' => 'Inactivo',
    ],

    'flash' => [
        'invoice_imported' => 'Pasado a facturas recibidas: :count.',
        'invoice_duplicate' => 'Esta factura ya está en las facturas recibidas.',
        'invoice_none' => 'Ningún adjunto es un archivo PDF, XML o de imagen.',
        'invoice_failed' => 'No se pudo pasar a las facturas recibidas.',
        'backfill_started' => 'Recuperación iniciada. Las próximas consultas procesan los correos anteriores por lotes.',
        'activated' => 'Buzón activado.',
        'chained_folders' => 'Nota: una carpeta de este buzón es también la carpeta de consulta o de procesados de «:name». Los correos pasarían de una entrada a otra.',
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
    'transport' => [
        'imap' => 'Buzón IMAP',
    ],
    'access' => [
        'own_mailbox' => 'Buzón de la cuenta conectada',
    ],
    'group' => [
        'mailbox' => 'Buzón',
        'folders' => 'Carpetas y fecha de inicio',
        'purpose' => 'Uso',
    ],
    'purpose' => [
        'invoices' => 'Facturas',
        'callreports' => 'Listas de llamadas',
        'inbox' => 'Bandeja',
    ],
    'validation' => [
        'duplicate_folder' => 'Ya existe una entrada con esta carpeta para este acceso.',
    ],
    'backfill' => [
        'title' => 'Recuperar correos anteriores',
        'submit' => 'Recuperar',
        'intro' => 'Las próximas consultas procesan todos los correos de la carpeta «:folder» a partir del día elegido, incluidos los ya leídos.',
        'note_folder' => 'Solo se recupera esta carpeta. Para un buzón general, cree una segunda entrada con la carpeta de facturas.',
        'note_inbox' => 'Los correos sin factura llegan como siempre a la bandeja de integración.',
        'note_duplicates' => 'Los correos y archivos ya incorporados se reconocen y no se crean dos veces.',
        'note_graph_limit' => 'Microsoft 365 entrega como máximo 5.000 correos por pasada.',
    ],
    'error' => [
        'folder_missing' => 'No se encontró la carpeta «:folder» en el buzón.',
    ],
    'reference' => [
        'customer_number' => 'Número de cliente en el texto: :number',
        'invoice_number' => 'Número de factura en el texto: :number',
        'project_number' => 'Número de proyecto en el texto: :number',
    ],
];
