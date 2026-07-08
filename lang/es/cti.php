<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : cti.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Telefonía / CTI',
    'intro' => 'Las llamadas entrantes de clientes conocidos se registran como entrada de comunicación (solo metadatos: dirección, número, hora, duración — nunca el contenido). El proveedor (sipgate, etc.) notifica las llamadas a la URL de webhook generada abajo. WorkDiary no es una centralita.',

    'note' => [
        'subject_inbound' => 'Llamada entrante de :number',
        'subject_outbound' => 'Llamada saliente a :number',
    ],

    // Ventana emergente de llamada (MVP-118) — notificación in-app al empleado
    // cuya extensión de opt-in fue marcada.
    'popup' => [
        'title_customer' => 'Llamada de :name',
        'title_unknown' => 'Llamada de :number',
        'message' => 'Llamada entrante (:number).',
        'unknown_number' => 'número desconocido',
    ],

    'profile' => [
        'heading' => 'Ventana emergente de llamada',
        'extension_label' => 'Mi extensión',
        'extension_help' => 'Cuando alguien llame a este número recibirás una ventana emergente con el llamante y —si se conoce— un enlace a la ficha del cliente. Déjalo vacío para no recibir ventanas.',
        'extension_placeholder' => 'p. ej. +49 30 1234-56',
        'invalid' => 'Introduce un número de teléfono válido.',
    ],

    'new_heading' => 'Nueva URL de webhook',
    'new_hint' => 'Introdúcela ahora en la centralita/el proveedor — el token se muestra solo esta vez.',

    'issue_heading' => 'Emitir una conexión',
    'connections_heading' => 'Conexiones',
    'no_connections' => 'Aún no se ha emitido ninguna conexión.',

    'field' => [
        'name' => 'Etiqueta',
        'name_placeholder' => 'p. ej. Recepción sipgate',
        'provider' => 'Proveedor',
    ],

    'action' => [
        'issue' => 'Emitir',
        'disconnect' => 'Desactivar',
    ],

    'col' => [
        'status' => 'Estado',
        'last_event' => 'Último evento',
    ],

    'status' => [
        'active' => 'Activo',
        'inactive' => 'Inactivo',
    ],

    'flash' => [
        'issued' => 'Conexión CTI emitida.',
        'disconnected' => 'Conexión CTI desactivada.',
    ],
];
