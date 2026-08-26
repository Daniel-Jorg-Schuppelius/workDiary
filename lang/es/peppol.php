<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : peppol.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Envía y recibe documentos a través de un punto de acceso Peppol certificado. WorkDiary no opera un punto de acceso propio: aquí se configuran los extremos y los nombres de campo del proveedor.',
    ],
    'settings' => [
        'base_url' => 'URL base del proveedor',
        'base_url_help' => 'Raíz de la API del proveedor, p. ej. https://api.example-ap.eu/v1 — sin barra final.',
        'api_key' => 'Clave de acceso',
        'api_key_help' => 'Se guarda cifrada y se oculta en los registros.',
        'auth_header' => 'Cabecera de autenticación',
        'auth_header_help' => 'Cabecera que transporta la clave (predeterminado: Authorization).',
        'auth_scheme' => 'Prefijo de autenticación',
        'auth_scheme_help' => 'Prefijo como Bearer. Dejar vacío si el proveedor espera solo la clave.',
        'send_path' => 'Extremo de envío (ruta)',
        'receive_path' => 'Extremo de recepción (ruta)',
        'ack_path' => 'Extremo de acuse (ruta)',
        'ack_path_help' => 'El marcador {messageId} se sustituye por el identificador del mensaje; sin él, el identificador viaja en el cuerpo.',
        'health_path' => 'Extremo de estado (ruta)',
        'payload_field' => 'Nombre del campo del sobre',
        'payload_field_help' => 'Campo JSON que contiene el sobre SBDH. Dejar vacío si el proveedor espera XML sin envolver.',
        'message_id_field' => 'Nombre del campo del identificador de mensaje',
        'status_field' => 'Nombre del campo del estado de transporte',
        'items_field' => 'Nombre del campo de la lista de entrada',
        'sender_participant_id' => 'Identificador de participante Peppol propio',
        'sender_participant_id_help' => 'Forma <ICD>:<identificador>, p. ej. 9930:DE123456789. Debe estar registrado ante el proveedor para esta organización.',
        'sender_country' => 'País del remitente',
        'sender_country_help' => 'Dos letras (ISO 3166-1), escritas en el sobre como COUNTRY_C1.',
        'sml_zone' => 'Zona SML',
        'sml_zone_help' => 'Producción o prueba. Las zonas NAPTR son el procedimiento actual; las zonas CNAME solo quedan de la migración.',
        'lookup_ttl_hours' => 'Validez de la comprobación del participante (horas)',
        'lookup_ttl_hours_help' => 'Cuánto tiempo vale un resultado SMP antes de resolver de nuevo. 0 = resolver cada vez.',
    ],
    'health' => [
        'not_configured' => 'No hay credenciales del proveedor de punto de acceso guardadas.',
        'sender_invalid' => 'Falta el identificador de participante Peppol propio o no tiene la forma <ICD>:<identificador>.',
        'unreachable' => 'El proveedor de punto de acceso no responde o rechaza la clave de acceso.',
        'ok' => 'Conectado con :url.',
    ],
    'field' => [
        'participant_id' => 'Identificador de participante Peppol',
        'participant_id_hint' => 'Forma <ICD>:<identificador>, p. ej. 9930:DE123456789 (NIF-IVA) o 0204:991-12345-67 (Leitweg-ID). Vacío = sin envío Peppol a este cliente.',
    ],
    'action' => [
        'send' => 'Enviar por Peppol',
        'send_title' => 'Entregar la factura a través del proveedor de punto de acceso — la prueba de entrega es el acuse de transporte.',
        'check' => 'Comprobar el registro Peppol',
    ],
    'validator' => [
        'scope' => 'Se comprobó un subconjunto de las reglas Peppol BIS Billing 3.0 (:scenario) — expresamente no es una declaración de conformidad completa. La comprobación Schematron completa la realizan el validador KoSIT y el punto de acceso.',
    ],
    'error' => [
        'not_configured' => 'Para esta organización no hay ningún punto de acceso Peppol configurado (complemento «Peppol Access Point»).',
        'sender_invalid' => 'Falta el identificador de participante Peppol propio o no es válido — está en los ajustes del complemento.',
        'no_participant' => 'Para :customer no hay ningún identificador de participante Peppol guardado.',
        'invalid_participant' => 'El identificador de participante Peppol de :customer no es válido: :value',
        'not_registered' => 'El destinatario :participant no está registrado en Peppol.',
        'unsupported_document' => 'El destinatario :participant no acepta el formato :document por Peppol.',
        'lookup_failed' => 'La resolución del participante Peppol ha fallado: :message',
        'validation' => 'La factura no cumple las reglas Peppol comprobadas: :messages',
        'transport' => 'El punto de acceso no aceptó el envío: :message',
        'not_issued' => 'Solo las facturas emitidas pueden entregarse por Peppol.',
        'external_billing' => 'La facturación pertenece a un sistema externo — WorkDiary no entrega facturas para este cliente.',
        'proforma' => 'Las facturas pro forma no son facturas electrónicas y no van por Peppol.',
    ],
    'status' => [
        'registered' => 'Registrado en Peppol (SMP :smp, :count formatos de documento).',
        'not_registered' => 'No registrado en Peppol.',
        'checked_at' => 'Última comprobación: :at',
        'never_checked' => 'Todavía sin comprobar.',
    ],
    'flash' => [
        'sent' => 'Factura entregada a :participant (mensaje :message, estado de transporte :status).',
        'checked' => 'Comprobación Peppol para :customer: :result',
    ],
    'inbound' => [
        'summary' => 'Entrada Peppol: :fetched recuperados, :imported incorporados, :duplicates duplicados, :unreadable ilegibles.',
        'document_name' => 'peppol-:id.xml',
    ],
];
